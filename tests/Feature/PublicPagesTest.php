<?php

use App\Filament\Admin\Resources\DemoRequests\Pages\ManageDemoRequests;
use App\Models\DemoRequest;
use App\Models\ReportCardTemplate;
use App\Models\User;
use App\Notifications\DemoRequested;
use App\Support\PublicSite;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    config(['app.url' => 'https://www.schoolhubug.com']);
});

it('serves every public page with its own title, the menu and a canonical address', function (string $slug) {
    [$label, $title] = PublicSite::PAGES[$slug];

    $response = $this->get('/'.$slug)->assertOk()
        ->assertSee("<title>{$title} | SchoolHub</title>", false)
        ->assertSee('<link rel="canonical" href="https://www.schoolhubug.com/'.$slug.'">', false);

    expect($response->getContent())->toMatch('#aria-current="page"\s*>'.preg_quote($label, '#').'</a>#');

    foreach (PublicSite::PAGES as $other => [$otherLabel]) {
        $response->assertSee('href="'.route('filament.app.site.page', $other).'"', false);
    }
})->with(array_keys(PublicSite::PAGES));

it('shows the phone, location and opening hours on the Contact page', function () {
    $this->get('/contact')->assertOk()
        ->assertSee('0782 863209')
        ->assertSee('Wakiso, Uganda')
        ->assertSee('Monday to Friday, 8:00 am – 6:00 pm')
        ->assertSee('Request a demo')
        ->assertSee('info@schoolhubug.com')
        ->assertSee('support@schoolhubug.com');
});

it('gives the founder their own address on the team page, and sends system emails with replies to support', function () {
    $this->get('/team')->assertOk()->assertSee('mailto:adrian.mugizi@schoolhubug.com', false);

    expect(config('mail.reply_to.address'))->toBe('support@schoolhubug.com');
});

it('shows the team: the founder, the company and a card to join', function () {
    $this->get('/team')->assertOk()
        ->assertSee('Adrian Mugizi')
        ->assertSee('Founder, FERO TECH SMC LIMITED &amp; SchoolHub.', false)
        ->assertSee('You — Join us');

    $this->get('/about')->assertOk()->assertSee('/team');
});

it('says in the terms who owns SchoolHub and that it may not be copied', function () {
    $this->get(route('filament.app.legal.terms'))->assertOk()
        ->assertSee('SchoolHub is owned and operated by <b>FERO TECH SMC LIMITED</b>', false)
        ->assertSee('Ownership of SchoolHub')
        ->assertSee('Address: FERO TECH SMC LIMITED, Wakiso, Uganda');
});

it('tells Google the business phone, place and hours', function () {
    $this->get('/')->assertOk()
        ->assertSee('"telephone": "+256782863209"', false)
        ->assertSee('"addressLocality": "Wakiso"', false)
        ->assertSee('"opens": "08:00"', false)
        ->assertSee('"closes": "18:00"', false);
});

it('lists every public page in the sitemap', function () {
    $response = $this->get('/sitemap.xml')->assertOk();

    foreach (array_keys(PublicSite::PAGES) as $slug) {
        $response->assertSee("<loc>https://www.schoolhubug.com/{$slug}</loc>", false);
    }
});

it('keeps robots.txt and the sitemap open while the site is being updated', function () {
    $this->app->maintenanceMode()->activate(['retry' => 30]);

    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: https://www.schoolhubug.com/sitemap.xml');
    $this->get('/sitemap.xml')->assertOk();
    $this->get('/pricing')->assertServiceUnavailable();

    $this->app->maintenanceMode()->deactivate();
});

it('shows the plans on the Pricing page', function () {
    $this->get('/pricing')->assertOk()->assertSee('Simple pricing, paid per term')->assertSee('Standard');
});

it('returns a demo request from the Contact page to the Contact page', function () {
    $this->from('/contact')
        ->post(route('filament.app.demo-request'), [
            'name' => 'Sarah Nakato', 'school_name' => 'Hope Primary', 'phone' => '0772123456', 'preferred_contact' => 'whatsapp',
        ])
        ->assertRedirect(route('filament.app.site.page', 'contact').'#demo');

    expect(DemoRequest::where('name', 'Sarah Nakato')->exists())->toBeTrue();

    $this->from('/')
        ->post(route('filament.app.demo-request'), ['name' => '', 'school_name' => '', 'phone' => '', 'preferred_contact' => 'whatsapp'])
        ->assertRedirect(url('/').'#demo');
});

it('files a sales pitch sent through the demo form as spam, without emailing the team', function () {
    Notification::fake();

    $this->from('/contact')->post(route('filament.app.demo-request'), [
        'name' => 'Chloe', 'school_name' => 'Chloe', 'phone' => '718054198', 'preferred_contact' => 'whatsapp',
        'message' => 'Hello http://schoolhubug.com, I wanted to reach out to see if you need more website traffic.',
    ]);
    $this->from('/contact')->post(route('filament.app.demo-request'), [
        'name' => 'Sarah Nakato', 'school_name' => 'Hope Primary', 'phone' => '0772123456', 'preferred_contact' => 'whatsapp',
        'message' => 'We have 450 learners and want to see the fees module.',
    ]);

    expect(DemoRequest::where('name', 'Chloe')->value('status'))->toBe('spam')
        ->and(DemoRequest::where('name', 'Sarah Nakato')->value('status'))->toBe('new');

    Notification::assertSentOnDemandTimes(DemoRequested::class, 1);
});

it('opens WhatsApp on the full international number, however the phone was typed', function (string $typed, string $expected) {
    $demo = new DemoRequest(['name' => 'Sarah', 'school_name' => 'Hope Primary', 'phone' => $typed]);

    expect($demo->whatsappUrl())->toStartWith('https://wa.me/'.$expected.'?text=');
})->with([
    ['0772 123 456', '256772123456'],
    ['772123456', '256772123456'],
    ['+256 772 123456', '256772123456'],
    ['+254 712 345678', '254712345678'],
]);

it('keeps unknown addresses as not found', function () {
    $this->get('/careers')->assertNotFound();
});

it('offers SchoolHub for Windows on the home, Features and Pricing pages', function (string $path) {
    config(['contact.windows_download_enabled' => true]);

    $this->get($path)->assertOk()
        ->assertSee('SchoolHub for Windows')
        ->assertSee('href="https://github.com/DualhubTechnologies/schoolapp/releases/latest/download/SchoolHub-Setup.exe"', false)
        ->assertSee('Download for Windows');
})->with(['/', '/features', '/pricing']);

it('hides the Windows download everywhere while it is switched off', function (string $path) {
    config(['contact.windows_download_enabled' => false]);

    $this->get($path)->assertOk()
        ->assertDontSee('SchoolHub-Setup.exe')
        ->assertDontSee('Download for Windows')
        ->assertDontSee('Get SchoolHub for Windows');
})->with(['/', '/features', '/pricing', '/contact']);

it('lets the platform owner email a demo request from the Demo requests page', function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create()->assignRole('Super Admin'));

    $demo = DemoRequest::create([
        'name' => 'Sarah Nakato', 'school_name' => 'Hope Primary', 'phone' => '0772123456',
        'email' => 'sarah@hope.ac.ug', 'preferred_contact' => 'email', 'status' => 'new',
    ]);

    Livewire::test(ManageDemoRequests::class)
        ->callTableAction('email', $demo, data: ['subject' => 'SchoolHub demo', 'body' => 'Hello Sarah'])
        ->assertNotified();

    expect($demo->fresh()->status)->toBe('contacted')
        ->and($demo->fresh()->notes)->toContain('Emailed');
});

it('says SchoolHub is a product of FERO TECH', function () {
    $this->get('/')->assertOk()->assertSee('A product of <strong>FERO TECH</strong>', false);
});

it('lists the modules schools get, and promises no student portal yet', function () {
    $this->get('/')->assertOk()
        ->assertSee('School fees management')
        ->assertSee('Parent communication')
        ->assertSee('See all 9 modules')
        ->assertSee('Can parents see their child')
        ->assertDontSee('student portal');

    $this->get('/features')->assertOk()
        ->assertSee('Attendance')
        ->assertSee('Transport &amp; ID cards', false)
        ->assertSee('Access control &amp; audit trail', false)
        ->assertDontSee('See all 9 modules');
});

it('leads the home page with the school\'s money and its report cards', function () {
    $this->get('/')->assertOk()
        ->assertSee('Run your school')
        ->assertSee('generate curriculum-aligned report cards in seconds')
        ->assertSee('Made for schools across Uganda')
        ->assertDontSee('Print every report card')
        ->assertSee('Income vs expenditure')
        ->assertSee('School fees and salaries fill in by themselves')
        ->assertSee('New-curriculum report cards, generated automatically in seconds')
        ->assertSee('A-Level report cards with UACE grades and points, generated automatically')
        ->assertSee('images/site/report-card-o-level.jpg')
        ->assertSee('images/site/report-card-a-level.jpg');
});

it('shows that each school designs its own report card, with the real choices', function () {
    $this->get('/')->assertOk()
        ->assertSee('id="report-card-designer"', false)
        ->assertSee("Not a fixed format. Your school's own report card.", false)
        ->assertSeeInOrder(['Classic', 'Modern', 'Compact'])
        ->assertSee('Choose which of 20 parts to print');

    // The page lists the parts a school can print; keep it in step with the system.
    expect(count(ReportCardTemplate::SECTIONS))->toBe(20)
        ->and(count(ReportCardTemplate::DESIGNS))->toBe(3)
        ->and(count(ReportCardTemplate::BORDERS))->toBe(4);

    $this->get('/features')->assertOk()->assertSee('id="report-card-designer"', false);
});

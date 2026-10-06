<?php

use App\Models\DemoRequest;
use App\Support\PublicSite;

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
        ->assertSee('Request a demo');
});

it('shows the team: the founder, the company and a card to join', function () {
    $this->get('/team')->assertOk()
        ->assertSee('Adrian Mugizi')
        ->assertSee('Founder, FERO TECH SMC LIMITED &amp; SchoolHub.', false)
        ->assertSee('You — Join us');

    $this->get('/about')->assertOk()->assertSee('/team');
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

<?php

beforeEach(function () {
    $this->withoutVite();
    config(['app.url' => 'https://www.schoolhubug.com']);
});

it('lets Google list the public website', function (string $path) {
    $this->get('https://www.schoolhubug.com'.$path)
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag');
})->with(['/', '/features', '/terms-and-conditions', '/robots.txt', '/sitemap.xml']);

it('keeps sign-in and registration out of search results', function (string $path) {
    $this->get('https://www.schoolhubug.com'.$path)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
})->with(['/login', '/register', '/admin/login']);

it('sends the bare domain to the www address, keeping the page', function () {
    $this->get('https://schoolhubug.com/pricing?plan=2')
        ->assertStatus(301)
        ->assertRedirect('https://www.schoolhubug.com/pricing?plan=2');
});

it('sends /index.php to the page it shows', function (string $from, string $to) {
    $this->get('https://www.schoolhubug.com'.$from)
        ->assertStatus(301)
        ->assertRedirect('https://www.schoolhubug.com'.$to);
})->with([
    ['/index.php', '/'],
    ['/index.php/features', '/features'],
]);

it('leaves other hosts and form posts alone', function () {
    $this->get('http://127.0.0.1/features')->assertOk();
    expect($this->post('https://schoolhubug.com/demo-request')->getStatusCode())->not->toBe(301);
});

<?php

beforeEach(function () {
    $this->withoutVite();
    config(['app.url' => 'https://www.schoolhubug.com']);
});

it('gives the home page a favicon Google can use, a canonical address and the publisher\'s logo', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<link rel="canonical" href="https://www.schoolhubug.com/">', false)
        ->assertSee('href="https://www.schoolhubug.com/favicon.ico"', false)
        ->assertSee('sizes="48x48" href="https://www.schoolhubug.com/images/schoolhub-icon-48.png"', false)
        ->assertSee('<link rel="apple-touch-icon"', false)
        ->assertSee('"@type": "Organization"', false)
        ->assertSee('"logo": "https://www.schoolhubug.com/images/schoolhub-icon-512.png"', false);
});

it('serves a real favicon.ico and a 48px icon', function () {
    $ico = file_get_contents(public_path('favicon.ico'));

    // ICO header: reserved 0, type 1 (icon), three sizes.
    expect(unpack('vreserved/vtype/vcount', $ico))->toBe(['reserved' => 0, 'type' => 1, 'count' => 3])
        ->and(getimagesize(public_path('images/schoolhub-icon-48.png'))[0])->toBe(48);
});

it('points search engines at a sitemap of the public pages', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: https://www.schoolhubug.com/sitemap.xml');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<loc>https://www.schoolhubug.com/</loc>', false)
        ->assertSee('<loc>https://www.schoolhubug.com/terms-and-conditions</loc>', false);
});

it('sends the old website\'s privacy page, which Google still lists, to where it lives now', function () {
    $this->get('/privacy-policy')->assertStatus(301)->assertRedirect('/terms-and-conditions#privacy');
});

it('writes the business details as valid JSON that Google can read', function () {
    $html = $this->get('/')->assertOk()->getContent();

    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $data = json_decode($m[1] ?? '', true);

    expect($data)->toBeArray()
        ->and($data['@context'])->toBe('https://schema.org')
        ->and($data['@type'])->toBe('Organization')
        ->and($data['logo'])->toBe('https://www.schoolhubug.com/images/schoolhub-icon-512.png')
        ->and($data['telephone'])->toBe('+256782863209')
        ->and($data['contactPoint']['hoursAvailable']['opens'])->toBe('08:00');
});

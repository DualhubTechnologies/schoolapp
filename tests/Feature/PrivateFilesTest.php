<?php

use App\Models\School;
use App\Models\Student;
use App\Support\PrivateFiles;
use Illuminate\Support\Facades\Storage;

afterEach(function () {
    Storage::disk(PrivateFiles::DISK)->deleteDirectory('testing');
    Storage::disk('public')->deleteDirectory('testing');
});

it('serves a private file only through a valid signed link', function () {
    Storage::disk(PrivateFiles::DISK)->put('testing/signature.png', 'signature-bytes');

    $url = PrivateFiles::url('testing/signature.png');

    expect($url)->toContain('/private-files/testing/signature.png')->toContain('signature=');

    $this->get($url)->assertOk();
    expect($this->get($url)->streamedContent())->toBe('signature-bytes');

    // The bare path, a tampered link and an expired link are all refused.
    $this->get('/private-files/testing/signature.png')->assertForbidden();
    $this->get(str_replace('signature.png', 'other.png', $url))->assertForbidden();

    $this->travel(PrivateFiles::LINK_MINUTES + 1)->minutes();
    $this->get($url)->assertForbidden();
});

it('is not reachable through the public storage address', function () {
    Storage::disk(PrivateFiles::DISK)->put('testing/photo.jpg', 'photo-bytes');

    $this->get('/storage/testing/photo.jpg')->assertNotFound();
    $this->get('/storage/../private/uploads/testing/photo.jpg')->assertNotFound();
});

it('still serves public files such as logos', function () {
    Storage::disk('public')->put('testing/logo.png', 'logo-bytes');

    $this->get('/storage/testing/logo.png')->assertOk();
});

it('gives no link for a missing file, and the avatar for a student without a photo', function () {
    expect(PrivateFiles::url(null))->toBeNull()
        ->and(PrivateFiles::url('testing/missing.png'))->toBeNull()
        ->and((new Student(['photo' => null]))->photoUrl())->toBe(asset('images/student-avatar.svg'))
        ->and((new School(['hm_signature' => null]))->signatureUrl())->toBeNull();
});

it('embeds private images in PDFs as data URIs', function () {
    Storage::disk(PrivateFiles::DISK)->put('testing/photo.png', 'png-bytes');

    expect(PrivateFiles::dataUri('testing/photo.png'))->toStartWith('data:')->toEndWith(base64_encode('png-bytes'));
});

it('moves existing photos, signatures and receipts off the public disk', function () {
    Storage::fake('public');
    Storage::fake(PrivateFiles::DISK);

    $school = School::create(['name' => 'Kampala High', 'slug' => 'kampala-high', 'email' => 'kh@example.com', 'hm_signature' => 'school-signatures/sig.png', 'logo' => 'school-logos/logo.png']);
    Storage::disk('public')->put('school-signatures/sig.png', 'sig');
    Storage::disk('public')->put('school-logos/logo.png', 'logo');

    $migration = require database_path('migrations/2026_09_30_100010_move_private_uploads_off_public_disk.php');
    $migration->up();

    expect(Storage::disk(PrivateFiles::DISK)->get('school-signatures/sig.png'))->toBe('sig')
        ->and(Storage::disk('public')->exists('school-signatures/sig.png'))->toBeFalse()
        ->and(Storage::disk('public')->exists('school-logos/logo.png'))->toBeTrue()
        ->and($school->fresh()->hm_signature)->toBe('school-signatures/sig.png');

    // Running it again changes nothing.
    $migration->up();
    expect(Storage::disk(PrivateFiles::DISK)->get('school-signatures/sig.png'))->toBe('sig');
});

<?php

use App\Support\LogoBackground;
use Illuminate\Support\Facades\Storage;

/** A 100x100 logo: a red disc on the given background, with a white dot inside the disc. */
function logoOn(array $background): string
{
    $image = imagecreatetruecolor(100, 100);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, ...$background));
    imagefilledellipse($image, 50, 50, 70, 70, (int) imagecolorallocate($image, 200, 20, 20));
    imagefilledellipse($image, 50, 50, 16, 16, (int) imagecolorallocate($image, 255, 255, 255));

    ob_start();
    imagejpeg($image, null, 95);

    return (string) ob_get_clean();
}

function alphaAt(string $png, int $x, int $y): int
{
    return (imagecolorat(imagecreatefromstring($png), $x, $y) >> 24) & 0x7F;
}

it('takes the white background off a logo and keeps the white inside it', function () {
    Storage::fake('public');
    Storage::disk('public')->put('school-logos/crest.jpg', logoOn([255, 255, 255]));

    $path = LogoBackground::clear(Storage::disk('public'), 'school-logos/crest.jpg');
    $png = Storage::disk('public')->get($path);

    expect($path)->toBe('school-logos/crest.png')
        ->and(Storage::disk('public')->exists('school-logos/crest.jpg'))->toBeFalse()
        ->and(alphaAt($png, 2, 2))->toBe(127)        // corner: see-through
        ->and(alphaAt($png, 50, 30))->toBe(0)        // the red disc stays
        ->and(alphaAt($png, 50, 50))->toBe(0);       // the white dot inside it stays
});

it('leaves a logo on a coloured background alone', function () {
    Storage::fake('public');
    Storage::disk('public')->put('school-logos/navy.jpg', logoOn([13, 31, 56]));

    expect(LogoBackground::clear(Storage::disk('public'), 'school-logos/navy.jpg'))->toBe('school-logos/navy.jpg')
        ->and(Storage::disk('public')->exists('school-logos/navy.jpg'))->toBeTrue();
});

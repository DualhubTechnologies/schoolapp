<?php

use App\Support\ImageShrinker;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;

function picture(int $width, int $height, string $type = 'png'): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 90, 200));

    ob_start();
    $type === 'png' ? imagepng($image) : imagejpeg($image);

    return (string) ob_get_clean();
}

beforeEach(function () {
    Storage::fake('public');
});

it('shrinks a large picture to fit the box, keeping its shape', function () {
    Storage::disk('public')->put('school-logos/logo.png', picture(3000, 2000));

    expect(ImageShrinker::shrink(Storage::disk('public'), 'school-logos/logo.png', 600, 600))->toBeTrue();

    [$width, $height] = getimagesizefromstring((string) Storage::disk('public')->get('school-logos/logo.png'));

    expect([$width, $height])->toBe([600, 400]);
});

it('keeps the format of the picture', function () {
    Storage::disk('public')->put('students/photo.jpg', picture(2400, 2400, 'jpeg'));

    ImageShrinker::shrink(Storage::disk('public'), 'students/photo.jpg', 800, 800);

    $info = getimagesizefromstring((string) Storage::disk('public')->get('students/photo.jpg'));

    expect($info['mime'])->toBe('image/jpeg')
        ->and([$info[0], $info[1]])->toBe([800, 800]);
});

it('leaves small pictures and non-pictures alone', function () {
    $small = picture(300, 300);
    Storage::disk('public')->put('small.png', $small);
    Storage::disk('public')->put('receipt.pdf', '%PDF-1.4 not an image');

    expect(ImageShrinker::shrink(Storage::disk('public'), 'small.png', 600, 600))->toBeFalse()
        ->and(Storage::disk('public')->get('small.png'))->toBe($small)
        ->and(ImageShrinker::shrink(Storage::disk('public'), 'receipt.pdf', 600, 600))->toBeFalse();
});

it('cuts the middle square out for logos and photos', function () {
    Storage::disk('public')->put('school-logos/wide.png', picture(1600, 900));

    ImageShrinker::shrink(Storage::disk('public'), 'school-logos/wide.png', 600, 600, square: true);

    [$width, $height] = getimagesizefromstring((string) Storage::disk('public')->get('school-logos/wide.png'));

    expect([$width, $height])->toBe([600, 600]);
});

it('stops the logo and photo fields resizing in the browser', function () {
    $logo = ImageShrinker::noBrowserResize(FileUpload::make('logo')->avatar());

    expect($logo->getImageResizeTargetWidth())->toBeNull()
        ->and($logo->getImageResizeTargetHeight())->toBeNull()
        ->and($logo->shouldAutomaticallyCropImagesToAspectRatio())->toBeFalse();
});

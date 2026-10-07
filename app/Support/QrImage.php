<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR codes for printed documents, as an SVG data URI for an <img>: sharp
 * at any size, no image files to store.
 */
class QrImage
{
    public static function svg(string $text): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
            'drawLightModules' => false,
            'svgAddXmlHeader' => false,
            'outputBase64' => true,
        ]);

        return (string) (new QRCode($options))->render($text);
    }
}

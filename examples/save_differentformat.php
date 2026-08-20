<?php
/**
 * Save to a different format than the source (JPEG → PNG here).
 *
 * The format argument is case-insensitive ('png' and 'PNG' both work).
 * Valid formats on GD: AVIF, GIF, JPEG (or JPG), PNG, WEBP.
 * Valid formats on Imagick: those plus BMP, HEIC, TIFF, and any format your
 * local ImageMagick build supports.
*/

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg');
$thumb->adaptiveResize(300, 300);
$thumb->save('test.png', 'png');


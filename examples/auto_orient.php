<?php
/**
 * Auto-orient a phone photo (or any image with EXIF rotation metadata)
 * before resizing. Common when uploading images taken on iOS/Android.
 *
 * This method is a graceful no-op when:
 *  - the source has no EXIF block (PNG, GIF, WEBP, AVIF, most edited JPEGs)
 *  - the orientation tag is 1 (already normal)
 *  - ext-exif isn't loaded (GD only — Imagick handles EXIF natively)
*/

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

// Replace with a JPEG from your phone (or any image with EXIF orientation
// metadata) to see the effect. For local testing without such an image,
// use the included fixture:
$source = __DIR__ . '/../tests/resources/exif_orientation.jpg';
// $source = '/path/to/your/phone-photo.jpg';

$thumb = new $backend($source);
$thumb->autoOrient()
      ->resize(400, 0)
      ->sharpen(40)
      ->show();

// Inspect the dimensions before/after:
// var_dump($thumb->getCurrentDimensions()); // [width => 200, height => 400]
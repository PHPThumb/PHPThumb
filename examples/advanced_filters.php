<?php
/**
 * Showcases every chainable filter wrapper in one run. Produces a single
 * preview that demonstrates grayscale, blur, pixelate, edge-detect, and
 * emboss chained together.
*/

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');

// Resize first so the filter effects are cheaper and more visible on the smaller preview.
$thumb->resize(400, 0)
      ->sharpen(40);

// Chain a sequence of filter wrappers.
$thumb->grayscale()
      ->contrast(15)
      ->blur(2);

// Try variations — uncomment one at a time:
// $thumb->pixelate(15);                 // cubist preview
// $thumb->edgeDetect()->contrast(-20);  // blueprint-style poster
// $thumb->emboss();                     // raised-relief effect

$thumb->border(6, '#000000')
      ->show();
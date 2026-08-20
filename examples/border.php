<?php
/**
 * Add a solid-color frame around an image.
 *
 * The frame grows the canvas by 2 × thickness in both axes. The original
 * pixel data is preserved untouched in the center.
*/

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');
$thumb->resize(400, 0)
      ->sharpen(40)
      ->border(12, '#000000')          // 12px black frame
      ->show();

// Try these variations:
// $thumb->border(4,  '#FFFFFF');      // 4px white frame
// $thumb->border(20, '#FF8800');      // 20px orange frame
// $thumb->border(8,  [200, 200, 200]);// 8px light-grey via RGB array

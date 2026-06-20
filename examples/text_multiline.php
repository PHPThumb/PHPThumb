<?php
/**
 * Multi-line caption with a background pill. Useful for "uploaded by X on
 * Y" photo credits.
 *
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$ttf = null;
foreach ([
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/Library/Fonts/Arial.ttf',
    'C:\\Windows\\Fonts\\arial.ttf',
] as $candidate) {
    if (file_exists($candidate)) {
        $ttf = $candidate;
        break;
    }
}

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');
$thumb->resize(600, 0)
      ->sharpen(40)
      ->text("PHPThumb library\n© 2.5", 'bottom-right', [
          'font'       => $ttf,
          'size'       => 16,
          'color'      => '#FFFFFF',
          'offsetX'    => 20,
          'offsetY'    => 20,
          'align'      => 'right',
          'background' => ['enabled' => true, 'color' => '#000000', 'padding' => 8, 'alpha' => 60],
      ])
      ->show();
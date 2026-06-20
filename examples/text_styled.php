<?php
/**
 * Show off every text() option: TTF font, custom color, drop shadow,
 * stroke, and a background pill. A real-world "watermark-style" caption.
 *
 * Requires a TTF font. On Linux, install fonts-dejavu:
 *   sudo apt install fonts-dejavu
 * On macOS, the system has Arial at /Library/Fonts/Arial.ttf.
 *
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

// Pick the first TTF font we find.
$ttf = null;
foreach ([
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
    '/Library/Fonts/Arial Bold.ttf',
    '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
    'C:\\Windows\\Fonts\\arialbd.ttf',
] as $candidate) {
    if (file_exists($candidate)) {
        $ttf = $candidate;
        break;
    }
}

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');
$thumb->resize(600, 0)
      ->sharpen(40)
      ->text('PHPThumb 2.5', 'center', [
          'font'   => $ttf,
          'size'   => 48,
          'color'  => '#FF3300',
          'angle'  => -15,
          'shadow' => ['enabled' => true, 'color' => '#000000', 'offsetX' => 3, 'offsetY' => 3],
          'stroke' => ['enabled' => true, 'color' => '#FFFFFF', 'width' => 2],
      ])
      ->show();

// Simpler version without a TTF (built-in font fallback):
//
// $thumb->text('SALE', 'center', [
//     'size'  => 5,
//     'color' => '#FF3300',
// ]);
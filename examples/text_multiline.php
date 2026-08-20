<?php
/**
 * Multi-line caption with a background pill. Useful for "uploaded by X on
 * Y" photo credits or two-line title overlays.
 *
 * 'align' => 'right' right-aligns both lines within the text block.
 * 'lineHeight' => 1.4 gives ~40% visual gap between lines (default is
 * 1.3 on Imagick, 1.5 on GD — set explicitly for cross-backend parity).
*/

require_once __DIR__ . '/../vendor/autoload.php';

$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');

// On systems with fontconfig, no explicit 'font' is needed — GD/Imagick
// resolve a sensible default. To force a specific TTF, pass:
//   'font' => '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
$thumb->resize(600, 0)
	->sharpen(40)
	->text("PHPThumb library\n© 2.5", 'bottom-right', [
		'size'       => 16,
		'color'      => '#FFFFFF',
		'offsetX'    => 20,
		'offsetY'    => 20,
		'align'      => 'right',
		'lineHeight' => 1.4,
		'background' => [
			'enabled' => true,
			'color'   => '#000000',
			'padding' => 8,
			'alpha'   => 60,
		],
	])
	->show();
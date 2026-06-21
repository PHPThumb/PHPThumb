<?php
/**
 * Render a simple bottom-right caption.
 *
 * On systems with fontconfig installed (most Linux/macOS), the backend
 * automatically resolves a usable TTF font via 'fc-match' — no need to
 * pass 'font' explicitly. On systems without fontconfig, this falls
 * back to the built-in font (which ignores size).
 *
 * @license MIT — see LICENSE in the repo root.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');

// Size 14 is in pixels for TTF fonts. If fontconfig is unavailable and
// the backend falls back to the built-in font, size is ignored.
$thumb->resize(500, 0)
	->sharpen(40)
	->text('PHPThumb 2.5', 'bottom-right', [
		'size'   => 14,
		'color'  => '#FFFFFF',
		'shadow' => ['enabled' => true, 'offsetX' => 1, 'offsetY' => 1],
	])
	->show();
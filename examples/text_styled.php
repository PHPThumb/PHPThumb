<?php
/**
 * Show off every text() option: TTF font, custom color, drop shadow,
 * stroke outline, rotation, and a background pill. A real-world
 * "watermark-style" caption.
 *
 * Requires a TTF font. With fontconfig installed (most Linux/macOS),
 * no explicit 'font' is needed — the backend resolves automatically.
 * To force a specific font on systems without fontconfig, pass an
 * explicit path:
 *
 *   'font' => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'
 *
 * If fontconfig is unavailable AND no system font can be found, GD
 * falls back to the built-in font, which ignores size, angle, shadow,
 * and stroke — yielding a small, unrotated, unstyled caption.
*/

require_once __DIR__ . '/../vendor/autoload.php';

$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');

$thumb->resize(600, 0)
	->sharpen(40)
	->text('PHPThumb 2.5', 'center', [
		// 'font' is optional — backend resolves via fontconfig if
		// omitted. Pass an explicit path to override.
		'size'   => 48,
		'color'  => '#FF3300',
		'angle'  => -15,    // 15° clockwise (same direction on both backends)
		'shadow' => ['enabled' => true, 'color' => '#000000', 'offsetX' => 3, 'offsetY' => 3],
		'stroke' => ['enabled' => true, 'color' => '#FFFFFF', 'width' => 2],
	])
	->show();

// To force a specific TTF (e.g. on systems without fontconfig):
//
// $ttf = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
// $thumb->text('PHPThumb 2.5', 'center', [
//     'font'   => $ttf,
//     'size'   => 48,
//     'color'  => '#FF3300',
//     'angle'  => -15,
//     'shadow' => ['enabled' => true, 'color' => '#000000', 'offsetX' => 3, 'offsetY' => 3],
//     'stroke' => ['enabled' => true, 'color' => '#FFFFFF', 'width' => 2],
// ]);
<?php
/**
 * Demonstrate rotation parity across backends.
 *
 * 'angle' => -15 produces 15° clockwise rotation on BOTH GD and
 * Imagick (the backends use opposite native conventions — Imagick
 * negates the angle internally so users see consistent behavior).
*/

require_once __DIR__ . '/../vendor/autoload.php';

$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');

$thumb->resize(500, 0)
	->sharpen(40)
	->text('15° clockwise', 'center', [
		'size'   => 36,
		'color'  => '#FFFFFF',
		'angle'  => -15,
		'shadow' => ['enabled' => true, 'color' => '#000000', 'offsetX' => 2, 'offsetY' => 2],
	])
	->show();

// To rotate 15° counter-clockwise, use a positive angle:
//   'angle' => 15,
<?php
/**
 * Apply a glossy reflection beneath the image, then adaptive-resize to a square.
*/

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
// The Reflection plugin auto-dispatches to the right backend internally.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg', [], [
	new PHPThumb\Plugins\Reflection(40, 40, 80, true, '#a4a4a4')
]);

$thumb->adaptiveResize(250, 250);
$thumb->show();
<?php
/**
 * Overlay a scaled, semi-transparent watermark on the image.
 *
 * The Watermark constructor (PHPThumb 2.4+) type-hints GD|Imagick for $wm.
 // The fluent return value of resizePercent() is itself a valid instance,
 // which is why this passes it in directly.
 *
 * Note: the watermark source must be the same backend as the base image.
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @author Oleg Sherbakov <holdmann@yandex.ru>
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$watermark = new $backend(__DIR__ .'/../tests/resources/test.jpg');

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg', [], [
	new PHPThumb\Plugins\Watermark($watermark->resizePercent(20), 'center', 50, 0, 0)
]);

$thumb->show();
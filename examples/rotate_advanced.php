<?php
/**
 * Rotate an image by an arbitrary number of degrees.
 *
 * Note: on the GD backend, imagerotate() fills new corner areas with black,
 // producing visible triangular artifacts. The Imagick backend handles this
 // cleanly via Imagick::rotateImage() with an explicit fill color.
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg');
$thumb->rotateImageNDegrees(180);
$thumb->show();

<?php
/**
 * Crop a rectangular region centered on the image.
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg');
$thumb->cropFromCenter(200, 100);
$thumb->show();

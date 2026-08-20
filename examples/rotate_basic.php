<?php
/**
 * Rotate an image 90° clockwise or counter-clockwise.
*/

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg');
$thumb->rotateImage('CW');
$thumb->show();

// or:
// $thumb->rotateImage('CCW');

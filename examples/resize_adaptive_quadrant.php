<?php
/**
 * Adaptive resize with the crop anchored to a specific quadrant (T/B/L/R/C).
 *
*/

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg');
$thumb->adaptiveResizeQuadrant(300, 300, 'C');

$thumb->show();

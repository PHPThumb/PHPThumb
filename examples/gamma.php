<?php
/**
 * Brighten an image with gamma correction.
 *
 * gamma(1.5) lifts midtones without clipping highlights as harshly
 * as a flat brightness boost.
 *
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');
$thumb->gamma(1.5);
$thumb->show();

// Try gamma(0.7) to darken, or gamma(2.0) for stronger brightening.
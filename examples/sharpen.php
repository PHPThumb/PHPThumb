<?php
/**
 * Resize an image and apply sharpening — a typical thumbnail pipeline.
 *
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');
$thumb->resize(400, 0);
$thumb->sharpen(60);    // crisp thumbnails, range 0–100
$thumb->show();
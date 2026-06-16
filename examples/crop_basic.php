<?php
/**
 * Crop an image from explicit x/y/width/height coordinates.
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
// Both classes share the same fluent API, so the rest of the script is
// identical regardless of which one you choose.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg');
$thumb->crop(100, 100, 300, 200);
$thumb->show();
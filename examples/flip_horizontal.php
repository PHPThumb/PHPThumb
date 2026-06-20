<?php
/**
 * Mirror an image horizontally (left ↔ right).
 *
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');
$thumb->flip('horizontal');
$thumb->show();

// or $thumb->flip('h');   (alias)
// or $thumb->flip('lr');  (alias)
<?php
/**
 * Adaptive resize: cover a target box, then crop the overflow from the center.
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg');
$thumb->adaptiveResize(175, 175);
$thumb->show();
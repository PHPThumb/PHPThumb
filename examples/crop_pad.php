<?php
/**
 * Pad an image onto a colored canvas.
 *
 * @author Marcel Domke <contact@marcel-domke.de>
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg');
$thumb->pad(1024, 350, [192, 212, 45]);

$thumb->show();

<?php
/**
 * A polaroid-style thick white border, common in photo galleries.
 *
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');
$thumb->adaptiveResize(400, 400)   // square crop
      ->sharpen(50)
      ->border(40, '#FFFFFF')       // 40px white frame
      ->show();

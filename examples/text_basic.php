<?php
/**
 * Render a simple bottom-right caption. Works on both backends without
 * requiring a TTF font — falls back to GD's built-in font or Imagick's
 * built-in font (Helvetica).
 *
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ . '/../tests/resources/test.jpg');
$thumb->resize(500, 0)
      ->sharpen(40)
      ->text('PHPThumb 2.5', 'bottom-right', [
          'size'   => 14,
          'color'  => '#FFFFFF',
          'shadow' => ['enabled' => true, 'offsetX' => 1, 'offsetY' => 1],
      ])
      ->show();
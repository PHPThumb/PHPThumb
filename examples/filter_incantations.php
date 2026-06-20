<?php
/**
 * Each filter wrapper demonstrated in isolation. Useful for picking the
 * right strength value before composing a pipeline.
 *
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$src = __DIR__ . '/../tests/resources/test.jpg';

// Grayscale
(new $backend($src))->resize(300, 0)->grayscale()->show();

// Brightness boost
(new $backend($src))->resize(300, 0)->brightness(50)->show();

// Contrast pump
(new $backend($src))->resize(300, 0)->contrast(25)->show();

// Blur
(new $backend($src))->resize(300, 0)->blur(5)->show();

// Pixelate
(new $backend($src))->resize(300, 0)->pixelate(20)->show();

// Edge detect
(new $backend($src))->resize(300, 0)->edgeDetect()->show();

// Emboss
(new $backend($src))->resize(300, 0)->emboss()->show();

// Smooth
(new $backend($src))->resize(300, 0)->smooth(5)->show();
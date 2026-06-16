<?php
/**
 * Apply a built-in image filter (colorize, grayscale, blur, …).
 *
 * NOTE: GD-only. PHPThumb\GD::imageFilter() accepts the IMG_FILTER_*
 * constants from GD's imagefilter(). PHPThumb\Imagick::imageFilter() wraps
 * Imagick::filter(), which takes a different constant family (Imagick::FILTER_*,
 * resize-style convolution kernels) — the two are not interchangeable.
 *
 * To colorize on the Imagick backend, call Imagick's native method directly:
 *
 *     $imagick = $thumb->getOldImage();
 *     $color = new \ImagickPixel('rgb(160, 20, 20)');
 *     $imagick->colorizeImage($color, 1.0);
 *     $thumb->setOldImage($imagick);
 *     $thumb->show();
 *
 * See the wiki "Imagick API" page for the full IMG_FILTER_* → Imagick mapping.
 *
 * @author Marcel Domke <contact@marcel-domke.de>
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

$backend = PHPThumb\GD::class; // Pinned: see note above.

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg');
// for more informations visit http://www.php.net/manual/de/function.imagefilter.php
$thumb->imageFilter(IMG_FILTER_COLORIZE, 160, 20, 20);
// or e.g. $thumb->imageFilter(IMG_FILTER_GRAYSCALE);

$thumb->show();
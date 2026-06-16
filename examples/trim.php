<?php
/**
 * Trim solid-color borders from an image.
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @author Oleg Sherbakov <holdmann@yandex.ru>
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

// Pick your backend: PHPThumb\GD (default) or PHPThumb\Imagick.
// The Trim plugin auto-dispatches to the right backend internally.
$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend(__DIR__ .'/../tests/resources/test.jpg', [], [
	new PHPThumb\Plugins\Trim([255, 255, 255], 'TBLR')
]);

$thumb->show();
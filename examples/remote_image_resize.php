<?php
/**
 * Load an image directly from a URL and resize it.
 *
 * Both backends accept URLs as the constructor argument; allow_url_fopen
 * must be enabled in php.ini. Use raw.githubusercontent.com (not
 * github.com/<repo>/blob/…) for direct asset access — the latter returns
 * an HTML page.
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

$backend = PHPThumb\GD::class;
// $backend = PHPThumb\Imagick::class;

$thumb = new $backend('https://raw.githubusercontent.com/PHPThumb/PHPThumb/master/tests/resources/test.jpg');
$thumb->resize(200, 200);
$thumb->show();

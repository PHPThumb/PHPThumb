<?php
/**
 * Load an image from raw bytes (e.g. a database BLOB or file_get_contents()
 * result), manipulate it, and emit both a debug view and a data URI.
 *
 * NOTE: GD-only. PHPThumb\GD auto-detects the STRING format via getimagesize().
 * PHPThumb\Imagick requires a file path or URL — to use raw bytes with the
 * Imagick backend, write them to a temp file first:
 *
 *     $tmp = tempnam(sys_get_temp_dir(), 'thumb');
 *     file_put_contents($tmp, $bytes);
 *     $thumb = new PHPThumb\Imagick($tmp);
 *     // ... do work ...
 *     @unlink($tmp);
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @license MIT — see LICENSE in the repo root.
 */

require_once '../vendor/autoload.php';

$backend = PHPThumb\GD::class; // Pinned: see note above.

$fileData = file_get_contents(__DIR__ . '/../tests/resources/test.jpg');

$thumb = new $backend($fileData);
$thumb->crop(100, 100, 300, 200);

// $imageAsString will contain the image data suitable for saving in a database.
$imageAsString = $thumb->getImageAsString();

?>
<h2>Here's the Image Data:</h2>
<strong>Note:</strong> This should be a bunch of gibberish<br />
<div style="overflow: auto; width: 500px; height: 400px; border: 1px solid #e4e4e4; padding: 5px;"><?php echo htmlentities($imageAsString); ?></div>

<h2>Here's that data as an image:</h2>
<img src="data:image/png;base64,<?php echo base64_encode($imageAsString); ?>" />
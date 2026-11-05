<?php
/**
 * Generates a JPEG fixture with an EXIF orientation tag (value 6 = "rotate
 * 90 CW") for testing PHPThumb's autoOrient(). Run this once if the
 * fixture file is missing or lacks the tag.
 *
 * Usage:
 *   php tests/resources/generate_exif_fixture.php
 *
 * The EXIF block is written by hand (a minimal APP1/TIFF structure), so
 * neither ext-imagick nor exiftool is required. The previous version fell
 * back to writing a plain JPEG with no EXIF at all, which produced a
 * fixture that could not exercise the orientation code paths.
 */

declare(strict_types=1);

require_once __DIR__ . '/exif_writer.php';

$out = __DIR__ . '/exif_orientation.jpg';

// Only keep an existing fixture if it actually carries the tag we need.
if (file_exists($out) && ExifFixtureWriter::orientation($out) === 6)
{
	echo "Fixture already exists: $out\n";
	exit(0);
}

try
{
	ExifFixtureWriter::write($out, 6);
}
catch (Throwable $e)
{
	fwrite(STDERR, 'Could not generate the fixture: ' . $e->getMessage() . "\n");
	exit(1);
}

$orientation = ExifFixtureWriter::orientation($out);

echo "Generated fixture: $out (orientation=" . var_export($orientation, true) . ")\n";

if ($orientation !== 6)
{
	fwrite(STDERR, "Warning: the fixture does not report Orientation = 6.\n");
	exit(1);
}
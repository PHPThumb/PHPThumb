<?php

/**
 * Minimal EXIF (APP1/TIFF) writer used to build test fixtures that carry
 * an Orientation tag.
 *
 * The structure written here is the minimal, standard one: a JPEG APP1
 * segment containing "Exif\0\0", a little-endian TIFF header, and an IFD0
 * with a single Orientation (0x0112) entry. That is enough for PHP's
 * exif_read_data() to report the orientation, and it needs neither
 * ext-imagick nor exiftool.
 */

declare(strict_types=1);

final class ExifFixtureWriter
{
	/**
	 * Builds the raw APP1 segment (marker + length + payload) that carries
	 * the given orientation.
	 */
	public static function buildApp1Segment(int $orientation): string
	{
		// ---- TIFF header (little-endian "II") ----
		$tiff  = "II";
		$tiff .= pack('v', 42);          // magic number 0x002A
		$tiff .= pack('V', 8);           // offset of IFD0 from the TIFF header

		// ---- IFD0 with a single entry ----
		$ifd  = pack('v', 1);            // number of directory entries
		$ifd .= pack('v', 0x0112);       // tag: Orientation
		$ifd .= pack('v', 3);            // type: SHORT
		$ifd .= pack('V', 1);            // value count
		$ifd .= pack('v', $orientation) . "\x00\x00"; // inline value, padded to 4 bytes
		$ifd .= pack('V', 0);            // offset of the next IFD (none)

		$payload = "Exif\x00\x00" . $tiff . $ifd;

		return "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;
	}

	/**
	 * Inserts the EXIF APP1 segment into an existing JPEG byte string,
	 * immediately after the SOI marker.
	 */
	public static function inject(string $jpegBytes, int $orientation): string
	{
		if (substr($jpegBytes, 0, 2) !== "\xFF\xD8")
		{
			throw new InvalidArgumentException('Not a JPEG file: missing the SOI marker.');
		}

		return "\xFF\xD8" . self::buildApp1Segment($orientation) . substr($jpegBytes, 2);
	}

	/**
	 * Builds a 400x200 test image (red left / blue right / white top
	 * stripe) and writes it to $path with the given EXIF orientation.
	 */
	public static function write(string $path, int $orientation = 6, int $width = 400, int $height = 200): void
	{
		if (!function_exists('imagecreatetruecolor'))
		{
			throw new RuntimeException('ext-gd is required to build the EXIF fixture.');
		}

		$im = imagecreatetruecolor($width, $height);

		if ($im === false)
		{
			throw new RuntimeException('imagecreatetruecolor() failed.');
		}

		$red   = imagecolorallocate($im, 220, 50, 50);
		$blue  = imagecolorallocate($im, 50, 50, 220);
		$white = imagecolorallocate($im, 255, 255, 255);

		imagefill($im, 0, 0, $white);
		imagefilledrectangle($im, 0, 20, intdiv($width, 2) - 1, $height - 1, $red);
		imagefilledrectangle($im, intdiv($width, 2), 20, $width - 1, $height - 1, $blue);

		$tmp = tempnam(sys_get_temp_dir(), 'exif_raw_');

		if ($tmp === false)
		{
			throw new RuntimeException('tempnam() failed.');
		}

		imagejpeg($im, $tmp, 95);

		$raw = file_get_contents($tmp);
		@unlink($tmp);

		if ($raw === false)
		{
			throw new RuntimeException('Could not read the temporary JPEG.');
		}

		file_put_contents($path, self::inject($raw, $orientation));
	}

	/**
	 * Returns the EXIF orientation of a file, or null when it has none.
	 */
	public static function orientation(string $path): ?int
	{
		if (!function_exists('exif_read_data'))
		{
			return null;
		}

		$exif = @exif_read_data($path);

		if (!is_array($exif) || !isset($exif['Orientation']))
		{
			return null;
		}

		return (int) $exif['Orientation'];
	}
}
<?php
namespace PHPThumb\Tests;

use Exception;
use InvalidArgumentException;
use PHPThumb\GD;
use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

/**
 * Both backends accept the raw bytes of an image as their source, not just
 * a file path or a URL.
 *
 * A source is treated as raw bytes when it is neither an existing file nor
 * a valid URL, and it looks like image data — either it carries one of the
 * known image signatures, or it is binary (contains a null byte, or is not
 * valid UTF-8).
 *
 * A path that does not exist satisfies neither test, so a typo still
 * produces the familiar "Image file not found" error rather than a
 * confusing format error.
 */
class RawBytesTest extends TestCase
{
	protected function bytes(string $fixture): string
	{
		$path = __DIR__ . '/../../resources/' . $fixture;

		self::assertFileExists($path);

		$data = file_get_contents($path);

		self::assertIsString($data);
		self::assertNotEmpty($data);

		return $data;
	}

	// ---------------- GD ----------------

	public function testGdAcceptsRawJpegBytes(): void
	{
		$thumb = new GD($this->bytes('test.jpg'));

		self::assertSame('JPEG', $thumb->getFormat());
		self::assertSame(500, $thumb->getCurrentDimensions()['width']);
		self::assertSame(375, $thumb->getCurrentDimensions()['height']);
	}

	public function testGdAcceptsRawPngBytes(): void
	{
		$thumb = new GD($this->bytes('test.png'));

		self::assertSame('PNG', $thumb->getFormat());
		self::assertSame(500, $thumb->getCurrentDimensions()['width']);
	}

	public function testGdAcceptsRawGifBytes(): void
	{
		$thumb = new GD($this->bytes('test.gif'));

		self::assertSame('GIF', $thumb->getFormat());
	}

	public function testGdAcceptsRawWebpBytes(): void
	{
		if (!function_exists('imagecreatefromwebp')) {
			$this->markTestSkipped('ext-gd was built without WebP support');
		}

		$thumb = new GD($this->bytes('test.webp'));

		self::assertSame('WEBP', $thumb->getFormat());
	}

	public function testGdRawBytesCanBeManipulated(): void
	{
		$thumb = new GD($this->bytes('test.jpg'));
		$thumb->resize(200, 0);

		self::assertSame(200, $thumb->getCurrentDimensions()['width']);
	}

	public function testGdRawBytesCanBeSaved(): void
	{
		$tempFile = __DIR__ . '/../../resources/gd_raw_bytes.png';

		try {
			$thumb = new GD($this->bytes('test.jpg'));
			$thumb->resize(120, 0);
			$thumb->save($tempFile, 'PNG');

			self::assertFileExists($tempFile);

			$reloaded = new GD($tempFile);
			self::assertSame(120, $reloaded->getCurrentDimensions()['width']);
		} finally {
			if (is_file($tempFile)) {
				unlink($tempFile);
			}
		}
	}

	/**
	 * The output format must follow the *source* format, not a fixed
	 * default: raw JPEG bytes are emitted as JPEG.
	 */
	public function testGdRawBytesProduceTheRightOutputFormat(): void
	{
		$thumb = new GD($this->bytes('test.jpg'));

		$data = $thumb->getImageAsString();

		self::assertNotEmpty($data);
		self::assertSame("\xFF\xD8\xFF", substr($data, 0, 3));
	}

	// ---------------- Imagick ----------------

	public function testImagickAcceptsRawJpegBytes(): void
	{
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('ext-imagick is not available');
		}

		$thumb = new Imagick($this->bytes('test.jpg'));

		self::assertSame('JPEG', $thumb->getFormat());
		self::assertSame(500, $thumb->getCurrentDimensions()['width']);
		self::assertSame(375, $thumb->getCurrentDimensions()['height']);
	}

	public function testImagickAcceptsRawPngBytes(): void
	{
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('ext-imagick is not available');
		}

		$thumb = new Imagick($this->bytes('test.png'));

		self::assertSame('PNG', $thumb->getFormat());
	}

	public function testImagickRawBytesCanBeSaved(): void
	{
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('ext-imagick is not available');
		}

		$tempFile = __DIR__ . '/../../resources/imagick_raw_bytes.png';

		try {
			$thumb = new Imagick($this->bytes('test.jpg'));
			$thumb->resize(120, 0);
			$thumb->save($tempFile, 'PNG');

			self::assertFileExists($tempFile);

			$reloaded = new Imagick($tempFile);
			self::assertSame(120, $reloaded->getCurrentDimensions()['width']);
		} finally {
			if (is_file($tempFile)) {
				unlink($tempFile);
			}
		}
	}

	// ---------------- error handling ----------------

	/**
	 * A path that does not exist must still be reported as a missing file,
	 * not as "unrecognised image data".
	 */
	public function testAMissingFileIsStillReportedAsMissing(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Image file not found');

		new GD('nosuchimage.jpg');
	}

	public function testAMissingFileIsStillReportedAsMissingOnImagick(): void
	{
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('ext-imagick is not available');
		}

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Image file not found');

		new Imagick('nosuchimage.jpg');
	}

	public function testBinaryDataThatIsNotAnImageIsRejected(): void
	{
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Could not determine');

		new GD("\x00\x01\x02\x03\x04 not an image at all \x00");
	}
}
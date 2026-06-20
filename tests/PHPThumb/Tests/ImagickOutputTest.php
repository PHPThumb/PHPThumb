<?php
namespace PHPThumb\Tests;

use Imagick as ImagickExt;
use InvalidArgumentException;
use PHPThumb\Imagick;
use RuntimeException;
use PHPUnit\Framework\TestCase;

class ImagickOutputTest extends TestCase
{
	protected Imagick $thumb;

	protected function setUp(): void
	{
		if (!extension_loaded('imagick'))
		{
			$this->markTestSkipped('ext-imagick is not available');
		}

		$this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
	}

	public function testSaveJpeg()
	{
		$tempFile = __DIR__ . '/../../resources/imagick_output.jpg';
		$this->thumb->save($tempFile);

		self::assertFileExists($tempFile);

		$info = getimagesize($tempFile);
		self::assertSame(IMAGETYPE_JPEG, $info[2]);

		unlink($tempFile);
	}

	public function testSavePng()
	{
		$tempFile = __DIR__ . '/../../resources/imagick_output.png';
		$this->thumb->save($tempFile, 'PNG');

		self::assertFileExists($tempFile);

		$info = getimagesize($tempFile);
		self::assertSame(IMAGETYPE_PNG, $info[2]);

		unlink($tempFile);
	}

	public function testSaveWebp()
	{
		$tempFile = __DIR__ . '/../../resources/imagick_output.webp';
		$this->thumb->save($tempFile, 'WEBP');

		self::assertFileExists($tempFile);

		$info = getimagesize($tempFile);
		self::assertSame(IMAGETYPE_WEBP, $info[2]);

		unlink($tempFile);
	}

	public function testSaveGif()
	{
		$tempFile = __DIR__ . '/../../resources/imagick_output.gif';
		$this->thumb->save($tempFile, 'GIF');

		self::assertFileExists($tempFile);

		$info = getimagesize($tempFile);
		self::assertSame(IMAGETYPE_GIF, $info[2]);

		unlink($tempFile);
	}

	public function testSaveUnwritableDirectory()
	{
		$this->expectException(RuntimeException::class);
		$this->thumb->save('/this/path/does/not/exist/output.jpg');
	}

	public function testGetImageAsString()
	{
		$this->thumb->resize(100, 100);

		$imageData = $this->thumb->getImageAsString();

		self::assertNotEmpty($imageData);
		self::assertIsString($imageData);
		// JPEG starts with the magic bytes FF D8 FF
		self::assertSame("\xFF\xD8\xFF", substr($imageData, 0, 3));
	}

	public function testSaveWithQuality()
	{
		$tempFile = __DIR__ . '/../../resources/imagick_output_quality.jpg';

		$this->thumb->setOptions(['jpegQuality' => 50]);
		$this->thumb->resize(100, 100);
		$this->thumb->save($tempFile, 'JPEG');

		self::assertFileExists($tempFile);

		// Verify the saved file is a valid JPEG of expected dimensions
		$reloaded = new ImagickExt($tempFile);
		self::assertEquals(100, $reloaded->getImageWidth());
		self::assertEqualsWithDelta(75, $reloaded->getImageHeight(), 1);

		unlink($tempFile);
	}

	public function testSavePreservesFormat()
	{
		$tempFile = __DIR__ . '/../../resources/imagick_preserve.jpg';

		$this->thumb->resize(100, 100);
		$this->thumb->save($tempFile);

		$reloaded = new Imagick($tempFile);
		self::assertSame('JPEG', $reloaded->getFormat());

		unlink($tempFile);
	}

	public function testSaveCreatesValidImage()
	{
		$tempFile = __DIR__ . '/../../resources/imagick_roundtrip.png';

		$this->thumb->save($tempFile, 'PNG');

		$reloaded = new Imagick($tempFile);
		self::assertSame(500, $reloaded->getCurrentDimensions()['width']);
		self::assertSame(375, $reloaded->getCurrentDimensions()['height']);

		unlink($tempFile);
	}
}
<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\GD;
use RuntimeException;
use PHPUnit\Framework\TestCase;

class GDOutputTest extends TestCase
{
	protected GD $thumb;

	protected function setUp(): void
	{
		$this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
	}

	public function testSaveJpeg(): void
	{
		$tempFile = __DIR__ . '/../../resources/gd_output.jpg';

		try {
			$this->thumb->save($tempFile);

			self::assertFileExists($tempFile);

			$info = getimagesize($tempFile);
			self::assertSame(IMAGETYPE_JPEG, $info[2]);
			self::assertSame(500, $info[0]);
			self::assertSame(375, $info[1]);
		} finally {
			if (is_file($tempFile)) {
				unlink($tempFile);
			}
		}
	}

	public function testSavePng(): void
	{
		$tempFile = __DIR__ . '/../../resources/gd_output.png';

		try {
			$this->thumb->save($tempFile, 'PNG');

			self::assertFileExists($tempFile);

			$info = getimagesize($tempFile);
			self::assertSame(IMAGETYPE_PNG, $info[2]);
		} finally {
			if (is_file($tempFile)) {
				unlink($tempFile);
			}
		}
	}

	public function testSaveWebp(): void
	{
		if (!function_exists('imagewebp')) {
			$this->markTestSkipped('ext-gd was built without WebP support');
		}

		$tempFile = __DIR__ . '/../../resources/gd_output.webp';

		try {
			$this->thumb->save($tempFile, 'WEBP');

			self::assertFileExists($tempFile);

			$info = getimagesize($tempFile);
			self::assertSame(IMAGETYPE_WEBP, $info[2]);
		} finally {
			if (is_file($tempFile)) {
				unlink($tempFile);
			}
		}
	}

	public function testSaveGif(): void
	{
		$tempFile = __DIR__ . '/../../resources/gd_output.gif';

		try {
			$this->thumb->save($tempFile, 'GIF');

			self::assertFileExists($tempFile);

			$info = getimagesize($tempFile);
			self::assertSame(IMAGETYPE_GIF, $info[2]);
		} finally {
			if (is_file($tempFile)) {
				unlink($tempFile);
			}
		}
	}

	public function testSaveRejectsAnUnknownFormat(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->save(__DIR__ . '/../../resources/gd_output.tga', 'TGA');
	}

	public function testSaveUnwritableDirectory(): void
	{
		$this->expectException(RuntimeException::class);
		$this->thumb->save('/this/path/does/not/exist/output.jpg');
	}

	public function testSavePreservesTheSourceFormatByDefault(): void
	{
		$tempFile = __DIR__ . '/../../resources/gd_preserve.jpg';

		try {
			$this->thumb->save($tempFile);

			$reloaded = new GD($tempFile);
			self::assertSame('JPEG', $reloaded->getFormat());
			self::assertSame(500, $reloaded->getCurrentDimensions()['width']);
		} finally {
			if (is_file($tempFile)) {
				unlink($tempFile);
			}
		}
	}

	public function testGetImageAsStringReturnsJpegBytes(): void
	{
		$data = $this->thumb->getImageAsString();

		self::assertIsString($data);
		self::assertNotEmpty($data);
		// JPEG starts with the magic bytes FF D8 FF
		self::assertSame("\xFF\xD8\xFF", substr($data, 0, 3));
	}

	public function testShowWritesTheImageToTheOutputBuffer(): void
	{
		ob_start();
		$this->thumb->show(true);
		$output = ob_get_clean();

		self::assertNotEmpty($output);
		self::assertSame("\xFF\xD8\xFF", substr($output, 0, 3));
	}

	public function testLowerQualityProducesASmallerJpeg(): void
	{
		$low  = __DIR__ . '/../../resources/gd_quality_low.jpg';
		$high = __DIR__ . '/../../resources/gd_quality_high.jpg';

		try {
			$thumbLow = new GD(__DIR__ . '/../../resources/test.jpg');
			$thumbLow->setOptions(['jpegQuality' => 10]);
			$thumbLow->save($low, 'JPEG');

			$thumbHigh = new GD(__DIR__ . '/../../resources/test.jpg');
			$thumbHigh->setOptions(['jpegQuality' => 95]);
			$thumbHigh->save($high, 'JPEG');

			self::assertLessThan(
				filesize($high),
				filesize($low),
				'jpegQuality=10 must produce a smaller file than jpegQuality=95'
				);
		} finally {
			foreach ([$low, $high] as $file) {
				if (is_file($file)) {
					unlink($file);
				}
			}
		}
	}

	public function testQualityPresetOffFallsBackToTheCodecDefault(): void
	{
		// 'off' maps to a null quality, which GD signals as -1 ("use the
		// codec default"). The file must still be a valid JPEG.
		$tempFile = __DIR__ . '/../../resources/gd_quality_off.jpg';

		try {
			$this->thumb->setQualityPreset('off');
			$this->thumb->save($tempFile, 'JPEG');

			self::assertFileExists($tempFile);

			$info = getimagesize($tempFile);
			self::assertSame(IMAGETYPE_JPEG, $info[2]);
		} finally {
			if (is_file($tempFile)) {
				unlink($tempFile);
			}
		}
	}
}
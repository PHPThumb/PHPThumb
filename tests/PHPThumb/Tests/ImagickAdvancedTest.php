<?php
namespace PHPThumb\Tests;

use Imagick as ImagickExt;
use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickAdvancedTest extends TestCase
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

	/**
	 * @dataProvider formatConversionProvider
	 */
	public function testFormatConversion(string $outputFormat): void
	{
		$ext = strtolower($outputFormat === 'JPEG' ? 'jpg' : $outputFormat);
		$tempFile = __DIR__ . '/../../resources/imagick_convert.' . $ext;

		$this->thumb->resize(100, 100);
		$this->thumb->save($tempFile, $outputFormat);

		self::assertFileExists($tempFile);

		$reloaded = new Imagick($tempFile);
		self::assertSame($outputFormat, $reloaded->getFormat());

		unlink($tempFile);
	}

	public static function formatConversionProvider(): array
	{
		return [
			'to JPEG' => ['JPEG'],
			'to PNG'  => ['PNG'],
			'to GIF'  => ['GIF'],
			'to WEBP' => ['WEBP'],
		];
	}

	public function testChainedOperations()
	{
		$this->thumb
			->resize(400, 300)
			->rotateImage('CW')
			->crop(50, 50, 200, 200);

		$dimensions = $this->thumb->getCurrentDimensions();

		self::assertSame(200, $dimensions['width']);
		self::assertSame(200, $dimensions['height']);
	}

	public function testMultipleRotations()
	{
		// Four 90° rotations return to original orientation
		$this->thumb->rotateImage('CW');
		$this->thumb->rotateImage('CW');
		$this->thumb->rotateImage('CW');
		$this->thumb->rotateImage('CW');

		$dimensions = $this->thumb->getCurrentDimensions();
		self::assertSame(500, $dimensions['width']);
		self::assertSame(375, $dimensions['height']);
	}

	public function testOldImageGetter()
	{
		$oldImage = $this->thumb->getOldImage();
		self::assertInstanceOf(ImagickExt::class, $oldImage);
	}

	public function testWorkingImageGetter()
	{
		// Working image is null until pad()/crop() etc. is called
		// After pad(), working_image is promoted to old_image.
		$this->thumb->pad(600, 500);
		self::assertInstanceOf(ImagickExt::class, $this->thumb->getOldImage());
	}

	public function testSetOldImage()
	{
		$newImage = new Imagick(__DIR__ . '/../../resources/test.gif');

		$this->thumb->setOldImage($newImage->getOldImage());

		$replacedImage = $this->thumb->getOldImage();
		self::assertInstanceOf(ImagickExt::class, $replacedImage);
	}

	public function testSetWorkingImage()
	{
		$newImage = new Imagick(__DIR__ . '/../../resources/test.gif');
		$this->thumb->setWorkingImage($newImage->getOldImage());

		$workingImage = $this->thumb->getWorkingImage();
		self::assertInstanceOf(ImagickExt::class, $workingImage);
	}

	public function testCurrentDimensions()
	{
		$dimensions = $this->thumb->getCurrentDimensions();

		self::assertArrayHasKey('width', $dimensions);
		self::assertArrayHasKey('height', $dimensions);
		self::assertSame(500, $dimensions['width']);
		self::assertSame(375, $dimensions['height']);
	}

	public function testSetCurrentDimensions()
	{
		$newDimensions = ['width' => 100, 'height' => 100];
		$result = $this->thumb->setCurrentDimensions($newDimensions);

		self::assertSame($newDimensions, $this->thumb->getCurrentDimensions());
		self::assertInstanceOf(Imagick::class, $result);
	}

	public function testNewDimensions()
	{
		$this->thumb->resize(200, 150);

		$newDimensions = $this->thumb->getNewDimensions();
		self::assertArrayHasKey('new_width', $newDimensions);
		self::assertArrayHasKey('new_height', $newDimensions);
		self::assertSame(200, $newDimensions['new_width']);
		self::assertSame(150, $newDimensions['new_height']);
	}

	public function testSetNewDimensions()
	{
		$newDimensions = ['new_width' => 150, 'new_height' => 150];
		$result = $this->thumb->setNewDimensions($newDimensions);

		self::assertSame($newDimensions, $this->thumb->getNewDimensions());
		self::assertInstanceOf(Imagick::class, $result);
	}

	public function testMaxWidthSetter()
	{
		$result = $this->thumb->setMaxWidth(300);
		self::assertSame(300, $this->thumb->getMaxWidth());
		self::assertInstanceOf(Imagick::class, $result);
	}

	public function testMaxHeightSetter()
	{
		$result = $this->thumb->setMaxHeight(300);
		self::assertSame(300, $this->thumb->getMaxHeight());
		self::assertInstanceOf(Imagick::class, $result);
	}

	public function testPercentSetter()
	{
		$result = $this->thumb->setPercent(75);
		self::assertSame(75, $this->thumb->getPercent());
		self::assertInstanceOf(Imagick::class, $result);
	}

	public function testGetFilename()
	{
		self::assertSame(__DIR__ . '/../../resources/test.jpg', $this->thumb->getFilename());
	}

	public function testSetFilename()
	{
		$result = $this->thumb->setFilename('new_filename.jpg');
		self::assertSame('new_filename.jpg', $this->thumb->getFilename());
		self::assertInstanceOf(Imagick::class, $result);
	}

	public function testGetFormat()
	{
		self::assertSame('JPEG', $this->thumb->getFormat());
	}

	public function testSetFormat()
	{
		$result = $this->thumb->setFormat('PNG');
		self::assertSame('PNG', $this->thumb->getFormat());
		self::assertInstanceOf(Imagick::class, $result);
	}

	public function testGetOptions()
	{
		$options = $this->thumb->getOptions();

		self::assertIsArray($options);
		self::assertArrayHasKey('resizeUp', $options);
		self::assertArrayHasKey('jpegQuality', $options);
		self::assertArrayHasKey('avifQuality', $options);
		self::assertArrayHasKey('webpQuality', $options);
	}

	public function testPreserveAlphaOption()
	{
		$this->thumb->setOptions(['preserveAlpha' => true]);
		self::assertTrue($this->thumb->getOptions()['preserveAlpha']);
	}

	public function testInterlaceOption()
	{
		$this->thumb->setOptions(['interlace' => true]);
		self::assertTrue($this->thumb->getOptions()['interlace']);

		$this->thumb->setOptions(['interlace' => false]);
		self::assertFalse($this->thumb->getOptions()['interlace']);
	}

	public function testImageFilterPreservesDimensions()
	{
		$this->thumb->imageFilter(\Imagick::FILTER_GRAYSCALE);

		$dimensions = $this->thumb->getCurrentDimensions();
		self::assertSame(500, $dimensions['width']);
		self::assertSame(375, $dimensions['height']);
	}

	public function testSaveCreatesNonEmptyFile()
	{
		$tempFile = __DIR__ . '/../../resources/imagick_nonempty.jpg';
		$this->thumb->save($tempFile);

		clearstatcache();
		self::assertGreaterThan(0, filesize($tempFile));

		unlink($tempFile);
	}
}
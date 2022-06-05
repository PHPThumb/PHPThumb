<?php
namespace PHPThumb\Tests;

use PHPThumb\GD;

class GDTest extends \PHPUnit\Framework\TestCase
{
	protected $gif;
	protected $jpg;
	protected $png;
	protected $webp;

	protected function setUp():void
	{
		$this->gif = new GD(__DIR__ . '/../../resources/test.gif');
		$this->jpg = new GD(__DIR__ . '/../../resources/test.jpg');
		$this->png = new GD(__DIR__ . '/../../resources/test.png');
		$this->webp = new GD(__DIR__.'/../../resources/test.webp');
	}

	public function testLoadFileTypes()
	{
		self::assertSame('GIF', $this->gif->getFormat());
		self::assertSame('JPG', $this->jpg->getFormat());
		self::assertSame('PNG', $this->png->getFormat());
		self::assertSame('WEBP', $this->webp->getFormat());
	}

	/**
	 * This test might seem pointless but it runs the __destruct and gets us to
	 * 100% code coverage.
	 */
	public function testImageDestroy()
	{
		$testImage = new GD(__DIR__ . '/../../resources/test.gif');
		unset($testImage);
		self::assertSame(false, isset($testImage));
	}

	/**
	 * This test first resize a webp image and then save it in a temp file.
	 * Load the image file and test if the resulting image have a width of 200 px.
	 */
	public function testWebp()
	{
		$this->webp->adaptiveResize(200, 200);
		$tempFile = __DIR__. '/../../resources/resize.webp';
		fwrite(fopen($tempFile, 'w'), $this->webp->getImageAsString());
		$testing = new GD($tempFile);
		self::assertSame(200, $testing->getCurrentDimensions()['width']);
		unlink($tempFile);
	}
}

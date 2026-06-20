<?php

namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPUnit\Framework\TestCase;

class GDLoadTest extends TestCase
{
	protected GD $thumb;

	protected function setUp():void
	{
		$this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
	}

	public function testLoadFile()
	{
		self::assertSame(['width' => 500, 'height' => 375], $this->thumb->getCurrentDimensions());
		self::assertSame([
			'resizeUp'              => false,
			'avifQuality'           => 100,
			'jpegQuality'           => 100,
			'webpQuality'           => 100,
			'correctPermissions'    => false,
			'preserveAlpha'         => true,
			'alphaMaskColor'        => [255, 255, 255],
			'preserveTransparency'  => true,
			'transparencyMaskColor' => [0, 0, 0],
			'interlace'             => null,
			'sharpenAmount'         => 50,
			'textFont'              => null,
			'textDefaultSize'       => 12,
		], $this->thumb->getOptions());

		self::assertSame('JPEG', $this->thumb->getFormat());
		self::assertSame(__DIR__ . '/../../resources/test.jpg', $this->thumb->getFileName());
	}

	public function testSetFormat()
	{
		$this->thumb->setFormat('PNG');
		self::assertSame('PNG', $this->thumb->getFormat());
	}

	public function testSetFileName()
	{
		$this->thumb->setFilename('mytest.jpg');
		self::assertSame('mytest.jpg', $this->thumb->getFilename());
	}

	public function testLoadExternalImage()
	{
		if (!getenv('RUN_NETWORK_TESTS'))
		{
			$this->markTestSkipped(
				'Network tests are disabled (set RUN_NETWORK_TESTS=1 to enable). ' .
				'Note: the previous Gravatar fixture URL has been retired upstream.'
				);
		}

		$remoteThumb = new GD('https://raw.githubusercontent.com/PHPThumb/PHPThumb/master/tests/resources/test.jpg');
		self::assertTrue($remoteThumb->getIsRemoteImage());
	}

	public function testNonexistentFile()
	{
		$this->expectException(InvalidArgumentException::class);
		$madeupThumb = new GD('nosuchimage.jpg');
	}
}

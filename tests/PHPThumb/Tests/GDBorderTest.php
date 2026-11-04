<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPUnit\Framework\TestCase;

class GDBorderTest extends TestCase
{
	protected GD $thumb;

	protected function setUp(): void
	{
		$this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
	}

	public function testBorderReturnsSelf(): void
	{
		$result = $this->thumb->border(10);
		self::assertInstanceOf(GD::class, $result);
	}

	public function testBorderIncreasesDimensions(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->border(10);

		self::assertSame($w + 20, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h + 20, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testBorderColorAtCorner(): void
	{
		$this->thumb->border(10, '#FF0000');

		$rgb = imagecolorat($this->thumb->getOldImage(), 0, 0);
		$r = ($rgb >> 16) & 0xFF;
		$g = ($rgb >> 8)  & 0xFF;
		$b = $rgb & 0xFF;

		self::assertSame(255, $r);
		self::assertSame(0,   $g);
		self::assertSame(0,   $b);
	}

	public function testBorderColorAtAllCorners(): void
	{
		$this->thumb->border(20, [0, 255, 0]);
		$img = $this->thumb->getOldImage();
		$w = imagesx($img);
		$h = imagesy($img);

		foreach ([
			[0,	 0],
			[$w - 1, 0],
			[0,	 $h - 1],
			[$w - 1, $h - 1],
		] as [$x, $y]) {
			$rgb = imagecolorat($img, $x, $y);
			$r = ($rgb >> 16) & 0xFF;
			$g = ($rgb >> 8)  & 0xFF;
			$b = $rgb & 0xFF;

			self::assertSame(0,   $r, "R at ($x,$y)");
			self::assertSame(255, $g, "G at ($x,$y)");
			self::assertSame(0,   $b, "B at ($x,$y)");
		}
	}

	public function testBorderPreservesCenter(): void
	{
		$beforeRgb = imagecolorat($this->thumb->getOldImage(), 250, 187);

		$this->thumb->border(20, '#000000');

		$afterRgb = imagecolorat($this->thumb->getOldImage(), 270, 207);

		self::assertSame($beforeRgb, $afterRgb);
	}

	public function testBorderPreservesTransparencyForPng(): void
	{
		$path = $this->createTransparentPng();
		$thumb = new GD($path);

		$before = imagecolorsforindex(
			$thumb->getOldImage(),
			imagecolorat($thumb->getOldImage(), 25, 25)
			);
		self::assertGreaterThan(100, (int) $before['alpha'], 'fixture must contain a transparent pixel');

		$thumb->border(10, '#FF0000');

		$img = $thumb->getOldImage();

		// The original pixel moves to (35, 35) after a 10px border.
		$after = imagecolorsforindex($img, imagecolorat($img, 35, 35));
		self::assertEqualsWithDelta(
			(int) $before['alpha'],
			(int) $after['alpha'],
			2,
			'the transparent centre must stay transparent'
			);

		$corner = imagecolorsforindex($img, imagecolorat($img, 0, 0));
		self::assertSame(0, (int) $corner['alpha'], 'the border frame must be opaque');
	}

	/**
	 * Builds a 50x50 fully-transparent PNG in the temp directory.
	 */
	private function createTransparentPng(): string
	{
		$path = sys_get_temp_dir() . '/phpthumb_gd_border_alpha_' . getmypid() . '.png';

		$im = imagecreatetruecolor(50, 50);
		imagealphablending($im, false);
		imagesavealpha($im, true);
		$transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
		imagefilledrectangle($im, 0, 0, 49, 49, $transparent);
		imagepng($im, $path);

		return $path;
	}

	public function testBorderAcceptsHexShort(): void
	{
		$this->thumb->border(5, '#fff');
		$rgb = imagecolorat($this->thumb->getOldImage(), 0, 0);
		self::assertSame(255, ($rgb >> 16) & 0xFF);
		self::assertSame(255, ($rgb >> 8)  & 0xFF);
		self::assertSame(255, $rgb & 0xFF);
	}

	public function testBorderAcceptsHexWithoutHash(): void
	{
		$this->thumb->border(5, 'ff8800');
		$rgb = imagecolorat($this->thumb->getOldImage(), 0, 0);
		self::assertSame(0xFF, ($rgb >> 16) & 0xFF);
		self::assertSame(0x88, ($rgb >> 8)  & 0xFF);
		self::assertSame(0x00, $rgb & 0xFF);
	}

	public function testBorderZeroIsNoOp(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->border(0);

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	public function testBorderNegativeThicknessThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->border(-5);
	}

	public function testBorderInvalidColorThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->border(5, [255, 0]);
	}

	public function testBorderInvalidHexThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->border(5, '#zzzzzz');
	}

	public function testBorderIsChainable(): void
	{
		$result = $this->thumb
			->resize(200, 0)
			->border(10, '#000000')
			->sharpen(50);

		self::assertInstanceOf(GD::class, $result);
		self::assertSame(220, $this->thumb->getCurrentDimensions()['width']);
	}

	public function testBorderDefaultColorIsBlack(): void
	{
		$this->thumb->border(5);
		$rgb = imagecolorat($this->thumb->getOldImage(), 0, 0);
		self::assertSame(0, ($rgb >> 16) & 0xFF);
		self::assertSame(0, ($rgb >> 8)  & 0xFF);
		self::assertSame(0, $rgb & 0xFF);
	}
}

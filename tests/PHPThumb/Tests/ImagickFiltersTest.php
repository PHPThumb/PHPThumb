<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImagickFiltersTest extends TestCase
{
	protected Imagick $thumb;

	protected function setUp(): void
	{
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('ext-imagick is not available');
		}

		$this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
	}

	// ---------------- grayscale() ----------------

	public function testGrayscaleReturnsSelf(): void
	{
		self::assertInstanceOf(Imagick::class, $this->thumb->grayscale());
	}

	public function testGrayscaleChangesPixels(): void
	{
		$before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		self::assertNotSame((int) $before['r'], (int) $before['g']);

		$this->thumb->grayscale();

		$after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();

		self::assertEqualsWithDelta((int) $after['r'], (int) $after['g'], 256);
		self::assertEqualsWithDelta((int) $after['g'], (int) $after['b'], 256);
	}

	public function testGrayscalePreservesDimensions(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->grayscale();

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- brightness() ----------------

	public function testBrightnessPositiveBrightens(): void
	{
		$before = $this->thumb->getOldImage()->getImagePixelColor(100, 100)->getColor();
		$beforeSum = $before['r'] + $before['g'] + $before['b'];

		$this->thumb->brightness(50);

		$after = $this->thumb->getOldImage()->getImagePixelColor(100, 100)->getColor();
		$afterSum = $after['r'] + $after['g'] + $after['b'];

		self::assertGreaterThan($beforeSum, $afterSum);
	}

	public function testBrightnessNegativeDarkens(): void
	{
		$before = $this->thumb->getOldImage()->getImagePixelColor(100, 100)->getColor();
		$beforeSum = $before['r'] + $before['g'] + $before['b'];

		$this->thumb->brightness(-50);

		$after = $this->thumb->getOldImage()->getImagePixelColor(100, 100)->getColor();
		$afterSum = $after['r'] + $after['g'] + $after['b'];

		self::assertLessThan($beforeSum, $afterSum);
	}

	public function testBrightnessPreservesDimensions(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->brightness(50);

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- contrast() ----------------

	public function testContrastChangesPixels(): void
	{
		$before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		$this->thumb->contrast(20);
		$after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		self::assertNotSame((int) $before['r'], (int) $after['r']);
	}

	public function testContrastPreservesDimensions(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->contrast(20);

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- blur() ----------------

	public function testBlurChangesPixels(): void
	{
		$before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		$this->thumb->blur(5);
		$after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		self::assertNotSame((int) $before['r'], (int) $after['r']);
	}

	public function testBlurPreservesDimensions(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->blur(5);

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- pixelate() ----------------

	public function testPixelateChangesPixels(): void
	{
		$before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		$this->thumb->pixelate(20);
		$after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		self::assertNotSame((int) $before['r'], (int) $after['r']);
	}

	public function testPixelateOneIsNoOp(): void
	{
		$before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		$this->thumb->pixelate(1);
		$after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();

		self::assertSame((int) $before['r'], (int) $after['r']);
		self::assertSame((int) $before['g'], (int) $after['g']);
		self::assertSame((int) $before['b'], (int) $after['b']);
	}

	public function testPixelateZeroThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->pixelate(0);
	}

	public function testPixelateNegativeThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->pixelate(-5);
	}

	public function testPixelatePreservesDimensions(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->pixelate(20);

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- edgeDetect() ----------------

	public function testEdgeDetectReturnsSelf(): void
	{
		self::assertInstanceOf(Imagick::class, $this->thumb->edgeDetect());
	}

	public function testEdgeDetectChangesPixels(): void
	{
		$before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		$this->thumb->edgeDetect();
		$after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		self::assertNotSame((int) $before['r'], (int) $after['r']);
	}

	public function testEdgeDetectPreservesDimensions(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->edgeDetect();

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- emboss() ----------------

	public function testEmbossReturnsSelf(): void
	{
		self::assertInstanceOf(Imagick::class, $this->thumb->emboss());
	}

	public function testEmbossChangesPixels(): void
	{
		$before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		$this->thumb->emboss();
		$after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		self::assertNotSame((int) $before['r'], (int) $after['r']);
	}

	public function testEmbossPreservesDimensions(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->emboss();

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- smooth() ----------------

	public function testSmoothChangesPixels(): void
	{
		$before = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		$this->thumb->smooth(5);
		$after = $this->thumb->getOldImage()->getImagePixelColor(250, 187)->getColor();
		self::assertNotSame((int) $before['r'], (int) $after['r']);
	}

	public function testSmoothPreservesDimensions(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->smooth(3);

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	// ---------------- imageFilter() ----------------

	public function testLegacyFilterConstantsKeepTheirHistoricalValues(): void
	{
		// These are the historical ImageMagick 6 MagickFilterTypes ids
		// (magick/magick-type.h), which ext-imagick exposed as
		// Imagick::FILTER_* before ImageMagick 7 removed the API.
		self::assertSame(0,  Imagick::FILTER_UNDEFINED);
		self::assertSame(1,  Imagick::FILTER_POINT);
		self::assertSame(2,  Imagick::FILTER_CELLULAR);
		self::assertSame(3,  Imagick::FILTER_EDGEDETECT);
		self::assertSame(4,  Imagick::FILTER_EMBOSS);
		self::assertSame(5,  Imagick::FILTER_GAUSSIAN);
		self::assertSame(6,  Imagick::FILTER_LAPLACE);
		self::assertSame(7,  Imagick::FILTER_LAPLACE_ROBERTS);
		self::assertSame(8,  Imagick::FILTER_OCTAGON);
		self::assertSame(9,  Imagick::FILTER_PREWITT);
		self::assertSame(10, Imagick::FILTER_ROBERTS);
		self::assertSame(11, Imagick::FILTER_SOBEL);
		self::assertSame(12, Imagick::FILTER_UNSHARP);
	}

	public function testLegacyFilterIdsListsTheEmulatedFilters(): void
	{
		self::assertCount(11, Imagick::legacyFilterIds());

		self::assertContains(Imagick::FILTER_SOBEL, Imagick::legacyFilterIds());
		self::assertNotContains(Imagick::FILTER_CELLULAR, Imagick::legacyFilterIds());
	}

	public function testImageFilterIsChainable(): void
	{
		$result = $this->thumb->imageFilter(Imagick::FILTER_SOBEL);

		self::assertInstanceOf(Imagick::class, $result);
	}

	public function testImageFilterPreservesDimensions(): void
	{
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->imageFilter(Imagick::FILTER_SOBEL);

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	#[DataProvider('legacyFilterProvider')]
	public function testEveryLegacyFilterIsSupported(int $filter): void
	{
		// The whole point of the compatibility layer: none of these may
		// fatal, regardless of the ImageMagick major version or the
		// ext-imagick binding generation.
		$result = $this->thumb->imageFilter($filter);

		self::assertInstanceOf(Imagick::class, $result);
		self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(375, $this->thumb->getCurrentDimensions()['height']);
	}

	public static function legacyFilterProvider(): array
	{
		return [
			'point'           => [Imagick::FILTER_POINT],
			'edgedetect'      => [Imagick::FILTER_EDGEDETECT],
			'emboss'          => [Imagick::FILTER_EMBOSS],
			'gaussian'        => [Imagick::FILTER_GAUSSIAN],
			'laplace'         => [Imagick::FILTER_LAPLACE],
			'laplace-roberts' => [Imagick::FILTER_LAPLACE_ROBERTS],
			'octagon'         => [Imagick::FILTER_OCTAGON],
			'prewitt'         => [Imagick::FILTER_PREWITT],
			'roberts'         => [Imagick::FILTER_ROBERTS],
			'sobel'           => [Imagick::FILTER_SOBEL],
			'unsharp'         => [Imagick::FILTER_UNSHARP],
		];
	}

	/**
	 * Every legacy filter must change the pixels of an image that contains a
	 * hard edge.
	 *
	 * The fixture is synthetic (light left half, dark right half) rather
	 * than a photograph: a raw kernel response on a smooth photo can be
	 * uniformly ~0, which would make a "the image changed" assertion
	 * flaky, whereas a hard edge guarantees a large response.
	 */
	#[DataProvider('pixelChangingFilterProvider')]
	public function testLegacyFilterChangesPixels(int $filter, string $label): void
	{
		$thumb = $this->makeEdgeThumb();

		$before = $this->sampleGrid($thumb->getOldImage());

		$thumb->imageFilter($filter);

		$after = $this->sampleGrid($thumb->getOldImage());

		self::assertNotSame(
			$before,
			$after,
			"the {$label} filter must change at least one sampled pixel"
			);
	}

	public static function pixelChangingFilterProvider(): array
	{
		return [
			'edgedetect'      => [Imagick::FILTER_EDGEDETECT, 'edge-detect'],
			'emboss'          => [Imagick::FILTER_EMBOSS, 'emboss'],
			'gaussian'        => [Imagick::FILTER_GAUSSIAN, 'gaussian'],
			'laplace'         => [Imagick::FILTER_LAPLACE, 'laplace'],
			'laplace-roberts' => [Imagick::FILTER_LAPLACE_ROBERTS, 'laplace-roberts'],
			'octagon'         => [Imagick::FILTER_OCTAGON, 'octagon'],
			'prewitt'         => [Imagick::FILTER_PREWITT, 'prewitt'],
			'roberts'         => [Imagick::FILTER_ROBERTS, 'roberts'],
			'sobel'           => [Imagick::FILTER_SOBEL, 'sobel'],
			'unsharp'         => [Imagick::FILTER_UNSHARP, 'unsharp'],
		];
	}

	public function testPointFilterIsANoOp(): void
	{
		$before = $this->sampleGrid($this->thumb->getOldImage());

		$this->thumb->imageFilter(Imagick::FILTER_POINT);

		$after = $this->sampleGrid($this->thumb->getOldImage());

		self::assertSame($before, $after);
	}

	public function testCellularFilterIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('CELLULAR');

		$this->thumb->imageFilter(Imagick::FILTER_CELLULAR);
	}

	public function testGaussianFilterAcceptsNumericRadiusAndSigma(): void
	{
		$before = $this->sampleGrid($this->thumb->getOldImage());

		// radius / sigma are real numbers rather than bools, so the
		// requested blur strength is actually honoured.
		$this->thumb->imageFilter(Imagick::FILTER_GAUSSIAN, 3, 3);

		$after = $this->sampleGrid($this->thumb->getOldImage());

		self::assertNotSame($before, $after);
	}

	public function testUnknownFilterThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Unknown Imagick filter');

		$this->thumb->imageFilter(9999);
	}

	/**
	 * The image must survive a convolution filter completely intact: right
	 * geometry, and still a decodable JPEG.
	 */
	public function testTheImageRemainsValidAfterAConvolutionFilter(): void
	{
		$this->thumb->imageFilter(Imagick::FILTER_SOBEL);

		self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame(375, $this->thumb->getCurrentDimensions()['height']);

		$blob = $this->thumb->getImageAsString();
		self::assertNotEmpty($blob);
		self::assertSame("\xFF\xD8\xFF", substr($blob, 0, 3));
	}

	// ============================================================
	// Helpers
	// ============================================================

	/**
	 * Returns a fresh Imagick backed by a 60x60 image with a hard vertical
	 * edge down the middle.
	 */
	private function makeEdgeThumb(): Imagick
	{
		return new Imagick($this->createEdgeTestImage());
	}

	/**
	 * Writes a 60x60 PNG with a light left half and a dark right half, and
	 * returns its path.
	 *
	 * The file is written to a temporary name first and then renamed, so a
	 * half-written file from an interrupted run is never picked up by the
	 * cache check below.
	 */
	private function createEdgeTestImage(): string
	{
		$path = sys_get_temp_dir() . '/phpthumb_edge_test_v1.png';

		if (is_file($path)) {
			return $path;
		}

		$temp = $path . '.' . getmypid() . '.tmp';

		$img = new \Imagick();
		$img->newImage(60, 60, new \ImagickPixel('rgb(200,200,200)'));

		$draw = new \ImagickDraw();

		// ImagickDraw::rectangle() takes exactly four arguments
		// (x1, y1, x2, y2); the fill colour comes from setFillColor().
		$draw->setFillColor(new \ImagickPixel('rgb(20,20,20)'));
		$draw->rectangle(30, 0, 59, 59);

		$img->drawImage($draw);
		$img->setImageFormat('png');
		$img->writeImage($temp);

		$draw->clear();
		$draw->destroy();
		$img->clear();
		$img->destroy();

		rename($temp, $path);

		return $path;
	}
	/**
	 * Samples a grid of pixels for cheap before/after comparison.
	 *
	 * @return array<int, array{0: int, 1: int, 2: int}>
	 */
	private function sampleGrid(\Imagick $img): array
	{
		$w = $img->getImageWidth();
		$h = $img->getImageHeight();

		$samples = [];
		for ($y = 0; $y < $h; $y += 2) {
			for ($x = 0; $x < $w; $x += 2) {
				$color = $img->getImagePixelColor($x, $y)->getColor();
				$samples[] = [
					(int) $color['r'],
					(int) $color['g'],
					(int) $color['b'],
				];
			}
		}

		return $samples;
	}

	// ---------------- chaining ----------------

	public function testFiltersChainable(): void
	{
		$result = $this->thumb
			->resize(200, 0)
			->grayscale()
			->blur(2)
			->sharpen(40)
			->pixelate(15);

		self::assertInstanceOf(Imagick::class, $result);
		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
	}
}

<?php
namespace PHPThumb\Tests;

use PHPThumb\GD;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../resources/exif_writer.php';

class GDAutoOrientTest extends TestCase
{
	protected GD $thumb;

	protected function setUp(): void
	{
		$this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
	}

	// ... (existing tests unchanged) ...

	public function testAutoOrientRotatesExifSix(): void
	{
		$fixture = $this->resolveExifFixture();

		if ($fixture === null) {
			$this->markTestSkipped(
				'No usable EXIF fixture could be produced — ext-gd is required'
				);
		}

		$img = new GD($fixture);
		self::assertSame(400, $img->getCurrentDimensions()['width']);
		self::assertSame(200, $img->getCurrentDimensions()['height']);

		$img->autoOrient();

		self::assertSame(200, $img->getCurrentDimensions()['width']);
		self::assertSame(400, $img->getCurrentDimensions()['height']);
	}

	/**
	 * The EXIF orientation tag lives in the source file, not in the
	 * in-memory image, so a second autoOrient() call used to read the same
	 * tag again and rotate the already-rotated pixels a second time.
	 */
	public function testAutoOrientIsIdempotentWithAnExifTag(): void
	{
		$fixture = $this->resolveExifFixture();

		if ($fixture === null) {
			$this->markTestSkipped(
				'No usable EXIF fixture could be produced — ext-gd is required'
				);
		}

		$img = new GD($fixture);

		$img->autoOrient();

		$w = $img->getCurrentDimensions()['width'];
		$h = $img->getCurrentDimensions()['height'];

		// The first call must have done something, otherwise the test
		// would pass trivially.
		self::assertSame(200, $w);
		self::assertSame(400, $h);

		$img->autoOrient();

		self::assertSame($w, $img->getCurrentDimensions()['width']);
		self::assertSame($h, $img->getCurrentDimensions()['height']);
	}

	public function testAutoOrientIsChainable(): void
	{
		$result = $this->thumb
			->autoOrient()
			->resize(200, 0)
			->sharpen(50);

		self::assertInstanceOf(GD::class, $result);
		self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
	}

	public function testAutoOrientIsIdempotent(): void
	{
		$this->thumb->autoOrient();
		$w = $this->thumb->getCurrentDimensions()['width'];
		$h = $this->thumb->getCurrentDimensions()['height'];

		$this->thumb->autoOrient();

		self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
		self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
	}

	/**
	 * Returns the path to a JPEG fixture carrying EXIF Orientation = 6.
	 *
	 * The checked-in fixture is preferred; if it is missing or was
	 * generated without the tag, an equivalent one is built on the fly so
	 * the orientation paths are actually exercised.
	 */
	private function resolveExifFixture(): ?string
	{
		$fixture = __DIR__ . '/../../resources/exif_orientation.jpg';

		if (file_exists($fixture) && \ExifFixtureWriter::orientation($fixture) === 6) {
			return $fixture;
		}

		$path = sys_get_temp_dir()
			. '/phpthumb_exif_orientation_' . getmypid() . '.jpg';

		try {
			\ExifFixtureWriter::write($path, 6);
		} catch (\Throwable $e) {
			return null;
		}

		if (\ExifFixtureWriter::orientation($path) !== 6) {
			return null;
		}

		return $path;
	}
}
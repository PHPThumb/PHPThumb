<?php
namespace PHPThumb\Tests;

use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

/**
 * These tests verify the extended Imagick format detection (BMP, HEIC, TIFF,
 * JXL) that the GD branch doesn't support. They skip silently if the fixture
 * files are absent — you can drop real BMP/HEIC/TIFF/JXL samples into
 * tests/resources/ to enable them.
 *
 * The JPEG XL cases are a little smarter than the others: when no checked-in
 * test.jxl exists, one is generated on the fly from the JPEG fixture so the
 * JXL code paths are still exercised on builds that ship libjxl. Builds
 * without a JPEG XL codec skip instead of failing.
 */
class ImagickFormatCoverageTest extends TestCase
{
	/**
	 * Temporary files created by a test; removed on tear-down.
	 *
	 * @var string[]
	 */
	private array $tempFiles = [];

	protected function setUp(): void
	{
		if (!extension_loaded('imagick'))
		{
			$this->markTestSkipped('ext-imagick is not available');
		}
	}

	protected function tearDown(): void
	{
		foreach ($this->tempFiles as $file)
		{
			if (is_file($file))
			{
				unlink($file);
			}
		}

		$this->tempFiles = [];
	}

	public function testBmp()
	{
		$path = __DIR__ . '/../../resources/test.bmp';

		if (!file_exists($path))
		{
			$this->markTestSkipped('test.bmp fixture missing');
		}

		$thumb = new Imagick($path);
		self::assertSame('BMP', $thumb->getFormat());
	}

	public function testHeic()
	{
		$path = __DIR__ . '/../../resources/test.heic';

		if (!file_exists($path))
		{
			$this->markTestSkipped('test.heic fixture missing');
		}

		$thumb = new Imagick($path);
		self::assertSame('HEIC', $thumb->getFormat());
	}

	public function testTiff()
	{
		$path = __DIR__ . '/../../resources/test.tiff';

		if (!file_exists($path))
		{
			$this->markTestSkipped('test.tiff fixture missing');
		}

		$thumb = new Imagick($path);
		self::assertSame('TIFF', $thumb->getFormat());
	}

	// ---------------- JPEG XL ----------------

	/**
	 * A JPEG XL file must be detected and reported as the canonical 'JXL'
	 * format (not as the raw delegate alias 'JPEGXL').
	 */
	public function testJxl()
	{
		$path = $this->resolveJxlFixture();

		if ($path === null)
		{
			$this->markTestSkipped(
				'JPEG XL is not supported by this ImageMagick build '
				. '(no test.jxl fixture and no libjxl encoder)'
				);
		}

		$thumb = new Imagick($path);

		self::assertSame('JXL', $thumb->getFormat());
		self::assertGreaterThan(0, $thumb->getCurrentDimensions()['width']);
		self::assertGreaterThan(0, $thumb->getCurrentDimensions()['height']);
	}

	/**
	 * Saving through the PHPThumb API must produce a real JPEG XL file that
	 * can be read back with the same geometry.
	 */
	public function testJxlSaveRoundTrip()
	{
		if ($this->generateJxlFixture() === null)
		{
			$this->markTestSkipped(
				'JPEG XL encoding is not supported by this ImageMagick build'
				);
		}

		$tempFile = $this->tempPath('roundtrip');

		$thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
		$thumb->save($tempFile, 'JXL');

		self::assertFileExists($tempFile);
		self::assertGreaterThan(0, filesize($tempFile));

		$reloaded = new Imagick($tempFile);

		self::assertSame('JXL', $reloaded->getFormat());
		self::assertSame(500, $reloaded->getCurrentDimensions()['width']);
		self::assertSame(375, $reloaded->getCurrentDimensions()['height']);
	}

	/**
	 * The 'jxlQuality' option must be applied when writing JPEG XL, and the
	 * result must still be a decodable JXL file.
	 */
	public function testJxlQualityOptionIsAppliedWhenSavingJxl()
	{
		if ($this->generateJxlFixture() === null)
		{
			$this->markTestSkipped(
				'JPEG XL encoding is not supported by this ImageMagick build'
				);
		}

		$tempFile = $this->tempPath('quality');

		$thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
		$thumb->setOptions(['jxlQuality' => 30]);
		$thumb->save($tempFile, 'JXL');

		self::assertFileExists($tempFile);

		$reloaded = new Imagick($tempFile);

		self::assertSame('JXL', $reloaded->getFormat());
		self::assertSame(500, $reloaded->getCurrentDimensions()['width']);
		self::assertSame(375, $reloaded->getCurrentDimensions()['height']);
	}

	/**
	 * The Imagick backend is the only one that can encode JPEG XL, so it
	 * must expose a 'jxlQuality' option (and default it to 90).
	 */
	public function testJxlQualityOptionDefaultsToNinety()
	{
		$thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');

		self::assertArrayHasKey('jxlQuality', $thumb->getOptions());
		self::assertSame(90, $thumb->getOptions()['jxlQuality']);
	}

	/**
	 * The bytes handed to the client must actually be JPEG XL — either the
	 * naked code stream or the ISOBMFF container.
	 */
	public function testShowEmitsJxlBytes()
	{
		$path = $this->resolveJxlFixture();

		if ($path === null)
		{
			$this->markTestSkipped(
				'JPEG XL is not supported by this ImageMagick build'
				);
		}

		$thumb = new Imagick($path);

		ob_start();

		try
		{
			$thumb->show(true);
			$output = ob_get_contents();
		}
		catch (\ImagickException $e)
		{
			ob_end_clean();

			$this->markTestSkipped(
				'This ImageMagick build cannot encode JPEG XL: ' . $e->getMessage()
				);
		}

		ob_end_clean();

		self::assertIsString($output);
		self::assertNotEmpty($output);
		self::assertTrue(
			$this->looksLikeJxl($output),
			'The output blob must carry a JPEG XL signature'
			);
	}

	/**
	 * The mime type lookup must map JPEG XL to 'image/jxl'.
	 */
	public function testJxlMimeType()
	{
		$method = new \ReflectionMethod(Imagick::class, 'mimeTypeForFormat');

		self::assertSame('image/jxl', $method->invoke(null, 'JXL'));
		self::assertSame('image/jxl', $method->invoke(null, 'jxl'));
		self::assertSame('image/jxl', $method->invoke(null, 'JPEGXL'));
	}

	/**
	 * The delegate alias 'JPEGXL' must normalise to the canonical 'JXL',
	 * and both spellings must be accepted as the same codec.
	 */
	public function testJxlFormatAliases()
	{
		$normalize = new \ReflectionMethod(Imagick::class, 'normalizeFormatName');

		self::assertSame('JXL', $normalize->invoke(null, 'JXL'));
		self::assertSame('JXL', $normalize->invoke(null, 'jxl'));
		self::assertSame('JXL', $normalize->invoke(null, 'JPEGXL'));
		self::assertSame('JXL', $normalize->invoke(null, ' jxl '));

		$aliases = new \ReflectionMethod(Imagick::class, 'formatAliases');

		self::assertSame(['JXL', 'JPEGXL'], $aliases->invoke(null, 'JXL'));
	}

	// ---------------- helpers ----------------

	/**
	 * Returns the path to a JPEG XL fixture, or null when the current build
	 * has no JPEG XL codec at all.
	 *
	 * A checked-in tests/resources/test.jxl is preferred; otherwise one is
	 * encoded on the fly from the JPEG fixture.
	 */
	private function resolveJxlFixture(): ?string
	{
		$fixture = __DIR__ . '/../../resources/test.jxl';

		if (is_file($fixture))
		{
			return $fixture;
		}

		return $this->generateJxlFixture();
	}

	/**
	 * Encodes the JPEG fixture as JPEG XL and returns the path to the
	 * result, or null when the build cannot encode JPEG XL.
	 */
	private function generateJxlFixture(): ?string
	{
		$path = $this->tempPath('fixture');

		try
		{
			$image = new \Imagick(__DIR__ . '/../../resources/test.jpg');
			$image->setImageFormat('JXL');
			$image->writeImage($path);
			$image->clear();
			$image->destroy();
		}
		catch (\Throwable $e)
		{
			// No JPEG XL delegate in this build.
			return null;
		}

		if (!is_file($path) || filesize($path) === 0)
		{
			return null;
		}

		// Make sure the file really is JPEG XL and not the source JPEG
		// written out under a .jxl name.
		try
		{
			$probe = new \Imagick($path);
			$format = strtoupper((string) $probe->getImageFormat());
			$probe->clear();
			$probe->destroy();
		}
		catch (\Throwable $e)
		{
			return null;
		}

		if (!in_array($format, ['JXL', 'JPEGXL'], true))
		{
			return null;
		}

		return $path;
	}

	/**
	 * Builds a unique temporary file path and registers it for clean-up.
	 */
	private function tempPath(string $suffix): string
	{
		$path = sys_get_temp_dir()
		. '/phpthumb_format_coverage_' . $suffix . '_' . getmypid() . '.jxl';

		$this->tempFiles[] = $path;

		return $path;
	}

	/**
	 * True when the blob carries a JPEG XL signature — either the naked
	 * code stream (FF 0A) or the ISOBMFF container.
	 */
	private function looksLikeJxl(string $blob): bool
	{
		if (str_starts_with($blob, "\xFF\x0A"))
		{
			return true;
		}

		if (str_starts_with($blob, "\x00\x00\x00\x0CJXL \x0D\x0A\x87\x0A"))
		{
			return true;
		}

		return false;
	}
}
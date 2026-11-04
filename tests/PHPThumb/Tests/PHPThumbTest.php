<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\PHPThumb;
use PHPUnit\Framework\TestCase;

/**
 * A minimal concrete PHPThumb so the abstract base class can be exercised
 * without loading an image (and without any network access).
 */
final class PHPThumbTestDouble extends PHPThumb
{
	/** @var array<int, array> every payload handed to setOptions() */
	public array $setOptionsCalls = [];

	protected array $options = [];

	public function __construct(string $file_name, array $options = [], array $plugins = [])
	{
		parent::__construct($file_name, $options, $plugins);

		$this->format = 'JPEG';
	}

	public function setOptions(array $options = []): PHPThumb
	{
		$this->setOptionsCalls[] = $options;
		$this->options = array_merge($this->options, $options);

		return $this;
	}
}

/**
 * A double whose qualityOptionMap() uses the canonical upper-case format
 * names ('JPEG', …) — exactly like the real GD/Imagick backends. It exists
 * to pin the case-normalisation performed by qualityPresetOptions().
 */
final class PHPThumbQualityTestDouble extends PHPThumb
{
	protected array $options = [];

	public function __construct(string $file_name, array $options = [], array $plugins = [])
	{
		parent::__construct($file_name, $options, $plugins);
	}

	public function setOptions(array $options = []): PHPThumb
	{
		$this->options = array_merge($this->options, $options);

		return $this;
	}

	public function getOptions(): array
	{
		return $this->options;
	}

	protected function qualityOptionMap(): array
	{
		return [
			'JPEG' => 'jpegQuality',
			'WEBP' => 'webpQuality',
			'AVIF' => 'avifQuality',
			'JXL'  => 'jxlQuality',
		];
	}
}

/**
 * A double whose option map contains a format that no preset defines.
 */
final class PHPThumbPartialMapTestDouble extends PHPThumb
{
	protected array $options = [];

	public function __construct(string $file_name, array $options = [], array $plugins = [])
	{
		parent::__construct($file_name, $options, $plugins);
	}

	public function setOptions(array $options = []): PHPThumb
	{
		$this->options = array_merge($this->options, $options);

		return $this;
	}

	public function getOptions(): array
	{
		return $this->options;
	}

	protected function qualityOptionMap(): array
	{
		return [
			'JPEG' => 'jpegQuality',
			'TGA'  => 'tgaQuality',
		];
	}
}

class PHPThumbTest extends TestCase
{
	protected function fixturePath(): string
	{
		return __DIR__ . '/../../resources/test.jpg';
	}

	// ---------------- construction / validation ----------------

	public function testConstructorRejectsAMissingFile(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Image file not found');
		new PHPThumbTestDouble('nosuchimage.jpg');
	}

	public function testConstructorAcceptsAnExistingFile(): void
	{
		$thumb = new PHPThumbTestDouble($this->fixturePath());

		self::assertSame($this->fixturePath(), $thumb->getFileName());
	}

	public function testALocalFileIsNotFlaggedAsRemote(): void
	{
		$thumb = new PHPThumbTestDouble($this->fixturePath());

		self::assertFalse($thumb->getIsRemoteImage());
	}

	public function testAUrlIsFlaggedAsRemoteWithoutBeingFetched(): void
	{
		// The double performs no I/O, so this exercises only the URL
		// detection path — no network access is required.
		$thumb = new PHPThumbTestDouble('https://example.com/photo.jpg');

		self::assertTrue($thumb->getIsRemoteImage());
	}

	public function testConstructorPassesTheOptionsToSetOptions(): void
	{
		$thumb = new PHPThumbTestDouble($this->fixturePath(), ['foo' => 'bar']);

		self::assertContains(['foo' => 'bar'], $thumb->setOptionsCalls);
	}

	// ---------------- accessors ----------------

	public function testSetFileName(): void
	{
		$thumb = new PHPThumbTestDouble($this->fixturePath());

		$result = $thumb->setFileName('mytest.jpg');

		self::assertSame('mytest.jpg', $thumb->getFileName());
		self::assertInstanceOf(PHPThumb::class, $result);
	}

	public function testSetFormat(): void
	{
		$thumb = new PHPThumbTestDouble($this->fixturePath());

		$result = $thumb->setFormat('PNG');

		self::assertSame('PNG', $thumb->getFormat());
		self::assertInstanceOf(PHPThumb::class, $result);
	}

	// ---------------- quality presets ----------------

	public function testGetQualityPresets(): void
	{
		self::assertSame(
			['off', 'max', 'high', 'medium', 'low'],
			PHPThumb::getQualityPresets()
			);
	}

	public function testGetQualityPresetReturnsTheMatrix(): void
	{
		self::assertSame(
			[
				'jpeg' => 80,
				'webp' => 78,
				'avif' => 50,
				'jxl'  => 78,
			],
			PHPThumb::getQualityPreset('medium')
			);
	}

	public function testGetQualityPresetRejectsAnUnknownName(): void
	{
		$this->expectException(InvalidArgumentException::class);
		PHPThumb::getQualityPreset('ultra');
	}

	public function testSetQualityPresetOnTheBaseClassAppliesNothing(): void
	{
		// The base class declares no qualityOptionMap(), so a preset
		// expands to an empty option set.
		$thumb = new PHPThumbTestDouble($this->fixturePath());
		$thumb->setOptionsCalls = [];

		$result = $thumb->setQualityPreset('medium');

		self::assertInstanceOf(PHPThumb::class, $result);
		self::assertSame([[]], $thumb->setOptionsCalls);
	}

	public function testSetQualityPresetRejectsAnUnknownName(): void
	{
		$thumb = new PHPThumbTestDouble($this->fixturePath());

		$this->expectException(InvalidArgumentException::class);
		$thumb->setQualityPreset('ultra');
	}

	/**
	 * Regression test: the preset matrix is keyed by lowercase format names
	 * ('jpeg', 'webp', …) while qualityOptionMap() is keyed by the canonical
	 * upper-case names ('JPEG', 'WEBP', …). The lookup must normalise the
	 * case, otherwise every preset expands to an empty option set and the
	 * encoder silently keeps its default quality.
	 */
	public function testQualityPresetIsExpandedThroughTheBackendOptionMap(): void
	{
		$thumb = new PHPThumbQualityTestDouble($this->fixturePath());

		$result = $thumb->setQualityPreset('medium');

		$options = $thumb->getOptions();

		self::assertInstanceOf(PHPThumb::class, $result);
		self::assertSame(80, $options['jpegQuality']);
		self::assertSame(78, $options['webpQuality']);
		self::assertSame(50, $options['avifQuality']);
		self::assertSame(78, $options['jxlQuality']);
	}

	/**
	 * A format that the backend can encode but that no preset defines must
	 * be left untouched — the preset must not invent an option.
	 */
	public function testQualityPresetSkipsFormatsThePresetDoesNotDefine(): void
	{
		$thumb = new PHPThumbPartialMapTestDouble($this->fixturePath());

		$thumb->setQualityPreset('medium');

		$options = $thumb->getOptions();

		self::assertSame(80, $options['jpegQuality']);
		self::assertArrayNotHasKey('tgaQuality', $options);
	}
}
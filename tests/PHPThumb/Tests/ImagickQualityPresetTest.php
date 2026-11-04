<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The Imagick backend can encode JPEG XL, so a quality preset must expand
 * to all four per-format quality options.
 */
class ImagickQualityPresetTest extends TestCase
{
	protected Imagick $thumb;

	protected function setUp(): void
	{
		if (!extension_loaded('imagick')) {
			$this->markTestSkipped('ext-imagick is not available');
		}

		$this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
	}

	public function testSetQualityPresetReturnsSelf(): void
	{
		self::assertInstanceOf(Imagick::class, $this->thumb->setQualityPreset('medium'));
	}

	#[DataProvider('presetOptionProvider')]
	public function testSetQualityPresetAppliesTheBackendOptions(string $preset, array $expected): void
	{
		$this->thumb->setQualityPreset($preset);

		$options = $this->thumb->getOptions();

		foreach ($expected as $key => $value) {
			self::assertArrayHasKey($key, $options);
			self::assertSame($value, $options[$key], "option '{$key}' for preset '{$preset}'");
		}
	}

	public static function presetOptionProvider(): array
	{
		return [
			'max' => [
				'max',
				[
					'jpegQuality' => 100,
					'webpQuality' => 100,
					'avifQuality' => 100,
					'jxlQuality'  => 100,
				],
			],
			'high' => [
				'high',
				[
					'jpegQuality' => 90,
					'webpQuality' => 88,
					'avifQuality' => 65,
					'jxlQuality'  => 90,
				],
			],
			'medium' => [
				'medium',
				[
					'jpegQuality' => 80,
					'webpQuality' => 78,
					'avifQuality' => 50,
					'jxlQuality'  => 78,
				],
			],
			'low' => [
				'low',
				[
					'jpegQuality' => 65,
					'webpQuality' => 62,
					'avifQuality' => 35,
					'jxlQuality'  => 62,
				],
			],
			'off' => [
				'off',
				[
					'jpegQuality' => null,
					'webpQuality' => null,
					'avifQuality' => null,
					'jxlQuality'  => null,
				],
			],
		];
	}

	public function testImagickBackendReceivesAJxlQualityOption(): void
	{
		$this->thumb->setQualityPreset('max');

		self::assertArrayHasKey('jxlQuality', $this->thumb->getOptions());
		self::assertSame(100, $this->thumb->getOptions()['jxlQuality']);
	}

	public function testQualityPresetCanBePassedToTheConstructor(): void
	{
		$thumb = new Imagick(
			__DIR__ . '/../../resources/test.jpg',
			['qualityPreset' => 'high']
			);

		$options = $thumb->getOptions();

		self::assertSame(90, $options['jpegQuality']);
		self::assertSame(88, $options['webpQuality']);
		self::assertSame(65, $options['avifQuality']);
		self::assertSame(90, $options['jxlQuality']);
	}

	public function testQualityPresetCanBePassedToSetOptions(): void
	{
		$this->thumb->setOptions(['qualityPreset' => 'low']);

		$options = $this->thumb->getOptions();

		self::assertSame(65, $options['jpegQuality']);
		self::assertSame(62, $options['webpQuality']);
		self::assertSame(35, $options['avifQuality']);
		self::assertSame(62, $options['jxlQuality']);
	}

	public function testAnExplicitQualityOptionWinsOverThePreset(): void
	{
		$this->thumb->setOptions([
			'qualityPreset' => 'low',
			'jpegQuality'   => 42,
		]);

		self::assertSame(42, $this->thumb->getOptions()['jpegQuality']);
	}

	public function testQualityPresetDoesNotClobberUnrelatedOptions(): void
	{
		$this->thumb->setOptions(['resizeUp' => true]);
		$this->thumb->setQualityPreset('medium');

		$options = $this->thumb->getOptions();

		self::assertTrue($options['resizeUp']);
		self::assertSame(80, $options['jpegQuality']);
	}

	public function testQualityPresetIsNotLeftBehindAsAnOption(): void
	{
		$this->thumb->setOptions(['qualityPreset' => 'medium']);

		self::assertArrayNotHasKey('qualityPreset', $this->thumb->getOptions());
	}

	public function testSetQualityPresetRejectsUnknownPreset(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->setQualityPreset('ultra');
	}

	public function testQualityPresetOptionRejectsUnknownPreset(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->thumb->setOptions(['qualityPreset' => 'ultra']);
	}

	public function testLowerQualityProducesASmallerJpeg(): void
	{
		$low  = __DIR__ . '/../../resources/imagick_quality_low.jpg';
		$high = __DIR__ . '/../../resources/imagick_quality_high.jpg';

		try {
			$thumbLow = new Imagick(__DIR__ . '/../../resources/test.jpg');
			$thumbLow->setOptions(['jpegQuality' => 10]);
			$thumbLow->save($low, 'JPEG');

			$thumbHigh = new Imagick(__DIR__ . '/../../resources/test.jpg');
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
}
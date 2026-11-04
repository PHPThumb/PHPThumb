<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The GD backend has no JPEG XL codec, so it must never receive a
 * 'jxlQuality' option — not even from a preset that defines one.
 */
class GDQualityPresetTest extends TestCase
{
	protected GD $thumb;

	protected function setUp(): void
	{
		$this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
	}

	public function testSetQualityPresetReturnsSelf(): void
	{
		self::assertInstanceOf(GD::class, $this->thumb->setQualityPreset('medium'));
	}

	#[DataProvider('presetOptionProvider')]
	public function testSetQualityPresetAppliesTheBackendOptions(string $preset, array $expected): void
	{
		$this->thumb->setQualityPreset($preset);

		$options = $this->thumb->getOptions();

		foreach ($expected as $key => $value)
		{
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
				],
			],
			'high' => [
				'high',
				[
					'jpegQuality' => 90,
					'webpQuality' => 88,
					'avifQuality' => 65,
				],
			],
			'medium' => [
				'medium',
				[
					'jpegQuality' => 80,
					'webpQuality' => 78,
					'avifQuality' => 50,
				],
			],
			'low' => [
				'low',
				[
					'jpegQuality' => 65,
					'webpQuality' => 62,
					'avifQuality' => 35,
				],
			],
			'off' => [
				'off',
				[
					'jpegQuality' => null,
					'webpQuality' => null,
					'avifQuality' => null,
				],
			],
		];
	}

	public function testGdBackendNeverReceivesAJxlQualityOption(): void
	{
		$this->thumb->setQualityPreset('max');

		self::assertArrayNotHasKey('jxlQuality', $this->thumb->getOptions());
	}

	public function testQualityPresetCanBePassedToTheConstructor(): void
	{
		$thumb = new GD(
			__DIR__ . '/../../resources/test.jpg',
			['qualityPreset' => 'high']
			);

		$options = $thumb->getOptions();

		self::assertSame(90, $options['jpegQuality']);
		self::assertSame(88, $options['webpQuality']);
		self::assertSame(65, $options['avifQuality']);
	}

	public function testQualityPresetCanBePassedToSetOptions(): void
	{
		$this->thumb->setOptions(['qualityPreset' => 'low']);

		$options = $this->thumb->getOptions();

		self::assertSame(65, $options['jpegQuality']);
		self::assertSame(62, $options['webpQuality']);
		self::assertSame(35, $options['avifQuality']);
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
}
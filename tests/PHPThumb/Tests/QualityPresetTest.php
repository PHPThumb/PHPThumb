<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\PHPThumb;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers the shared, backend-agnostic quality-preset API that lives on
 * the abstract PHPThumb base class.
 */
class QualityPresetTest extends TestCase
{
	public function testQualityPresetsConstantListsEveryPreset(): void
	{
		self::assertSame(
			['off', 'max', 'high', 'medium', 'low'],
			array_keys(PHPThumb::QUALITY_PRESETS)
			);
	}

	public function testGetQualityPresetsReturnsThePresetNames(): void
	{
		self::assertSame(
			['off', 'max', 'high', 'medium', 'low'],
			PHPThumb::getQualityPresets()
			);
		self::assertSame(array_keys(PHPThumb::QUALITY_PRESETS), PHPThumb::getQualityPresets());
	}

	#[DataProvider('presetProvider')]
	public function testGetQualityPresetReturnsThePerFormatMatrix(string $preset, array $expected): void
	{
		self::assertSame($expected, PHPThumb::getQualityPreset($preset));
	}

	public static function presetProvider(): array
	{
		return [
			'off' => [
				'off',
				[
					'jpeg' => null,
					'webp' => null,
					'avif' => null,
					'jxl'  => null,
				],
			],
			'max' => [
				'max',
				[
					'jpeg' => 100,
					'webp' => 100,
					'avif' => 100,
					'jxl'  => 100,
				],
			],
			'high' => [
				'high',
				[
					'jpeg' => 90,
					'webp' => 88,
					'avif' => 65,
					'jxl'  => 90,
				],
			],
			'medium' => [
				'medium',
				[
					'jpeg' => 80,
					'webp' => 78,
					'avif' => 50,
					'jxl'  => 78,
				],
			],
			'low' => [
				'low',
				[
					'jpeg' => 65,
					'webp' => 62,
					'avif' => 35,
					'jxl'  => 62,
				],
			],
		];
	}

	public function testGetQualityPresetIsCaseAndWhitespaceInsensitive(): void
	{
		$expected = PHPThumb::getQualityPreset('medium');

		self::assertSame($expected, PHPThumb::getQualityPreset('MEDIUM'));
		self::assertSame($expected, PHPThumb::getQualityPreset('Medium'));
		self::assertSame($expected, PHPThumb::getQualityPreset('  medium  '));
	}

	public function testGetQualityPresetRejectsUnknownPreset(): void
	{
		$this->expectException(InvalidArgumentException::class);
		PHPThumb::getQualityPreset('ultra');
	}

	public function testUnknownPresetMessageListsTheKnownPresets(): void
	{
		$this->expectExceptionMessage('Unknown quality preset');
		PHPThumb::getQualityPreset('ultra');
	}
}
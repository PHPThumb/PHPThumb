<?php

namespace PHPThumb;

use InvalidArgumentException;

/**
 * PhpThumb : PHP Thumb Library <https://github.com/PHPThumb/PHPThumb>
 * Copyright (c) 2009, Ian Selby
 *
 * Author(s): Ian Selby <ianrselby@gmail.com>
 *
 * Licensed under the MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @copyright Copyright (c) 2009 Ian Selby
 * @link https://github.com/PHPThumb/PHPThumb
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 */

abstract class PHPThumb
{
	/**
	 * Per-format encoder quality presets.
	 *
	 * The 0-100 quality scale is NOT interchangeable between formats: each
	 * codec maps it onto a different internal parameter (libjpeg quantization
	 * tables, libwebp VBR quality, an AV1 quantizer, libjxl distance), and
	 * both the resulting curve and the resulting file size differ. The values
	 * below are tuned per codec so that a given preset yields a comparable
	 * *perceptual* result across formats rather than a comparable number.
	 *
	 * Presets, from most to least aggressive:
	 *
	 *   off     - set no quality at all; each codec's own default applies
	 *   max     - maximum quality each codec offers
	 *   high    - visually near-lossless
	 *   medium  - good quality; typical for web delivery
	 *   low     - aggressive compression; small files
	 *
	 * Note that 'max' is NOT the same as 'lossless': JPEG and WebP remain
	 * lossy at their maximum quality, while AVIF and JXL do reach true
	 * lossless.
	 *
	 * A null value means "do not apply a quality setting", which is not the
	 * same as a quality of 0.
	 *
	 * These are starting points, not gospel - validate them against your own
	 * content. Encoder effort/speed defines (e.g. 'heic:speed', 'jxl:effort')
	 * change the file size substantially at identical quality numbers.
	 *
	 * @var array<string, array<string, int|null>>
	 */
	public const QUALITY_PRESETS = [
		'off' => [
			'jpeg' => null,
			'webp' => null,
			'avif' => null,
			'jxl'  => null,
		],
		'max' => [
			'jpeg' => 100,
			'webp' => 100,
			'avif' => 100,
			'jxl'  => 100,
		],
		'high' => [
			'jpeg' => 90,
			'webp' => 88,
			'avif' => 65,
			'jxl'  => 90,
		],
		'medium' => [
			'jpeg' => 80,
			'webp' => 78,
			'avif' => 50,
			'jxl'  => 78,
		],
		'low' => [
			'jpeg' => 65,
			'webp' => 62,
			'avif' => 35,
			'jxl'  => 62,
		],
	];

	/**
	 * The name of the file we're manipulating
	 * This must include the path to the file (absolute paths recommended)
	 */
	protected string $file_name;

	/**
	 * What the file format is (mime-type)
	 */
	protected string $format;

	/**
	 * Whether or not the image is hosted remotely
	 */
	protected bool $remote_image;

	/**
	 * An array of attached plugins to execute in order.
	 */
	protected array $plugins;

	public function __construct(string $file_name, array $options = [], array $plugins = [])
	{
		$this->file_name	= $file_name;
		$this->remote_image	= false;

		if(!$this->validateRequestedResource($file_name))
		{
			throw new InvalidArgumentException('Image file not found: ' . $file_name);
		}

		$this->setOptions($options);

		$this->plugins = $plugins;
	}

	abstract public function setOptions(array $options = []);

	/**
	 * Check the provided filename/url. If it is an url, validate that it is properly
	 * formatted. If it is a file, check to make sure that it actually exists on
	 * the filesystem.
	 */
	protected function validateRequestedResource(string $filename): bool
	{
		if (false !== filter_var($filename, FILTER_VALIDATE_URL))
		{
			$this->remote_image = true;
			return true;
		}

		if (file_exists($filename))
		{
			return true;
		}

		return false;
	}

	/**
	 * Returns the filename.
	 */
	public function getFileName(): string
	{
		return $this->file_name;
	}

	/**
	 * Sets the filename.
	 */
	public function setFileName(string $file_name): PHPThumb
	{
		$this->file_name = $file_name;

		return $this;
	}

	/**
	 * Returns the format.
	 */
	public function getFormat(): string
	{
		return $this->format;
	}

	/**
	 * Sets the format.
	 */
	public function setFormat(string $format): PHPThumb
	{
		$this->format = $format;

		return $this;
	}

	/**
	 * Returns whether the image exists remotely, i.e. it was loaded via a URL.
	 */
	public function getIsRemoteImage(): bool
	{
		return $this->remote_image;
	}

	#################################
	# ----- QUALITY PRESETS ------- #
	#################################

	/**
	 * Returns the names of the available quality presets.
	 *
	 * @return string[]
	 */
	public static function getQualityPresets(): array
	{
		return array_keys(self::QUALITY_PRESETS);
	}

	/**
	 * Returns the per-format quality values for a named preset.
	 *
	 * @param string $preset One of the keys in self::QUALITY_PRESETS:
	 *                       'off', 'max', 'high', 'medium', 'low'.
	 * @return array<string, int|null> format key => quality value
	 *
	 * @throws InvalidArgumentException For an unknown preset name.
	 */
	public static function getQualityPreset(string $preset): array
	{
		$key = strtolower(trim($preset));

		if (!array_key_exists($key, self::QUALITY_PRESETS))
		{
			throw new InvalidArgumentException(
				'Unknown quality preset: "' . $preset . '". Expected one of: ' .
				implode(', ', array_keys(self::QUALITY_PRESETS)) . '.'
				);
		}

		return self::QUALITY_PRESETS[$key];
	}

	/**
	 * Applies a named quality preset to this instance.
	 *
	 * The preset sets every quality option the backend actually supports
	 * (see qualityOptionMap()), so applying one never leaves an option the
	 * backend ignores behind.
	 *
	 * Example:
	 *
	 *     $thumb = new \PHPThumb\Imagick('photo.jpg');
	 *     $thumb->setQualityPreset('medium');
	 *
	 * @param string $preset One of the keys in self::QUALITY_PRESETS:
	 *                       'off', 'max', 'high', 'medium', 'low'.
	 * @return $this
	 *
	 * @throws InvalidArgumentException For an unknown preset name.
	 */
	public function setQualityPreset(string $preset): static
	{
		$this->setOptions($this->qualityPresetOptions($preset));
		
		return $this;
	}
	
	/**
	 * Expands a named quality preset into the per-format option values that
	 * this backend actually understands.
	 *
	 * @param string $preset One of the keys in self::QUALITY_PRESETS.
	 * @return array<string, int|null> option key => quality value
	 *
	 * @throws InvalidArgumentException For an unknown preset name.
	 */
	protected function qualityPresetOptions(string $preset): array
	{
		$values = self::getQualityPreset($preset);
		
		$options = [];
		foreach ($this->qualityOptionMap() as $format => $option_key)
		{
			if (array_key_exists($format, $values))
			{
				$options[$option_key] = $values[$format];
			}
		}
		
		return $options;
	}

	/**
	 * Maps canonical format names to the option key that controls the encoder
	 * quality for that format.
	 *
	 * Backends override this to declare which formats they can actually
	 * encode, so a preset never sets an option the backend ignores.
	 *
	 * @return array<string, string> canonical format name => option key
	 */
	protected function qualityOptionMap(): array
	{
		return [];
	}
}
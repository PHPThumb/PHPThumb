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
	 * Whether the supplied source was the raw bytes of an image, rather
	 * than a file path or a URL.
	 *
	 * @var bool
	 */
	protected bool $raw_image = false;

	/**
	 * An array of attached plugins to execute in order.
	 */
	protected array $plugins;

	/**
	 * Whether the plugin stack has already been executed for this instance.
	 *
	 * @var bool
	 */
	protected bool $plugins_executed = false;


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
	 * formatted. If it is a file, check to make sure that it actually exists on the
	 * filesystem. If it is neither, it may be the raw bytes of an image.
	 */
	protected function validateRequestedResource(string $filename): bool
	{
		if (false !== filter_var($filename, FILTER_VALIDATE_URL))
		{
			$this->remote_image = true;
			return true;
		}

		// A filesystem call with a null byte in the path is a fatal error on
		// PHP 8, and binary image data almost always contains null bytes —
		// so the path is only probed when it could plausibly be one.
		if (!str_contains($filename, "\0") && @file_exists($filename))
		{
			return true;
		}

		// Neither a path nor a URL: the source may be the raw bytes of an
		// image (a database blob, a data URI payload, an upload).
		if ($this->looksLikeImageBytes($filename))
		{
			$this->raw_image = true;
			return true;
		}

		return false;
	}

	/**
	 * Returns true when the given string looks like the raw bytes of an
	 * image rather than a filesystem path.
	 *
	 * Two independent signals are used:
	 *
	 *  1. The data starts with one of the known image signatures.
	 *  2. The data is binary — it contains a null byte, or it is not valid
	 *     UTF-8. A filesystem path is always text, so binary data cannot
	 *     be one.
	 *
	 * A path that does not exist satisfies neither test, so a typo still
	 * produces the familiar "Image file not found" error rather than a
	 * confusing format error.
	 */
	protected function looksLikeImageBytes(string $data): bool
	{
		if ($data === '')
		{
			return false;
		}

		if ($this->sniffImageFormat($data) !== null)
		{
			return true;
		}

		if (str_contains($data, "\0"))
		{
			return true;
		}

		// preg_match('//u', …) validates UTF-8 without needing ext-mbstring.
		return preg_match('//u', $data) !== 1;
	}

	/**
	 * Identifies an image format from the leading bytes of the data.
	 *
	 * The signatures are the same ones every image codec publishes, so the
	 * detection does not depend on the PHP build knowing about a given
	 * format — which matters for AVIF and WebP, whose MIME types are not
	 * reported by getimagesize() on every PHP version.
	 *
	 * @return string|null The canonical format name, or null when the data
	 *                     does not match a known signature.
	 */
	protected function sniffImageFormat(string $data): ?string
	{
		$length = strlen($data);

		// JPEG
		if ($length >= 3 && substr($data, 0, 3) === "\xFF\xD8\xFF")
		{
			return 'JPEG';
		}

		// PNG
		if ($length >= 8 && substr($data, 0, 8) === "\x89PNG\r\n\x1A\n")
		{
			return 'PNG';
		}

		// GIF
		if ($length >= 6
			&& (substr($data, 0, 6) === 'GIF87a' || substr($data, 0, 6) === 'GIF89a'))
		{
			return 'GIF';
		}

		// WebP — a RIFF container with a WEBP form type
		if ($length >= 12 && substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP')
		{
			return 'WEBP';
		}

		// JPEG XL — the naked code stream
		if ($length >= 2 && substr($data, 0, 2) === "\xFF\x0A")
		{
			return 'JXL';
		}

		// BMP
		if ($length >= 2 && substr($data, 0, 2) === 'BM')
		{
			return 'BMP';
		}

		// TIFF, little- and big-endian
		if ($length >= 4
			&& (substr($data, 0, 4) === "II\x2A\x00" || substr($data, 0, 4) === "MM\x00\x2A"))
		{
			return 'TIFF';
		}

		// ISOBMFF-based formats: the brand sits at offset 8, after 'ftyp'.
		if ($length >= 12 && substr($data, 4, 4) === 'ftyp')
		{
			$brand = substr($data, 8, 4);

			if (in_array($brand, ['avif', 'avis'], true))
			{
				return 'AVIF';
			}

			if (in_array($brand, ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'hevm', 'hevs'], true))
			{
				return 'HEIC';
			}

			if ($brand === 'JXL ')
			{
				return 'JXL';
			}
		}

		// SVG is text, so it has no binary signature — look for the root
		// element in the first kilobyte instead.
		$head = substr($data, 0, 1024);

		if (preg_match('//u', $head) === 1)
		{
			$trimmed = ltrim($head);

			if (str_starts_with($trimmed, '<svg'))
			{
				return 'SVG';
			}

			if (str_starts_with($trimmed, '<?xml') && stripos($head, '<svg') !== false)
			{
				return 'SVG';
			}
		}

		return null;
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

	/**
	 * Resolves the sharpening strength used by sharpen().
	 *
	 * A null $amount means "not supplied" — the 'sharpenAmount' option is
	 * used instead. The option defaults to 50, so sharpen() with no
	 * argument behaves exactly as it did before the option became
	 * meaningful.
	 *
	 * An explicit 0 is honoured as a no-op, which is why the "not
	 * supplied" case is signalled with null rather than with 0.
	 *
	 * @param int|null $amount The caller-supplied strength, or null.
	 * @return int The resolved strength, in the range 0–100.
	 *
	 * @throws InvalidArgumentException If the supplied amount — or the
	 *                                  'sharpenAmount' option — is out of range.
	 */
	protected function resolveSharpenAmount(?int $amount): int
	{
		if ($amount !== null)
		{
			if ($amount < 0 || $amount > 100)
			{
				throw new InvalidArgumentException(
					'sharpen() $amount must be between 0 and 100, got: ' . $amount
					);
			}

			return $amount;
		}

		$configured = (int) ($this->options['sharpenAmount'] ?? 50);

		if ($configured < 0 || $configured > 100)
		{
			throw new InvalidArgumentException(
				"The 'sharpenAmount' option must be between 0 and 100, got: " . $configured
				);
		}

		return $configured;
	}

	/**
	 * Executes the attached plugin stack.
	 *
	 * The stack is executed at most once per instance. `show()` and
	 * `save()` both route through this method, so a caller who produces
	 * both a streamed and a written image still ends up with a singly
	 * plugged image: whichever call comes first applies the plugins to the
	 * in-memory image, and the second call writes or streams that
	 * already-plugged image. Without the guard a watermark would be
	 * composited twice.
	 *
	 * The flag is set *before* the plugins run, so a plugin that throws
	 * cannot cause the stack to be re-applied on a later call.
	 */
	protected function executePlugins(): void
	{
		if ($this->plugins_executed)
		{
			return;
		}

		$this->plugins_executed = true;

		foreach ($this->plugins as $plugin)
		{
			$plugin->execute($this);
		}
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
	 * The preset matrix (self::QUALITY_PRESETS) is keyed by lowercase
	 * format name ('jpeg', 'webp', 'avif', 'jxl'), while qualityOptionMap()
	 * is keyed by the canonical format name ('JPEG', 'WEBP', 'AVIF', 'JXL').
	 * The lookup below normalises the case so a backend can use either
	 * spelling; without it every preset silently expands to an empty option
	 * set and the encoder keeps its default quality.
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
			$key = strtolower(trim((string) $format));

			if (array_key_exists($key, $values))
			{
				$options[$option_key] = $values[$key];
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
	 * The keys may be written in any case — qualityPresetOptions() lowercases
	 * them before matching against the preset matrix — but the canonical
	 * upper-case spelling ('JPEG', 'WEBP', 'AVIF', 'JXL') is preferred.
	 *
	 * @return array<string, string> canonical format name => option key
	 */
	protected function qualityOptionMap(): array
	{
		return [];
	}
}
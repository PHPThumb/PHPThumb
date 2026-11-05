<?php

namespace PHPThumb;

use Exception;
use ImagickDraw;
use ImagickPixel;
use InvalidArgumentException;
use RuntimeException;

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

class Imagick extends PHPThumb
{
	/**
	 * Legacy spatial-filter identifiers.
	 *
	 * ext-imagick exposed these as Imagick::FILTER_* up to ext-imagick 3.4
	 * (ImageMagick 6). ImageMagick 7 removed the underlying
	 * MagickFilterImage() API entirely, so ext-imagick 3.5+ dropped both
	 * the constants and Imagick::filter() itself.
	 *
	 * The values below are the historical ImageMagick 6 MagickFilterTypes
	 * ids (magick/magick-type.h), so user code can migrate from
	 * Imagick::FILTER_SOBEL to PHPThumb\Imagick::FILTER_SOBEL with a
	 * one-line change and keep the same numeric semantics.
	 *
	 *   0 UndefinedFilter   7 LaplacianRobertsFilter
	 *   1 PointFilter       8 OctagonFilter
	 *   2 CellularFilter    9 PrewittFilter
	 *   3 EdgeDetectFilter 10 RobertsFilter
	 *   4 EmbossFilter     11 SobelFilter
	 *   5 GaussianFilter   12 UnsharpFilter
	 *   6 LaplacianFilter
	 *
	 * @see self::imageFilter()
	 */
	public const FILTER_UNDEFINED       = 0;
	public const FILTER_POINT           = 1;
	public const FILTER_CELLULAR        = 2;
	public const FILTER_EDGEDETECT      = 3;
	public const FILTER_EMBOSS          = 4;
	public const FILTER_GAUSSIAN        = 5;
	public const FILTER_LAPLACE         = 6;
	public const FILTER_LAPLACE_ROBERTS = 7;
	public const FILTER_OCTAGON         = 8;
	public const FILTER_PREWITT         = 9;
	public const FILTER_ROBERTS         = 10;
	public const FILTER_SOBEL           = 11;
	public const FILTER_UNSHARP         = 12;

	/**
	 * The prior image (before manipulation)
	 *
	 * @var \Imagick|null
	 */
	protected ?\Imagick $old_image = null;

	/**
	 * The working image (used during manipulation)
	 *
	 * @var \Imagick|null
	 */
	protected ?\Imagick $working_image = null;

	/**
	 * The current dimensions of the image
	 */
	protected array $current_dimensions;

	/**
	 * The new, calculated dimensions of the image
	 */
	protected array $new_dimensions;

	/**
	 * The options for this class
	 */
	protected array $options = [];

	/**
	 * The maximum width an image can be after resizing (in pixels)
	 */
	protected int $max_width;

	/**
	 * The maximum height an image can be after resizing (in pixels)
	 */
	protected int $max_height;

	/**
	 * The percentage to resize the image by
	 */
	protected int $percent;

	/**
	 * @throws Exception
	 */
	public function __construct(string $file_name, array $options = [], array $plugins = [])
	{
		if (!extension_loaded('imagick'))
		{
			throw new RuntimeException(
				'The Imagick extension is required to use PHPThumb\Imagick. ' .
				'Install ext-imagick or use PHPThumb\GD instead.'
				);
		}

		parent::__construct($file_name, $options, $plugins);

		$this->old_image = new \Imagick();

		try
		{
			// Local files and remote URLs are both read through Imagick (it
			// uses its own delegates for network protocols). The image is
			// loaded up-front because Imagick is the only component that
			// knows which format it just decoded - getimagesize()/finfo
			// only recognise a fraction of the supported formats.
			$this->old_image->readImage($this->file_name);
		}
		catch (\ImagickException $e)
		{
			$this->old_image->clear();
			$this->old_image->destroy();
			$this->old_image = null;

			throw new Exception(
				'Could not read image: ' . $this->file_name . ' (' . $e->getMessage() . ')',
				0,
				$e
				);
		}

		$this->determineFormat();
		$this->verifyFormatCompatibility();

		$this->current_dimensions = [
			'width'		=> $this->old_image->getImageWidth(),
			'height'	=> $this->old_image->getImageHeight()
		];
	}

	/**
	 * Destructor
	 */
	public function __destruct()
	{
		// $old_image and $working_image may point at the same object (see
		// pad()), so de-duplicate before clearing/destroying.
		$destroyed = [];

		foreach (['old_image', 'working_image'] as $property)
		{
			$image = $this->{$property};

			if ($image instanceof \Imagick)
			{
				$id = spl_object_id($image);

				if (!isset($destroyed[$id]))
				{
					$destroyed[$id] = true;
					$image->clear();
					$image->destroy();
				}
			}

			$this->{$property} = null;
		}
	}

	/**
	 * Pad an image to desired dimensions. Moves the image into the center and fills the rest with $color.
	 */
	public function pad(int $width, int $height, array $color = [255, 255, 255]): Imagick
	{
		// no resize - woohoo!
		if ($width == $this->current_dimensions['width'] && $height == $this->current_dimensions['height'])
		{
			return $this;
		}

		$this->working_image = new \Imagick();

		$pixel = new ImagickPixel('rgb(' . $color[0] . ', ' . $color[1] . ', ' . $color[2] . ')');
		$this->working_image->newImage($width, $height, $pixel);
		$pixel->destroy();

		// Keep the format of the source image (getImageFormat()/setImageFormat()
		// are the explicit spellings of the getFormat()/setFormat() aliases).
		$this->working_image->setImageFormat($this->old_image->getImageFormat());

		$x = intval(($width - $this->current_dimensions['width']) / 2);
		$y = intval(($height - $this->current_dimensions['height']) / 2);

		$this->working_image->compositeImage(
			$this->old_image,
			\Imagick::COMPOSITE_DEFAULT,
			$x,
			$y
			);

		$this->old_image->clear();
		$this->old_image->destroy();
		$this->old_image = $this->working_image;
		$this->working_image = null;   // same object - do not destroy twice

		$this->current_dimensions['width']  = $width;
		$this->current_dimensions['height'] = $height;

		return $this;
	}

	/**
	 * Adds a solid-color border (frame) of $thickness pixels around the image.
	 *
	 * The resulting image grows by 2 × $thickness pixels in both axes
	 * (width and height each gain $thickness on every side). The original
	 * pixel data is preserved untouched in the center; only the surrounding
	 * frame is filled with $color.
	 *
	 * Internally this builds a fresh Imagick canvas of the new size filled
	 * with $color, then composites the original image centered inside it.
	 *
	 * @param int $thickness Frame thickness in pixels (positive integer).
	 * @param array|string $color Either a hex string ('#FF8800' / '#f80' / 'FF8800')
	 *                            or an [r, g, b] array.
	 * @return $this
	 *
	 * @throws InvalidArgumentException For negative thickness or invalid color.
	 */
	public function border(int $thickness, array|string $color = [0, 0, 0]): Imagick
	{
		if ($thickness < 0)
		{
			throw new InvalidArgumentException(
				'border() $thickness must be non-negative, got: ' . $thickness
				);
		}

		if ($thickness === 0)
		{
			return $this; // no-op
		}

		$rgb = $this->parseColor($color);

		$current_width  = $this->current_dimensions['width'];
		$current_height = $this->current_dimensions['height'];

		$new_width  = $current_width  + 2 * $thickness;
		$new_height = $current_height + 2 * $thickness;

		// Build the new canvas, filled with the border color.
		$canvas = new \Imagick();
		$canvas->newImage(
			$new_width,
			$new_height,
			$this->colorToPixel($rgb)
			);
		$canvas->setImageFormat($this->old_image->getImageFormat());

		// Composite the original image centered inside.
		$canvas->compositeImage(
			$this->old_image,
			\Imagick::COMPOSITE_DEFAULT,
			$thickness,
			$thickness
			);

		// Commit.
		$this->old_image->clear();
		$this->old_image->destroy();
		$this->old_image = $canvas;

		$this->current_dimensions['width']  = $new_width;
		$this->current_dimensions['height'] = $new_height;

		return $this;
	}

	/**
	 * Parses a color argument into an [r, g, b] array suitable for Imagick.
	 *
	 * @param array|string $color
	 * @return array{r: int, g: int, b: int}
	 */
	protected function parseColor(array|string $color): array
	{
		if (is_array($color))
		{
			$count = count($color);
			if ($count !== 3 && $count !== 4)
			{
				throw new InvalidArgumentException(
					'border() color array must have 3 (RGB) or 4 (RGBA) elements, got: ' . $count
					);
			}
			return [
				'r' => max(0, min(255, (int) $color[0])),
				'g' => max(0, min(255, (int) $color[1])),
				'b' => max(0, min(255, (int) $color[2])),
			];
		}

		if (!is_string($color))
		{
			throw new InvalidArgumentException(
				'border() color must be a hex string or an [r, g, b] array.'
				);
		}

		$hex = ltrim(trim($color), '#');
		if (str_starts_with($hex, '0x') || str_starts_with($hex, '0X'))
		{
			$hex = substr($hex, 2);
		}

		if (strlen($hex) === 3)
		{
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if (strlen($hex) !== 6 && strlen($hex) !== 8)
		{
			throw new InvalidArgumentException(
				'border() hex color must be 3, 6, or 8 hex digits (with optional #), got: ' . $color
				);
		}

		if (!ctype_xdigit($hex))
		{
			throw new InvalidArgumentException(
				'border() hex color contains non-hex characters: ' . $color
				);
		}

		return [
			'r' => hexdec(substr($hex, 0, 2)),
			'g' => hexdec(substr($hex, 2, 2)),
			'b' => hexdec(substr($hex, 4, 2)),
		];
	}

	/**
	 * Builds an ImagickPixel from an [r, g, b] array.
	 *
	 * @param array{r: int, g: int, b: int} $rgb
	 */
	protected function colorToPixel(array $rgb): \ImagickPixel
	{
		return new \ImagickPixel(sprintf('rgb(%d, %d, %d)', $rgb['r'], $rgb['g'], $rgb['b']));
	}

	/**
	 * Renders $text onto the image using ImagickDraw.
	 *
	 * Position keywords — same 9-grid + aliases as the GD backend.
	 *
	 * Options — same as the GD backend, with the following Imagick-specific notes:
	 *
	 *  - font     accepts both TTF paths and Imagick registered font names
	 *             ('Courier', 'Helvetica', 'Times', etc.). Built-in fonts
	 *             cannot be stroked reliably — pass a TTF if you need stroke.
	 *  - size     is in pixels (matches Imagick's setFontSize() semantics).
	 *
	 * Rotation: GD's imagettftext rotates CCW for positive angles. Imagick's
	 * annotateImage rotates CW for positive angles. To preserve GD-compatible
	 * behavior (the documented meaning of the `angle` option), the angle is
	 * negated before passing to annotateImage().
	 *
	 * Draw order, per line:
	 *   1. Background pill (drawn once around the block)
	 *   2. Shadow    (separate annotateImage with shadow ImagickDraw)
	 *   3. Stroke    (separate annotateImage with transparent-fill, stroke-only draw)
	 *   4. Main text (final annotateImage with main ImagickDraw)
	 *
	 * @param string $text     The text to render. Multi-line via "\n" supported.
	 * @param string $position Anchor keyword (see GD::text() docs).
	 * @param array  $options  See GD::text() docs.
	 * @return $this
	 *
	 * @throws InvalidArgumentException For unknown position strings or bad colors.
	 * @throws RuntimeException If no usable font can be located or metrics fail.
	 */
	public function text(
		string $text,
		string $position = 'bottom-right',
		array $options = []
		): Imagick
		{
			if ($text === '')
			{
				return $this;
			}

			$cfg = $this->resolveTextOptions($options);
			$anchor = $this->resolveTextAnchor($position);

			$current_width  = $this->current_dimensions['width'];
			$current_height = $this->current_dimensions['height'];

			$font_path = $this->resolveFont($cfg['font']);

			// ----- Rotation parity fix -----
			// GD: positive = CCW. Imagick: positive = CW. Negate so that
			// 'angle' => -15 means 15° clockwise in BOTH backends.
			$imagick_angle = -$cfg['angle'];

			// ----- Multi-line setup -----
			$lines     = explode("\n", $text);
			$num_lines = count($lines);

			$measure = new \ImagickDraw();
			$measure->setFont($font_path);
			$measure->setFontSize($cfg['size']);

			// Per-line: width and rotated visual height (depend on which
			// glyphs are in each line).
			$line_widths            = [];
			$line_visual_heights    = [];
			$max_line_width         = 0;
			$max_line_visual_height = 0;

			foreach ($lines as $line)
			{
				try
				{
					$m = $this->old_image->queryFontMetrics($measure, $line);
				}
				catch (\ImagickException $e)
				{
					$measure->clear();
					$measure->destroy();
					throw new RuntimeException(
						'Imagick: queryFontMetrics() failed for font "' . $font_path . '": ' . $e->getMessage(),
						0, $e
						);
				}

				$line_w = (int) ($m['textWidth'] ?? 0);
				$line_widths[] = $line_w;
				if ($line_w > $max_line_width)
				{
					$max_line_width = $line_w;
				}

				// textWidth/textHeight reflect the rotated bbox for
				// non-zero angles (Imagick measures with the angle applied).
				$line_visual_h = (int) ($m['textHeight'] ?? 0);
				$line_visual_heights[] = $line_visual_h;
				if ($line_visual_h > $max_line_visual_height)
				{
					$max_line_visual_height = $line_visual_h;
				}
			}

			// ----- Font-level ascender + descender, ONCE, via probe -----
			// queryFontMetrics() per-string under-reports the ascender/
			// descender when the string doesn't contain tall caps or
			// descender glyphs. Probe with a string that covers every
			// glyph category to get the font's worst-case metrics.
			try
			{
				$probe_metrics = $this->old_image->queryFontMetrics(
					$measure,
					'Hgjpqy0123456789'
					);
			}
			catch (\ImagickException $e)
			{
				$measure->clear();
				$measure->destroy();
				throw new RuntimeException(
					'Imagick: queryFontMetrics() failed for font probe: ' . $e->getMessage(),
					0, $e
					);
			}
			$measure->clear();
			$measure->destroy();

			$font_ascender  = (int) ($probe_metrics['ascender']  ?? 0);
			$font_descender = (int) abs($probe_metrics['descender'] ?? 0);

			// ----- Line height -----
			// Same formula as GD: max of (probe-based with 30% buffer +
			// size*0.1 margin, size-based with multiplier), plus 2*stroke_w
			// when stroke is enabled (each stroked line expands outward
			// by stroke_w in all directions, stealing 2*stroke_w from
			// the inter-line gap).
			$multiplier = (isset($cfg['lineHeight']) && $cfg['lineHeight'] > 0)
			? (float) $cfg['lineHeight']
			: 1.3;

			$stroke_padding = $cfg['stroke']['enabled']
			? max(1, (int) $cfg['stroke']['width']) * 2
			: 0;

			$buffered_font_height = (int) round(
				($font_ascender + $font_descender) * 1.3 + $cfg['size'] * 0.1
				) + $stroke_padding;
				$size_based_height    = (int) round($cfg['size'] * $multiplier) + $stroke_padding;
				$line_height          = max($buffered_font_height, $size_based_height);

				// ----- Block dimensions -----
				$block_width  = $max_line_width;
				$block_height = $max_line_visual_height + max(0, ($num_lines - 1) * $line_height);

				[$block_x, $block_y] = $this->computeTextTopLeft(
					$anchor, $cfg['offsetX'], $cfg['offsetY'],
					$current_width, $current_height,
					$block_width, $block_height
					);

				// First-line baseline: block_y + font_ascender (font-level, not
				// per-line, so it works for any input string).
				$first_line_baseline = $block_y + $font_ascender;

				// ----- Build the ImagickDraw instances (one per layer) -----
				$build_draw = static function () use ($font_path, $cfg): \ImagickDraw {
					$d = new \ImagickDraw();
					$d->setFont($font_path);
					$d->setFontSize($cfg['size']);
					// Pin ALIGN_LEFT so (x, y) is always the top-left of the
					// text bounding box, regardless of the user's align option.
					// Per-line alignment ('left'/'center'/'right') is computed
					// by the caller from $cfg['align'] — not via setTextAlignment.
					$d->setTextAlignment(\Imagick::ALIGN_LEFT);
					$d->setTextAntialias(true);
					$d->setStrokeAntialias(true);
					return $d;
				};

				// Shadow draw: fill only, no stroke.
				$shadow_draw = null;
				if ($cfg['shadow']['enabled'])
				{
					$shadow_rgb = $this->parseColor($cfg['shadow']['color']);
					$shadow_draw = $build_draw();
					$shadow_draw->setFillColor(new \ImagickPixel(sprintf(
						'rgba(%d, %d, %d, 1)',
						$shadow_rgb['r'], $shadow_rgb['g'], $shadow_rgb['b']
						)));
					$shadow_draw->setStrokeColor(new \ImagickPixel('transparent'));
					$shadow_draw->setStrokeWidth(0);
				}

				// Stroke draw: transparent fill, stroke outline only.
				// Done as a SEPARATE pass so we don't depend on Imagick's
				// combined fill+stroke behavior (which is unreliable when
				// fill alpha drops below 1.0 across some builds).
				$stroke_draw = null;
				if ($cfg['stroke']['enabled'])
				{
					$stroke_rgb = $this->parseColor($cfg['stroke']['color']);
					$stroke_draw = $build_draw();
					$stroke_draw->setFillColor(new \ImagickPixel('transparent'));
					$stroke_draw->setStrokeColor(new \ImagickPixel(sprintf(
						'rgba(%d, %d, %d, 1)',
						$stroke_rgb['r'], $stroke_rgb['g'], $stroke_rgb['b']
						)));
					$stroke_draw->setStrokeWidth(max(1, (int) $cfg['stroke']['width']));
				}

				// Main draw: fill only, no stroke (stroke is its own pass).
				$main_draw = $build_draw();
				$rgb = $this->parseColor($cfg['color']);
				$main_draw->setFillColor(new \ImagickPixel(sprintf(
					'rgba(%d, %d, %d, %f)',
					$rgb['r'], $rgb['g'], $rgb['b'],
					$cfg['alpha'] / 100
					)));
				$main_draw->setStrokeColor(new \ImagickPixel('transparent'));
				$main_draw->setStrokeWidth(0);

				// ----- Background pill (drawn once around the block) -----
				if ($cfg['background']['enabled'])
				{
					$bg_rgb = $this->parseColor($cfg['background']['color']);
					$padding  = (int) ($cfg['background']['padding'] ?? 4);
					$bg_alpha = max(0, min(100, (int) ($cfg['background']['alpha'] ?? 75))) / 100;

					$pill_x = max(0, $block_x - $padding);
					$pill_y = max(0, $block_y - $padding);
					$pill_w = $block_width + 2 * $padding;
					$pill_h = $block_height + 2 * $padding;

					if ($pill_x + $pill_w > $current_width)
					{
						$pill_w = max(0, $current_width - $pill_x);
					}
					if ($pill_y + $pill_h > $current_height)
					{
						$pill_h = max(0, $current_height - $pill_y);
					}

					$bg_draw = new \ImagickDraw();
					$bg_draw->setFillColor(new \ImagickPixel(sprintf(
						'rgba(%d, %d, %d, %f)',
						$bg_rgb['r'], $bg_rgb['g'], $bg_rgb['b'],
						$bg_alpha
						)));
					$bg_draw->rectangle($pill_x, $pill_y, $pill_x + $pill_w, $pill_y + $pill_h);

					$this->old_image->drawImage($bg_draw);
					$bg_draw->clear();
					$bg_draw->destroy();
				}

				$align_normalized = strtolower($cfg['align']);

				// ----- Per-line rendering in shadow → stroke → main order -----
				foreach ($lines as $i => $line)
				{
					if ($line === '')
					{
						continue;
					}

					$line_w = $line_widths[$i];
					$line_x = match ($align_normalized) {
						'left'  => $block_x,
						'right' => $block_x + $block_width - $line_w,
						default => (int) ($block_x + ($block_width - $line_w) / 2),
					};

					// baseline of line i = first-line baseline + i * line_height.
					$baseline = $first_line_baseline + $i * $line_height;

					// 1. Shadow.
					if ($shadow_draw !== null)
					{
						$this->old_image->annotateImage(
							$shadow_draw,
							$line_x + $cfg['shadow']['offsetX'],
							$baseline + $cfg['shadow']['offsetY'],
							$imagick_angle,
							$line
						);
					}

					// 2. Stroke (transparent fill → outline only).
					if ($stroke_draw !== null)
					{
						$this->old_image->annotateImage(
							$stroke_draw,
							$line_x,
							$baseline,
							$imagick_angle,
							$line
						);
					}

					// 3. Main.
					$this->old_image->annotateImage(
						$main_draw,
						$line_x,
						$baseline,
						$imagick_angle,
						$line
					);
				}

				$main_draw->clear();
				$main_draw->destroy();
				if ($shadow_draw !== null)
				{
					$shadow_draw->clear();
					$shadow_draw->destroy();
				}
				if ($stroke_draw !== null)
				{
					$stroke_draw->clear();
					$stroke_draw->destroy();
				}

				return $this;
	}

	/**
	 * Resolves a font argument to an actual TTF/OTF file path on disk.
	 *
	 * Strategy (in order):
	 *
	 *  1. If null/empty, use fontconfig's system default (sans-serif) via fc-match.
	 *  2. If it looks like a path (contains '/' or ends with a font extension),
	 *     return it as-is if it exists, else fall through.
	 *  3. Otherwise treat it as a font family name; resolve via fontconfig.
	 *  4. If fontconfig isn't available, try Imagick::queryFonts() for the first
	 *     registered family name and use that. (Note: on Imagick 7, this name
	 *     may still not be resolvable by setFont without fontconfig — but it's
	 *     the best we can do.)
	 *
	 * @param string|null $font
	 * @return string Absolute path to a TTF/OTF file. Never returns null.
	 *                Falls back to whatever fontconfig gives us for sans-serif.
	 *
	 * @throws RuntimeException If no font can be resolved by any means.
	 */
	protected function resolveFont(?string $font): string
	{
		// Step 1: handle null/empty by deferring to fontconfig default.
		if ($font === null || $font === '')
		{
			return $this->resolveViaFontconfig('sans-serif')
			?? $this->resolveViaImagickQuery()
			?? $this->throwNoFontAvailable();
		}

		// Step 2: looks like a path? Use it if it exists.
		if ($this->looksLikeFontPath($font))
		{
			if (is_file($font))
			{
				return $font;
			}
			// Non-existent path → don't throw, fall through to default.
			return $this->resolveViaFontconfig('sans-serif')
				?? $this->resolveViaImagickQuery()
				?? $this->throwNoFontAvailable();
		}

		// Step 3: treat as a family name; ask fontconfig.
		$resolved = $this->resolveViaFontconfig($font);
		if ($resolved !== null)
		{
			return $resolved;
		}

		// Step 4: ask Imagick's own registry for that name. If it's there, the
		// string is itself usable by setFont() on this build. Otherwise fall back
		// to the system default.
		try
		{
			$registered = \Imagick::queryFonts();
			foreach ($registered as $family)
			{
				if (strcasecmp($family, $font) === 0)
				{
					// Found — return the family name itself; setFont() can resolve it
					// on legacy builds.
					return $family;
				}
			}
		}
		catch (\Throwable)
		{
			// queryFonts() unavailable on this build.
		}

		// Final fallback.
		return $this->resolveViaFontconfig('sans-serif')
		?? $this->throwNoFontAvailable();
	}

	/**
	 * Heuristic: does this string look like a filesystem path rather than a name?
	 */
	protected function looksLikeFontPath(string $font): bool
	{
		if (str_contains($font, '/') || str_contains($font, '\\'))
		{
			return true;
		}

		$lower = strtolower($font);
		foreach (['.ttf', '.otf', '.ttc'] as $ext)
		{
			if (str_ends_with($lower, $ext))
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolves a font family name to a TTF file path using fontconfig.
	 *
	 * @return string|null Absolute path, or null if fontconfig isn't available
	 *                      or doesn't recognize the family.
	 */
	protected function resolveViaFontconfig(string $family): ?string
	{
		if (!$this->fontconfigAvailable())
		{
			return null;
		}

		$cmd = 'fc-match -f "%{file}" ' . escapeshellarg($family) . ' 2>/dev/null';
		$path = trim((string) @shell_exec($cmd));

		if ($path === '' || !is_file($path))
		{
			return null;
		}

		return $path;
	}

	/**
	 * Picks the first family returned by Imagick::queryFonts() and returns it
	 * unchanged. setFont() will be called with this name; legacy ImageMagick
	 * builds can resolve it directly.
	 *
	 * @return string|null
	 */
	protected function resolveViaImagickQuery(): ?string
	{
		try
		{
			$registered = \Imagick::queryFonts();
		}
		catch (\Throwable)
		{
			return null;
		}

		if (empty($registered))
		{
			return null;
		}

		return $registered[0];
	}

	/**
	 * Checks if fontconfig (fc-match) is available on the system.
	 */
	protected function fontconfigAvailable(): bool
	{
		static $available = null;

		if ($available !== null)
		{
			return $available;
		}

		$path = trim((string) @shell_exec('command -v fc-match 2>/dev/null'));
		$available = ($path !== '');

		return $available;
	}

	/**
	 * @return never
	 */
	protected function throwNoFontAvailable(): string
	{
		throw new RuntimeException(
			'Imagick: no usable font could be located. ' .
			'Either install a system font (e.g. fonts-dejavu on Debian/Ubuntu), ' .
			'install fontconfig, or pass an explicit TTF file path in ' .
			'options.font.'
			);
	}

	/**
	 * Resolves the text() $options array against configured defaults.
	 */
	protected function resolveTextOptions(array $options): array
	{
		$size = $options['size']
		?? $this->options['textDefaultSize']
		?? 12;

		$color = $options['color'] ?? '#FFFFFF';
		$font  = $options['font']
		?? $this->options['textFont']
		?? null;

		$angle    = (float) ($options['angle']    ?? 0);
		$offset_x = (int)   ($options['offsetX']  ?? 10);
		$offset_y = (int)   ($options['offsetY']  ?? 10);
		$align    = (string)($options['align']    ?? 'center');
		$alpha    = max(0, min(100, (int) ($options['alpha'] ?? 100)));

		// Multi-line inter-baseline distance, expressed as a multiplier of
		// size. 1.0 = ascender + descender exactly (no gap), 1.2 = ~20%
		// gap, 1.5 = ~50% gap, 2.0 = doubles line spacing. The actual line
		// height also has a floor of (max ascender + max descender) across
		// all lines, so this value acts as a visual comfort margin ON TOP
		// of the measured height. Setting it to 1.0 disables the comfort
		// margin but still guarantees no overlap.
		$line_height = isset($options['lineHeight']) && $options['lineHeight'] > 0
			? (float) $options['lineHeight']
			: 1.3;

		$shadow = array_merge(
			['enabled' => false, 'color' => '#000000', 'offsetX' => 1, 'offsetY' => 1, 'blur' => 0],
			$options['shadow'] ?? []
			);
		$stroke = array_merge(
			['enabled' => false, 'color' => '#000000', 'width' => 1],
			$options['stroke'] ?? []
			);
		$background = array_merge(
			['enabled' => false, 'color' => '#000000', 'padding' => 4, 'alpha' => 75],
			$options['background'] ?? []
			);

		// NOTE: keep the array keys in camelCase — they are part of the public
		// text() options API. Local variables use snake_case for consistency
		// with the rest of the codebase.
		return [
			'size'       => $size,
			'color'      => $color,
			'font'       => $font,
			'angle'      => $angle,
			'offsetX'    => $offset_x,
			'offsetY'    => $offset_y,
			'align'      => $align,
			'alpha'      => $alpha,
			'lineHeight' => $line_height,
			'shadow'     => $shadow,
			'stroke'     => $stroke,
			'background' => $background,
		];
	}

	/**
	 * Parses a position keyword into a [h, v] anchor tuple.
	 *
	 * @return array{0: string, 1: string}
	 */
	protected function resolveTextAnchor(string $position): array
	{
		$p = strtolower(trim($position));

		$h = 'center';
		if (str_contains($p, 'left') || str_contains($p, 'west'))
		{
			$h = 'left';
		}
		elseif (str_contains($p, 'right') || str_contains($p, 'east'))
		{
			$h = 'right';
		}

		$v = 'center';
		if (str_contains($p, 'top') || str_contains($p, 'north') || str_contains($p, 'upper'))
		{
			$v = 'top';
		}
		elseif (str_contains($p, 'bottom') || str_contains($p, 'south') || str_contains($p, 'lower'))
		{
			$v = 'bottom';
		}

		if ($h === 'center' && $v === 'center' && $p !== 'center')
		{
			throw new InvalidArgumentException(
				"Unknown text() position: '$position'. " .
				"Expected 'top-left', 'top', 'top-right', 'left', 'center', 'right', " .
				"'bottom-left', 'bottom', or 'bottom-right' (aliases: " .
				"'northwest'/'northeast'/'southwest'/'southeast', " .
				"'north'/'south', 'west'/'east')."
				);
		}

		return [$h, $v];
	}

	/**
	 * Given the canvas size, text bounding box, and anchor, returns the top-left
	 * (x, y) at which the text should be drawn.
	 *
	 * @param array{0: string, 1: string} $anchor
	 */
	protected function computeTextTopLeft(
		array $anchor,
		int $offset_x, int $offset_y,
		int $canvas_w, int $canvas_h,
		int $text_w, int $text_h
		): array
	{
		[$h_anchor, $v_anchor] = $anchor;

		$x = match ($h_anchor) {
			'left'   => $offset_x,
			'right'  => $canvas_w - $text_w - $offset_x,
			default  => (int) (($canvas_w - $text_w) / 2),
		};

		$y = match ($v_anchor) {
			'top'    => $offset_y,
			'bottom' => $canvas_h - $text_h - $offset_y,
			default => (int) (($canvas_h - $text_h) / 2),
		};

		return [$x, $y];
	}

	/**
	 * Check if the image can be scaled up
	 */
	private function checkingMaxSize(int $max_width, int $max_height): void
	{
		if ($this->options['resizeUp'] === false)
		{
			$this->max_height = ($max_height > $this->current_dimensions['height']) ? $this->current_dimensions['height'] : $max_height;
			$this->max_width  = ($max_width > $this->current_dimensions['width'])  ? $this->current_dimensions['width']  : $max_width;
		}
		else
		{
			$this->max_height = $max_height;
			$this->max_width  = $max_width;
		}
	}

	/**
	 * Resizes an image to be no larger than $max_width or $max_height
	 */
	public function resize(int $max_width = 0, int $max_height = 0): Imagick
	{
		$this->checkingMaxSize($max_width, $max_height);
		$this->calcImageSize($this->current_dimensions['width'], $this->current_dimensions['height']);

		$this->old_image->thumbnailImage(
			$this->new_dimensions['new_width'],
			$this->new_dimensions['new_height'],
			false
			);
		$this->resetImagePage();

		$this->current_dimensions['width']  = $this->new_dimensions['new_width'];
		$this->current_dimensions['height'] = $this->new_dimensions['new_height'];

		return $this;
	}

	/**
	 * Adaptively Resizes the Image
	 */
	public function adaptiveResize(int $width, int $height): Imagick
	{
		if ($width == 0 && $height == 0)
		{
			throw new InvalidArgumentException('$width and $height must be numeric and greater than zero');
		}

		if ($width == 0)
		{
			$width = intval(($height * $this->current_dimensions['width']) / $this->current_dimensions['height']);
		}

		if ($height == 0)
		{
			$height = intval(($width * $this->current_dimensions['height']) / $this->current_dimensions['width']);
		}

		$this->checkingMaxSize($width, $height);
		$this->calcImageSizeStrict($this->current_dimensions['width'], $this->current_dimensions['height']);

		$resize_width  = $this->new_dimensions['new_width'];
		$resize_height = $this->new_dimensions['new_height'];

		$this->old_image->thumbnailImage($resize_width, $resize_height, false);

		$this->checkingMaxSize($width, $height);

		$crop_width  = $this->max_width;
		$crop_height = $this->max_height;
		$crop_x      = 0;
		$crop_y      = 0;

		if ($this->current_dimensions['width'] > $this->max_width)
		{
			$crop_x = intval(($this->current_dimensions['width'] - $this->max_width) / 2);
		}
		else if ($this->current_dimensions['height'] > $this->max_height)
		{
			$crop_y = intval(($this->current_dimensions['height'] - $this->max_height) / 2);
		}

		$this->old_image->cropImage($crop_width, $crop_height, $crop_x, $crop_y);
		$this->resetImagePage();

		$this->current_dimensions['width']  = $crop_width;
		$this->current_dimensions['height'] = $crop_height;

		return $this;
	}

	/**
	 * Adaptively Resizes the Image and Crops Using a Percentage
	 */
	public function adaptiveResizePercent(int $width, int $height, int $percent = 50): Imagick
	{
		if ($width == 0)
		{
			throw new InvalidArgumentException('$width must be numeric and greater than zero');
		}

		if ($height == 0)
		{
			throw new InvalidArgumentException('$height must be numeric and greater than zero');
		}

		$this->checkingMaxSize($width, $height);
		$this->calcImageSizeStrict($this->current_dimensions['width'], $this->current_dimensions['height']);

		$resize_width  = $this->new_dimensions['new_width'];
		$resize_height = $this->new_dimensions['new_height'];

		$this->old_image->thumbnailImage($resize_width, $resize_height, false);

		$this->checkingMaxSize($width, $height);

		$crop_width  = $this->max_width;
		$crop_height = $this->max_height;
		$crop_x      = 0;
		$crop_y      = 0;

		if ($percent > 100)
		{
			$percent = 100;
		}
		else if ($percent < 1)
		{
			$percent = 1;
		}

		if ($this->current_dimensions['width'] > $this->max_width)
		{
			$max_crop_x = $this->current_dimensions['width'] - $this->max_width;
			$crop_x = intval(($percent / 100) * $max_crop_x);
		}
		else if ($this->current_dimensions['height'] > $this->max_height)
		{
			$max_crop_y = $this->current_dimensions['height'] - $this->max_height;
			$crop_y = intval(($percent / 100) * $max_crop_y);
		}

		$this->old_image->cropImage($crop_width, $crop_height, $crop_x, $crop_y);
		$this->resetImagePage();

		$this->current_dimensions['width']  = $crop_width;
		$this->current_dimensions['height'] = $crop_height;

		return $this;
	}

	/**
	 * Adaptively Resizes the Image and Crops Using a Quadrant
	 */
	public function adaptiveResizeQuadrant(int $width, int $height, string $quadrant = 'C'): Imagick
	{
		if ($width == 0)
		{
			throw new InvalidArgumentException('$width must be numeric and greater than zero');
		}

		if ($height == 0)
		{
			throw new InvalidArgumentException('$height must be numeric and greater than zero');
		}

		$this->checkingMaxSize($width, $height);
		$this->calcImageSizeStrict($this->current_dimensions['width'], $this->current_dimensions['height']);

		$resize_width  = $this->new_dimensions['new_width'];
		$resize_height = $this->new_dimensions['new_height'];

		$this->old_image->thumbnailImage($resize_width, $resize_height, false);

		$this->checkingMaxSize($width, $height);

		$crop_width  = $this->max_width;
		$crop_height = $this->max_height;
		$crop_x      = 0;
		$crop_y      = 0;

		if ($this->current_dimensions['width'] > $this->max_width)
		{
			$crop_x = match ($quadrant) {
				'L'     => 0,
				'R'     => intval(($this->current_dimensions['width'] - $this->max_width)),
				default => intval(($this->current_dimensions['width'] - $this->max_width) / 2),
			};
		}
		else if ($this->current_dimensions['height'] > $this->max_height)
		{
			$crop_y = match ($quadrant) {
				'T'     => 0,
				'B'     => intval(($this->current_dimensions['height'] - $this->max_height)),
				default => intval(($this->current_dimensions['height'] - $this->max_height) / 2),
			};
		}

		$this->old_image->cropImage($crop_width, $crop_height, $crop_x, $crop_y);
		$this->resetImagePage();

		$this->current_dimensions['width']  = $crop_width;
		$this->current_dimensions['height'] = $crop_height;

		return $this;
	}

	/**
	 * Resizes an image by a given percent uniformly
	 */
	public function resizePercent(int $percent = 0): Imagick
	{
		$this->percent = $percent;

		$this->calcImageSizePercent($this->current_dimensions['width'], $this->current_dimensions['height']);

		return $this->resize($this->new_dimensions['new_width'], $this->new_dimensions['new_height']);
	}

	/**
	 * Crops an image from the center with provided dimensions
	 */
	public function cropFromCenter(int $crop_width, ?int $crop_height = 0): Imagick
	{
		if ($crop_height == 0)
		{
			$crop_height = $crop_width;
		}

		$crop_width  = ($this->current_dimensions['width'] < $crop_width)  ? $this->current_dimensions['width']  : $crop_width;
		$crop_height = ($this->current_dimensions['height'] < $crop_height) ? $this->current_dimensions['height'] : $crop_height;

		$crop_x = intval(($this->current_dimensions['width'] - $crop_width) / 2);
		$crop_y = intval(($this->current_dimensions['height'] - $crop_height) / 2);

		$this->crop($crop_x, $crop_y, $crop_width, $crop_height);

		return $this;
	}

	/**
	 * Vanilla Cropping - Crops from x,y with specified width and height
	 */
	public function crop(int $start_x, int $start_y, int $crop_width, int $crop_height): Imagick
	{
		$crop_width  = ($this->current_dimensions['width'] < $crop_width)  ? $this->current_dimensions['width']  : $crop_width;
		$crop_height = ($this->current_dimensions['height'] < $crop_height) ? $this->current_dimensions['height'] : $crop_height;

		if (($start_x + $crop_width) > $this->current_dimensions['width'])
		{
			$start_x = ($this->current_dimensions['width'] - $crop_width);
		}

		if (($start_y + $crop_height) > $this->current_dimensions['height'])
		{
			$start_y = ($this->current_dimensions['height'] - $crop_height);
		}

		if ($start_x < 0)
		{
			$start_x = 0;
		}

		if ($start_y < 0)
		{
			$start_y = 0;
		}

		$this->old_image->cropImage($crop_width, $crop_height, $start_x, $start_y);
		$this->resetImagePage();

		$this->current_dimensions['width']  = $crop_width;
		$this->current_dimensions['height'] = $crop_height;

		return $this;
	}

	/**
	 * Rotates image either 90 degrees clockwise or counter-clockwise
	 */
	public function rotateImage(string $direction = 'CW'): Imagick
	{
		$degrees = match ($direction) {
			'CW'    => 90,
			default => -90,
		};

		$this->rotateImageNDegrees($degrees);

		return $this;
	}

	/**
	 * Rotates image specified number of degrees
	 */
	public function rotateImageNDegrees(int $degrees): Imagick
	{
		$background_color = new ImagickPixel('rgb(' . $this->options['alphaMaskColor'][0] . ', ' . $this->options['alphaMaskColor'][1] . ', ' . $this->options['alphaMaskColor'][2] . ')');

		$this->old_image->rotateImage($background_color, $degrees);
		$this->resetImagePage();

		$background_color->destroy();

		$this->current_dimensions['width']  = $this->old_image->getImageWidth();
		$this->current_dimensions['height'] = $this->old_image->getImageHeight();

		return $this;
	}

	/**
	 * Flips / mirrors the image.
	 *
	 * Accepts the following direction keywords (case-insensitive):
	 *
	 *  - 'horizontal' (alias: 'h', 'lr') — mirror left ↔ right (default)
	 *  - 'vertical'   (alias: 'v', 'tb') — mirror top ↔ bottom
	 *  - 'both'       (alias: 'hv')      — mirror both axes (equivalent to 180° rotation)
	 *
	 * Internally uses Imagick::flopImage() (horizontal) and Imagick::flipImage()
	 * (vertical). Dimensions are unchanged.
	 *
	 * @param string $direction The flip direction (see above).
	 * @return $this
	 *
	 * @throws InvalidArgumentException For an unknown direction string.
	 */
	public function flip(string $direction = 'horizontal'): Imagick
	{
		$normalized = strtolower(trim($direction));

		switch ($normalized)
		{
			case 'horizontal':
			case 'h':
			case 'lr':
				$this->old_image->flopImage();
				break;

			case 'vertical':
			case 'v':
			case 'tb':
				$this->old_image->flipImage();
				break;

			case 'both':
			case 'hv':
				$this->old_image->flopImage();
				$this->old_image->flipImage();
				break;

			default:
				throw new InvalidArgumentException(
				"Unknown flip direction: '{$direction}'. " .
				"Expected 'horizontal', 'vertical', or 'both'."
					);
		}

		return $this;
	}

	/**
	 * Auto-orients the image based on its EXIF orientation tag.
	 *
	 * Delegates to Imagick::autoOrient(), which handles all eight EXIF
	 * orientation cases natively (rotate + flip combinations) for JPEG,
	 * TIFF, HEIC, and any other format that carries EXIF metadata.
	 *
	 * The current dimensions are refreshed after the operation. The method
	 * is a no-op for images without orientation metadata (orientation =
	 * TOPLEFT or UNDEFINED).
	 *
	 * @return $this
	 */
	public function autoOrient(): Imagick
	{
		// Read current orientation. UNDEFINED and TOPLEFT are "already correct".
		$orientation = $this->old_image->getImageOrientation();

		if ($orientation === \Imagick::ORIENTATION_UNDEFINED
			|| $orientation === \Imagick::ORIENTATION_TOPLEFT)
		{
			return $this;
		}

		// Imagick::autoOrient() rewrites the pixel buffer AND resets the
		// orientation tag to TOPLEFT, so calling it twice is safe and idempotent.
		$this->old_image->autoOrient();

		// Refresh dimensions — rotation may have changed them.
		$this->current_dimensions = [
			'width'  => $this->old_image->getImageWidth(),
			'height' => $this->old_image->getImageHeight(),
		];

		return $this;
	}

	/**
	 * Applies gamma correction to the image.
	 *
	 * Wraps Imagick::gammaImage(). Values below 1.0 darken the image,
	 * values above 1.0 brighten. Realistic sRGB adjustment is in the 0.5–2.0 range.
	 *
	 * The image dimensions are unchanged.
	 *
	 * @param float $correction The output gamma (1.0 = no change).
	 * @return $this
	 */
	public function gamma(float $correction): Imagick
	{
		$this->old_image->gammaImage($correction);

		return $this;
	}

	/**
	 * Sharpens the image using an unsharp-mask style Gaussian.
	 *
	 * Wraps Imagick::sharpenImage() with a radius of 1.0 and a sigma
	 * mapped from $amount in the [0, 2] range. Higher values produce
	 * a stronger sharpening effect.
	 *
	 *  - $amount = 0   → no-op (skipped)
	 *  - $amount = 50  → sigma ~1.0 (default)
	 *  - $amount = 100 → sigma ~2.0 (strongest)
	 *
	 * Dimensions are unchanged.
	 *
	 * @param int $amount Sharpening strength, 0–100.
	 * @return $this
	 *
	 * @throws InvalidArgumentException For out-of-range amounts.
	 */
	public function sharpen(int $amount = 50): Imagick
	{
		if ($amount < 0 || $amount > 100)
		{
			throw new InvalidArgumentException(
				'sharpen() $amount must be between 0 and 100, got: ' . $amount
				);
		}

		if ($amount === 0)
		{
			return $this; // no-op
		}

		// Map $amount (0..100) → sigma (0..2.0)
		$sigma = ($amount / 100.0) * 2.0;

		$this->old_image->sharpenImage(1.0, $sigma);

		return $this;
	}

	/**
	 * Applies a spatial filter to the image.
	 *
	 * This is a compatibility layer over the legacy ImageMagick 6
	 * MagickFilterImage() filter family. ImageMagick 7 removed that API
	 * (and ext-imagick 3.5+ therefore removed Imagick::filter() and the
	 * Imagick::FILTER_* constants), so the filters are re-implemented here
	 * on top of primitives that exist in *both* ImageMagick 6 and 7:
	 *
	 *   EDGEDETECT       → Imagick::edgeImage()
	 *   EMBOSS           → Imagick::embossImage()
	 *   GAUSSIAN         → Imagick::gaussianBlurImage()
	 *   UNSHARP          → Imagick::sharpenImage()
	 *   SOBEL           ┐
	 *   PREWITT         │
	 *   ROBERTS         ├→ Imagick::convolveImage() with a 3×3 kernel
	 *   LAPLACE         │
	 *   LAPLACE_ROBERTS │
	 *   OCTAGON         ┘
	 *   POINT           → no-op (IM6's PointFilter replaces each pixel
	 *                     with itself)
	 *
	 * CELLULAR has no ImageMagick 7 equivalent and is rejected.
	 *
	 * The optional arguments are interpreted as radius and sigma for the
	 * filters that take them (EDGEDETECT, EMBOSS, GAUSSIAN, UNSHARP) and
	 * are ignored by the pure-convolution filters.
	 *
	 * @param int $filter One of the self::FILTER_* constants.
	 * @return $this
	 *
	 * @throws InvalidArgumentException For an unknown filter id, or for a
	 *                                  filter that cannot be emulated.
	 */
	public function imageFilter(int $filter, int|float|bool $arg1 = false, int|float|bool $arg2 = false, int|float|bool $arg3 = false, int|float|bool $arg4 = false): Imagick
	{
		// 'false' means "argument not supplied"; any other value — including
		// 0 — is a real argument.
		$radius = ($arg1 !== false) ? max(0.0, (float) $arg1) : 1.0;
		$sigma  = ($arg2 !== false) ? max(0.0, (float) $arg2) : 1.0;

		switch ($filter)
		{
			case self::FILTER_POINT:
				// IM6's PointFilter copies each pixel onto itself.
				break;

			case self::FILTER_CELLULAR:
				throw new InvalidArgumentException(
					'The CELLULAR filter has no ImageMagick 7 equivalent and ' .
					'cannot be emulated.'
					);

			case self::FILTER_EDGEDETECT:
				// edgeImage() takes an integer radius.
				$this->old_image->edgeImage((int) round($radius));
				break;

			case self::FILTER_EMBOSS:
				// embossImage() rejects a zero sigma.
				$this->old_image->embossImage((int) round($radius), max(0.1, $sigma));
				break;

			case self::FILTER_GAUSSIAN:
				$this->old_image->gaussianBlurImage($radius, $sigma);
				break;

			case self::FILTER_UNSHARP:
				// sharpenImage() rejects a zero sigma.
				$this->old_image->sharpenImage($radius, max(0.1, $sigma));
				break;

			case self::FILTER_LAPLACE:
			case self::FILTER_LAPLACE_ROBERTS:
			case self::FILTER_OCTAGON:
			case self::FILTER_PREWITT:
			case self::FILTER_ROBERTS:
			case self::FILTER_SOBEL:
				$this->applyConvolutionKernel(self::legacyFilterKernels()[$filter]);
				break;

			default:
				throw new InvalidArgumentException(
					'Unknown Imagick filter: ' . $filter . '. Expected one of the ' .
					'FILTER_* constants (' . implode(', ', self::legacyFilterIds()) . ').'
					);
		}

		return $this;
	}

	/**
	 * Returns every filter id that imageFilter() can emulate.
	 *
	 * @return int[]
	 */
	public static function legacyFilterIds(): array
	{
		return [
			self::FILTER_POINT,
			self::FILTER_EDGEDETECT,
			self::FILTER_EMBOSS,
			self::FILTER_GAUSSIAN,
			self::FILTER_LAPLACE,
			self::FILTER_LAPLACE_ROBERTS,
			self::FILTER_OCTAGON,
			self::FILTER_PREWITT,
			self::FILTER_ROBERTS,
			self::FILTER_SOBEL,
			self::FILTER_UNSHARP,
		];
	}

	/**
	 * The 3×3 convolution kernels used by the pure-convolution filters.
	 *
	 * These are the textbook equivalents of the kernels ImageMagick 6
	 * hard-coded in MagickFilterImage(). The convolution is deliberately
	 * unnormalised (scale 1, offset 0) so the output is the raw kernel
	 * response — the standard definition of an edge-detection convolution.
	 *
	 * @return array<int, array<int, array<int, int>>>
	 */
	protected static function legacyFilterKernels(): array
	{
		return [
			// 4-neighbour Laplacian.
			self::FILTER_LAPLACE => [
				[0, 1, 0],
				[1, -4, 1],
				[0, 1, 0],
			],
			// 8-neighbour Laplacian (Laplacian of Roberts).
			self::FILTER_LAPLACE_ROBERTS => [
				[1, -1, 1],
				[-1, 8, -1],
				[1, -1, 1],
			],
			// Roberts cross.
			self::FILTER_ROBERTS => [
				[1, 0, 0],
				[0, -1, 0],
				[0, 0, 0],
			],
			// Prewitt.
			self::FILTER_PREWITT => [
				[-1, 0, 1],
				[-1, 0, 1],
				[-1, 0, 1],
			],
			// Sobel.
			self::FILTER_SOBEL => [
				[-1, 0, 1],
				[-2, 0, 2],
				[-1, 0, 1],
			],
			// Octagon (a.k.a. "LoG"-style 8-neighbour Laplacian).
			self::FILTER_OCTAGON => [
				[0, -1, 0],
				[-1, 8, -1],
				[0, -1, 0],
			],
		];
	}

	/**
	 * Applies a 3×3 convolution kernel to the working image.
	 *
	 * Imagick::convolveImage() is available in ext-imagick for both
	 * ImageMagick 6 and 7, which makes it the portable replacement for the
	 * removed MagickFilterImage() spatial filters. Its signature has
	 * changed twice, though:
	 *
	 *   - ext-imagick ≤ 3.6 : convolveImage(array $matrix, int $scale[, int $offset])
	 *   - ext-imagick ≥ 3.7 : convolveImage(ImagickKernel $kernel, int $channel = null)
	 *
	 * ext-imagick 3.7 re-typed the first parameter to ImagickKernel and
	 * dropped the matrix overload; the scale/offset moved into
	 * ImagickKernel::scale(). On some builds (ext-imagick 3.8.1 with
	 * ImageMagick 7.1.2) a kernel constructed from a PHP matrix comes back
	 * *empty* — getMatrix() and convolveImage() both throw "ImagickKernel
	 * is empty, cannot be used" — so the native path is not always usable.
	 *
	 * The kernel is therefore applied in PHP when the native path is
	 * unavailable. That path is deterministic and produces the same result
	 * on every build, which is what the legacy IM6 filters did.
	 *
	 * @param array<int, array<int, float|int>> $kernel A square 2-D kernel.
	 *
	 * @throws RuntimeException If neither the native nor the PHP path works.
	 */
	protected function applyConvolutionKernel(array $kernel): void
	{
		$errors = [];

		// ----- Native attempt: Imagick::convolveImage() ---------------------
		if (method_exists(\Imagick::class, 'convolveImage'))
		{
			$variants = self::imagickKernelVariants($kernel);

			if ($variants === [])
			{
				$errors[] = 'No ImagickKernel could be constructed from the filter matrix.';
			}

			// The second parameter is a channel mask (not a scale) on the
			// modern binding, and absent on the older one — both shapes are
			// attempted.
			$shapes = [
				'kernel'         => static function ($k) { return [$k]; },
				'kernel,channel' => static function ($k) { return [$k, self::imagickChannelAll()]; },
			];

			foreach ($variants as $label => $kernel_object)
			{
				foreach ($shapes as $shape_label => $shape)
				{
					try
					{
						$this->old_image->convolveImage(...$shape($kernel_object));

						return;
					}
					catch (\Throwable $e)
					{
						$errors[] = 'convolveImage(' . $label . ', ' . $shape_label . '): '
							. get_class($e) . ': ' . $e->getMessage();
					}
				}
			}
		}
		else
		{
			$errors[] = 'Imagick::convolveImage() is not available in this ext-imagick build.';
		}

		// ----- PHP fallback ---------------------------------------------------
		try
		{
			$this->applyConvolutionKernelInPhp($kernel);

			return;
		}
		catch (\Throwable $e)
		{
			$errors[] = 'PHP fallback: ' . get_class($e) . ': ' . $e->getMessage();
		}

		throw new RuntimeException(
			'Could not apply the convolution filter. Attempts: ' . implode(' | ', $errors)
			);
	}

	/**
	 * Builds every ImagickKernel variant that can be constructed from a
	 * filter matrix.
	 *
	 * ext-imagick 3.7+ accepts an ImagickKernel instead of a raw matrix.
	 * The constructor cannot be introspected — ImagickKernel is an internal
	 * class whose __construct is not registered in a way ReflectionMethod
	 * can see — so the argument list is determined by trial.
	 *
	 * Note that a variant may construct successfully and still be *empty* on
	 * some builds; that is detected by the caller, which reports the
	 * failure and falls back to the PHP implementation.
	 *
	 * @param array<int, array<int, float|int>> $matrix
	 *
	 * @return array<string, \ImagickKernel>
	 */
	protected static function imagickKernelVariants(array $matrix): array
	{
		if (!class_exists(\ImagickKernel::class))
		{
			return [];
		}

		$candidates = [
			'matrix,Convolution' => [$matrix, 'Convolution'],
			'matrix,convolution' => [$matrix, 'convolution'],
			'matrix,Correlation'  => [$matrix, 'Correlation'],
			'matrix'              => [$matrix],
			'floats,Convolution'  => [self::toFloatMatrix($matrix), 'Convolution'],
		];

		$variants = [];

		foreach ($candidates as $label => $arguments)
		{
			try
			{
				$variants[$label] = new \ImagickKernel(...$arguments);
			}
			catch (\Throwable $e)
			{
				// This shape is not supported by the installed build.
			}
		}

		return $variants;
	}

	/**
	 * Converts every kernel weight to a float.
	 *
	 * @param array<int, array<int, float|int>> $matrix
	 *
	 * @return array<int, array<int, float>>
	 */
	protected static function toFloatMatrix(array $matrix): array
	{
		$converted = [];

		foreach ($matrix as $row)
		{
			$converted_row = [];
			foreach ($row as $value)
			{
				$converted_row[] = (float) $value;
			}
			$converted[] = $converted_row;
		}

		return $converted;
	}

	/**
	 * Returns the "all channels" mask for the installed ext-imagick.
	 */
	protected static function imagickChannelAll(): int
	{
		if (defined('Imagick::CHANNEL_ALL'))
		{
			return (int) constant('Imagick::CHANNEL_ALL');
		}

		return 0x7fffffff;
	}

	/**
	 * Applies a convolution kernel to the working image in pure PHP.
	 *
	 * This is the primary implementation on builds where the native
	 * Imagick::convolveImage() cannot be used (see the class-level notes on
	 * applyConvolutionKernel()).
	 *
	 * The frame is pulled into PHP once via exportImagePixels() and written
	 * back via importImagePixels(), so the image geometry is unchanged and
	 * the per-pixel cost is a single array lookup rather than an
	 * Imagick::getImagePixelColor() call.
	 *
	 * @param array<int, array<int, float|int>> $kernel
	 */
	protected function applyConvolutionKernelInPhp(array $kernel): void
	{
		$width  = $this->old_image->getImageWidth();
		$height = $this->old_image->getImageHeight();

		if ($width < 1 || $height < 1)
		{
			return;
		}

		$frame = $this->exportFrame($width, $height);

		if ($frame === null)
		{
			throw new RuntimeException(
				'exportImagePixels() did not return a usable frame for a '
				. $width . 'x' . $height . ' image.'
				);
		}

		[$values, $channels, $map, $prefer_array] = $frame;

		$kernel_size = count($kernel);
		$half        = intdiv($kernel_size, 2);

		$output = [];

		for ($y = 0; $y < $height; $y++)
		{
			for ($x = 0; $x < $width; $x++)
			{
				$r = $g = $b = 0.0;

				for ($ky = 0; $ky < $kernel_size; $ky++)
				{
					$sy = $y + $ky - $half;

					if ($sy < 0 || $sy >= $height)
					{
						continue;
					}

					$row = $kernel[$ky];

					for ($kx = 0; $kx < $kernel_size; $kx++)
					{
						$sx = $x + $kx - $half;

						if ($sx < 0 || $sx >= $width)
						{
							continue;
						}

						$weight = (float) $row[$kx];

						if ($weight === 0.0)
						{
							continue;
						}

						$index = (($sy * $width) + $sx) * $channels;

						$r += $weight * (float) $values[$index];
						$g += $weight * (float) $values[$index + 1];
						$b += $weight * (float) $values[$index + 2];
					}
				}

				$source_index = (($y * $width) + $x) * $channels;

				$output[] = self::clampQuantum($r);
				$output[] = self::clampQuantum($g);
				$output[] = self::clampQuantum($b);

				if ($channels === 4)
				{
					// The alpha channel is copied through untouched.
					$output[] = (int) $values[$source_index + 3];
				}
			}
		}

		$this->importFrame($width, $height, $output, $channels, $map, $prefer_array);
	}

	/**
	 * Reads the frame as a flat list of integer channel values.
	 *
	 * exportImagePixels() behaves differently between ext-imagick
	 * generations: it may return a binary string or an array of integers,
	 * and the number of values written per pixel depends on the channels
	 * the image actually carries rather than on the requested map (an RGB
	 * JPEG asked for an 'RGBA' map comes back with three values per
	 * pixel). The channel count is therefore derived from the data itself.
	 *
	 * @return array{0: array<int, int>, 1: int, 2: string, 3: bool}|null
	 *         [values, channels, import map, prefer array payload] — or null
	 *         when no map produced a usable frame.
	 */
	protected function exportFrame(int $width, int $height): ?array
	{
		$pixel_count = $width * $height;

		if ($pixel_count < 1)
		{
			return null;
		}

		foreach (['RGBA', 'RGB'] as $map)
		{
			try
			{
				$data = $this->old_image->exportImagePixels(
					0,
					0,
					$width,
					$height,
					$map,
					\Imagick::PIXEL_CHAR
					);
			}
			catch (\Throwable $e)
			{
				continue;
			}

			if (is_string($data))
			{
				$values = unpack('C*', $data);
				$values = ($values === false) ? [] : array_values($values);
				$prefer_array = false;
			}
			elseif (is_array($data))
			{
				$values = array_values(array_map('intval', $data));
				$prefer_array = true;
			}
			else
			{
				continue;
			}

			if ($values === [] || (count($values) % $pixel_count) !== 0)
			{
				continue;
			}

			$channels = intdiv(count($values), $pixel_count);

			if (!in_array($channels, [3, 4], true))
			{
				continue;
			}

			// Some builds ignore the requested 8-bit pixel type and hand
			// back 16-bit quantum values. Scale them down so the payload
			// matches the 8-bit import.
			$maximum = max($values);

			if ($maximum > 255)
			{
				$scale = 255 / $maximum;
				foreach ($values as $index => $value)
				{
					$values[$index] = (int) round($value * $scale);
				}
			}

			// The import map is derived from the data, not from the map that
			// was requested, so the two always agree.
			$import_map = ($channels === 4) ? 'RGBA' : 'RGB';

			return [$values, $channels, $import_map, $prefer_array];
		}

		return null;
	}

	/**
	 * Writes the frame back into the working image.
	 *
	 * importImagePixels() takes seven arguments — x, y, width, height, map,
	 * pixel type and the payload — and accepts the payload either as a
	 * binary string or as an array of integers, depending on the build.
	 *
	 * @param array<int, int> $values
	 */
	protected function importFrame(
		int $width,
		int $height,
		array $values,
		int $channels,
		string $map,
		bool $prefer_array
		): void
	{
		$payloads = [];

		if ($prefer_array)
		{
			$payloads['array']  = $values;
			$payloads['string'] = self::packValues($values);
		}
		else
		{
			$payloads['string'] = self::packValues($values);
			$payloads['array']  = $values;
		}

		$errors = [];

		foreach ($payloads as $label => $payload)
		{
			try
			{
				$result = $this->old_image->importImagePixels(
					0,
					0,
					$width,
					$height,
					$map,
					\Imagick::PIXEL_CHAR,
					$payload
					);

				if ($result !== false)
				{
					return;
				}

				$errors[] = $label . ': importImagePixels() returned false';
			}
			catch (\Throwable $e)
			{
				$errors[] = $label . ': ' . get_class($e) . ': ' . $e->getMessage();
			}
		}

		throw new RuntimeException(
			'importImagePixels() failed for a ' . $width . 'x' . $height
			. ' frame using the ' . $map . ' map: ' . implode(' | ', $errors)
			);
	}

	/**
	 * Packs a list of channel values into a binary string.
	 *
	 * @param array<int, int> $values
	 */
	protected static function packValues(array $values): string
	{
		$bytes = '';

		foreach ($values as $value)
		{
			$bytes .= chr($value & 0xFF);
		}

		return $bytes;
	}

	/**
	 * Clamps a computed channel value to the 0..255 range.
	 */
	protected static function clampQuantum(float $value): int
	{
		if ($value <= 0)
		{
			return 0;
		}

		if ($value >= 255)
		{
			return 255;
		}

		return (int) round($value);
	}

	/**
	 * Desaturates the image (chainable convenience wrapper).
	 *
	 * Implemented via Imagick::modulateImage() with saturation = 0 so the
	 * colorspace is preserved (a true colorspace conversion via
	 * setImageColorspace(GRAY) can produce unexpected results on
	 * subsequent save()/show() calls for some formats).
	 */
	public function grayscale(): Imagick
	{
		$this->old_image->modulateImage(100, 0, 100);

		return $this;
	}

	/**
	 * Adjusts brightness (chainable convenience wrapper).
	 *
	 * Implemented via Imagick::modulateImage(). Imagick's brightness scale
	 * is centered on 100 (no change); 0 = black, 200 = double. We map the
	 * caller-supplied $level (which is centered on 0, like GD's) to that
	 * scale by adding 100.
	 *
	 * @param int $level Brightness offset. 0 = no change. Positive brightens,
	 *                   negative darkens. Typical range: -50..50.
	 */
	public function brightness(int $level): Imagick
	{
		$imagick_level = 100 + $level;
		// Clamp to a sane range. Imagick accepts negatives but the visible
		// effect becomes all-black well before that.
		$imagick_level = max(0, $imagick_level);

		$this->old_image->modulateImage($imagick_level, 100, 100);

		return $this;
	}

	/**
	 * Adjusts contrast (chainable convenience wrapper).
	 *
	 * Implemented via Imagick::contrastImage(). Positive $level increases
	 * contrast; negative $level decreases it. The magnitude of the effect
	 * is not 1:1 with GD's IMG_FILTER_CONTRAST — this is a relative
	 * adjustment, not a calibrated one.
	 *
	 * @param int $level Contrast offset. 0 = no change.
	 */
	public function contrast(int $level): Imagick
	{
		$this->old_image->contrastImage($level);

		return $this;
	}

	/**
	 * Applies a Gaussian blur (chainable convenience wrapper).
	 *
	 * Implemented via Imagick::gaussianBlurImage(). A radius of 0 lets
	 * Imagick pick a sensible default; sigma controls the blur strength.
	 *
	 * @param int|float $amount Blur sigma. Typical range 0..10.
	 */
	public function blur(int|float $amount = 1): Imagick
	{
		$this->old_image->gaussianBlurImage(0, $amount);

		return $this;
	}

	/**
	 * Applies a pixelation effect (chainable convenience wrapper).
	 *
	 * Imagick has no native pixelate filter, so we emulate it by scaling the
	 * image down to $blockSize-pixel blocks and then back to the original
	 * dimensions. The visual result is the same as GD's IMG_FILTER_PIXELATE.
	 *
	 * @param int $block_size Pixel block size in pixels. Must be >= 1.
	 *                        $block_size = 1 is a no-op.
	 */
	public function pixelate(int $block_size = 10): Imagick
	{
		if ($block_size < 1)
		{
			throw new InvalidArgumentException(
				'pixelate() $block_size must be >= 1, got: ' . $block_size
				);
		}

		if ($block_size === 1)
		{
			return $this; // no-op
		}

		$w = $this->old_image->getImageWidth();
		$h = $this->old_image->getImageHeight();

		$small_w = max(1, (int) floor($w / $block_size));
		$small_h = max(1, (int) floor($h / $block_size));

		// Scale down to the block grid, then scale back up. The resulting
		// pixelation looks the same as a native pixelate filter.
		$this->old_image->scaleImage($small_w, $small_h);
		$this->old_image->scaleImage($w, $h);

		// Refresh dimensions — they should be unchanged after the round-trip,
		// but be defensive about any rounding differences.
		$this->current_dimensions = [
			'width'  => $this->old_image->getImageWidth(),
			'height' => $this->old_image->getImageHeight(),
		];

		return $this;
	}

	/**
	 * Applies an edge-detection filter (chainable convenience wrapper).
	 *
	 * Implemented via Imagick::edgeImage() with radius 1.
	 */
	public function edgeDetect(): Imagick
	{
		$this->old_image->edgeImage(1);

		return $this;
	}

	/**
	 * Applies an emboss filter (chainable convenience wrapper).
	 *
	 * Implemented via Imagick::embossImage() with radius 0 and sigma 1.
	 */
	public function emboss(): Imagick
	{
		$this->old_image->embossImage(0, 1);

		return $this;
	}

	/**
	 * Applies a smoothing pass (chainable convenience wrapper).
	 *
	 * Implemented as a Gaussian blur where $level controls the radius.
	 *
	 * @param int $level Blur radius. 0 is a no-op for Imagick's
	 *                   gaussianBlurImage() (radius 0 = auto), so we use
	 *                   max(0.5, $level) to ensure a visible effect.
	 */
	public function smooth(int $level = 1): Imagick
	{
		$radius = max(0.5, $level);
		$this->old_image->gaussianBlurImage($radius, 1);

		return $this;
	}

	/**
	 * Shows an image
	 */
	public function show(bool $raw_data = false): Imagick
	{
		if ($this->plugins)
		{
			foreach ($this->plugins as $plugin)
			{
				$plugin->execute($this);
			}
		}

		if (headers_sent() && php_sapi_name() != 'cli')
		{
			throw new RuntimeException('Cannot show image, headers have already been sent');
		}

		// Let Imagick name the format it is actually holding; the helper
		// maps it to the correct mime type (including the aliases Imagick may report, e.g. "jpe" or "tif").
		$image_format = (string) $this->old_image->getImageFormat();
		$mime_type = self::mimeTypeForFormat($image_format);

		// Apply the configured encoder quality so that streamed output
		// matches what save() would have written to disk.
		$quality = $this->qualityForFormat($image_format);

		if ($quality !== null)
		{
			$this->old_image->setImageCompressionQuality($quality);
		}

		if ($raw_data === false)
		{
			header('Content-type: ' . $mime_type);
		}

		echo $this->old_image->getImagesBlob();

		return $this;
	}

	/**
	 * Returns the Working Image as a String
	 */
	public function getImageAsString(): string
	{
		return $this->old_image->getImagesBlob();
	}

	/**
	 * Saves an image
	 */
	public function save(string $file_name, ?string $format = null): Imagick
	{
		$format = ($format !== null)
			? self::normalizeFormatName($format)
			: self::normalizeFormatName($this->format);

		if (!is_writeable(dirname($file_name)))
		{
			if ($this->options['correctPermissions'] === true)
			{
				@chmod(dirname($file_name), 0777);

				if (!is_writeable(dirname($file_name)))
				{
					throw new RuntimeException('File is not writeable, and could not correct permissions: ' . $file_name);
				}
			}
			else
			{
				throw new RuntimeException('File not writeable: ' . $file_name);
			}
		}

		$quality = $this->qualityForFormat($format);

		if ($quality !== null)
		{
			$this->old_image->setImageCompressionQuality($quality);
		}

		// Clear any leftover virtual canvas so the file is written at the
		// origin rather than at the crop/rotate offset.
		$this->resetImagePage();

		$this->old_image->setImageFormat($format);

		$this->old_image->writeImage($file_name);

		return $this;
	}

	#################################
	# ----- GETTERS / SETTERS ----- #
	#################################

	/**
	 * Sets options for all operations.
	 */
	public function setOptions(array $options = []): Imagick
	{
		// A quality preset is expanded into the per-format quality options
		// and merged *before* the caller's own options, so an explicit
		// 'jpegQuality' (or any other per-format value) always wins over
		// the preset.
		if (array_key_exists('qualityPreset', $options))
		{
			$preset = $options['qualityPreset'];
			unset($options['qualityPreset']);

			if ($preset !== null && $preset !== '')
			{
				$options = array_merge(
					$this->qualityPresetOptions((string) $preset),
					$options
					);
			}
		}

		if (count($this->options) == 0)
		{
			$default_options = [
				'resizeUp'              => false,
				'avifQuality'           => 100,
				'jpegQuality'           => 100,
				'jxlQuality'            => 90,
				'webpQuality'           => 100,
				'correctPermissions'    => false,
				'preserveAlpha'         => true,
				'alphaMaskColor'        => [255, 255, 255],
				'preserveTransparency'  => true,
				'transparencyMaskColor' => [0, 0, 0],
				'interlace'             => null,
				'sharpenAmount'         => 50,
				'textFont'              => null,
				'textDefaultSize'       => 12,
			];
		}
		else
		{
			$default_options = $this->options;
		}

		$this->options = array_merge($default_options, $options);

		return $this;
	}

	/**
	 * Returns $current_dimensions.
	 */
	public function getCurrentDimensions(): array
	{
		return $this->current_dimensions;
	}

	public function setCurrentDimensions(array $current_dimensions): Imagick
	{
		$this->current_dimensions = $current_dimensions;

		return $this;
	}

	public function getMaxHeight(): int
	{
		return $this->max_height;
	}

	public function setMaxHeight(int $max_height): Imagick
	{
		$this->max_height = $max_height;

		return $this;
	}

	public function getMaxWidth(): int
	{
		return $this->max_width;
	}

	public function setMaxWidth(int $max_width): Imagick
	{
		$this->max_width = $max_width;

		return $this;
	}

	/**
	 * Returns $new_dimensions.
	 */
	public function getNewDimensions(): array
	{
		return $this->new_dimensions;
	}

	/**
	 * Sets $new_dimensions.
	 */
	public function setNewDimensions(array $new_dimensions): Imagick
	{
		$this->new_dimensions = $new_dimensions;

		return $this;
	}

	/**
	 * Returns $options.
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	/**
	 * Returns $percent.
	 */
	public function getPercent(): int
	{
		return $this->percent;
	}

	/**
	 * Sets $percent.
	 */
	public function setPercent(int $percent): Imagick
	{
		$this->percent = $percent;

		return $this;
	}

	/**
	 * Returns $old_image.
	 */
	public function getOldImage(): ?\Imagick
	{
		return $this->old_image;
	}

	/**
	 * Sets $old_image.
	 */
	public function setOldImage(\Imagick $old_image): static
	{
		$this->old_image = $old_image;

		return $this;
	}

	/**
	 * Returns $working_image.
	 */
	public function getWorkingImage(): ?\Imagick
	{
		return $this->working_image;
	}

	/**
	 * Sets $working_image.
	 */
	public function setWorkingImage(\Imagick $working_image): static
	{
		$this->working_image = $working_image;

		return $this;
	}


	#################################
	# ----- UTILITY FUNCTIONS ----- #
	#################################

	/**
	 * Resets the virtual canvas (page) of the working image.
	 *
	 * Imagick's crop/rotate/thumbnail operations keep the original virtual
	 * canvas around. The leftover page offset makes the image appear shifted
	 * when it is composited or written out, so it is cleared after every
	 * geometry change.
	 */
	protected function resetImagePage(): void
	{
		if ($this->old_image instanceof \Imagick)
		{
			$this->old_image->setImagePage(
				$this->old_image->getImageWidth(),
				$this->old_image->getImageHeight(),
				0,
				0
				);
		}
	}

	/**
	 * Maps canonical format names to the option key that controls the encoder
	 * quality for that format.
	 *
	 * @return array<string, string>
	 */
	protected function qualityOptionMap(): array
	{
		return [
			'AVIF' => 'avifQuality',
			'JPEG' => 'jpegQuality',
			'JXL'  => 'jxlQuality',
			'WEBP' => 'webpQuality',
		];
	}

	/**
	 * Returns the encoder quality to use for a given format, or null when no
	 * quality should be applied (leaving the codec's own default in place).
	 *
	 * Every format has its own independent option; there is deliberately no
	 * fallback between them. The 0-100 scale is not interchangeable between
	 * codecs - each maps it onto a different internal parameter (libjpeg
	 * quantization tables, libwebp VBR quality, an AV1 quantizer, libjxl
	 * distance) - so reusing one format's setting for another would
	 * silently mis-set the encoder.
	 *
	 * A null value means "do not call setImageCompressionQuality()", which is
	 * what the 'off' quality preset relies on. This is distinct from a
	 * quality of 0, which is passed through to the codec.
	 *
	 * @param string $format Format name in any case (e.g. 'JPEG', 'jpg', 'JXL').
	 */
	protected function qualityForFormat(string $format): ?int
	{
		$quality = match (self::normalizeFormatName($format)) {
			'AVIF' => $this->options['avifQuality'],
			'JPEG' => $this->options['jpegQuality'],
			'JXL'  => $this->options['jxlQuality'],
			'WEBP' => $this->options['webpQuality'],
			default => null,
		};

		return ($quality === null) ? null : (int) $quality;
	}

	/**
	 * Calculates a new width and height for the image based on $this->max_width and the provided dimensions
	 */
	protected function calcWidth(int $width, int $height): array
	{
		$new_width_percentage = (100 * $this->max_width) / $width;
		$new_height = ($height * $new_width_percentage) / 100;

		return [
			'new_width'  => $this->max_width,
			'new_height' => intval($new_height)
		];
	}

	/**
	 * Calculates a new width and height for the image based on $this->max_height and the provided dimensions
	 */
	protected function calcHeight(int $width, int $height): array
	{
		$new_height_percentage = (100 * $this->max_height) / $height;
		$new_width = ($width * $new_height_percentage) / 100;

		return [
			'new_width'  => ceil($new_width),
			'new_height' => ceil($this->max_height)
		];
	}

	/**
	 * Calculates a new width and height for the image based on $this->percent and the provided dimensions
	 */
	protected function calcPercent(int $width, int $height): array
	{
		$new_width  = ($width * $this->percent) / 100;
		$new_height = ($height * $this->percent) / 100;

		return [
			'new_width'  => ceil($new_width),
			'new_height' => ceil($new_height)
		];
	}

	/**
	 * Calculates the new image dimensions
	 */
	protected function calcImageSize(int $width, int $height): void
	{
		$new_size = [
			'new_width'  => $width,
			'new_height' => $height
		];

		if ($this->max_width > 0)
		{
			$new_size = $this->calcWidth($width, $height);

			if ($this->max_height > 0 && $new_size['new_height'] > $this->max_height)
			{
				$new_size = $this->calcHeight($new_size['new_width'], $new_size['new_height']);
			}
		}

		if ($this->max_height > 0)
		{
			$new_size = $this->calcHeight($width, $height);

			if ($this->max_width > 0 && $new_size['new_width'] > $this->max_width)
			{
				$new_size = $this->calcWidth($new_size['new_width'], $new_size['new_height']);
			}
		}

		$this->new_dimensions = $new_size;
	}

	/**
	 * Calculates new image dimensions, not allowing the width and height to be less than either the max width or height
	 */
	protected function calcImageSizeStrict(int $width, int $height): void
	{
		$new_dimensions = $this->getCurrentDimensions();

		if ($this->max_width >= $this->max_height)
		{
			if ($width > $height)
			{
				$new_dimensions = $this->calcHeight($width, $height);

				if ($new_dimensions['new_width'] < $this->max_width)
				{
					$new_dimensions = $this->calcWidth($width, $height);
				}
			}
			else if ($height >= $width)
			{
				$new_dimensions = $this->calcWidth($width, $height);

				if ($new_dimensions['new_height'] < $this->max_height)
				{
					$new_dimensions = $this->calcHeight($width, $height);
				}
			}
		}
		else if ($this->max_height > $this->max_width)
		{
			if ($width >= $height)
			{
				$new_dimensions = $this->calcWidth($width, $height);

				if ($new_dimensions['new_height'] < $this->max_height)
				{
					$new_dimensions = $this->calcHeight($width, $height);
				}
			}
			else if ($height > $width)
			{
				$new_dimensions = $this->calcHeight($width, $height);

				if ($new_dimensions['new_width'] < $this->max_width)
				{
					$new_dimensions = $this->calcWidth($width, $height);
				}
			}
		}

		$this->new_dimensions = $new_dimensions;
	}

	/**
	 * Calculates new dimensions based on $this->percent and the provided dimensions
	 */
	protected function calcImageSizePercent(int $width, int $height): void
	{
		if ($this->percent > 0)
		{
			$this->new_dimensions = $this->calcPercent($width, $height);
		}
	}

	/**
	 * Determines the image format using Imagick itself.
	 *
	 * getimagesize() (and finfo_file()) only recognise a small subset of the
	 * formats ImageMagick can decode - HEIC/HEIF, AVIF, TIFF, BMP, ICO, SVG
	 * and friends are either unknown or misnamed. Imagick already knows
	 * exactly which format it loaded, so we ask the image for its format and
	 * only fall back to the mime type Imagick reports if the format string
	 * is unusable.
	 *
	 * @throws Exception
	 */
	protected function determineFormat(): void
	{
		if (!$this->old_image instanceof \Imagick)
		{
			throw new Exception(
				'Could not determine format: the image has not been loaded.'
				);
		}

		$format = strtoupper(trim((string) $this->old_image->getImageFormat()));

		if ($format === '' || $format === 'UNKNOWN')
		{
			// Imagick could not name the format - ask for the mime type it
			// detected for the image instead.
			$mime_type = strtolower(trim((string) $this->old_image->getImageMimeType()));

			$format = match ($mime_type) {
				'image/avif'                    => 'AVIF',
				'image/bmp', 'image/x-ms-bmp'    => 'BMP',
				'image/gif'                     => 'GIF',
				'image/heic'                    => 'HEIC',
				'image/heif'                    => 'HEIF',
				'image/jpeg'                    => 'JPEG',
				'image/jxl'                     => 'JXL',
				'image/png'                     => 'PNG',
				'image/tiff'                    => 'TIFF',
				'image/webp'                    => 'WEBP',
				'image/x-icon', 'image/vnd.microsoft.icon' => 'ICO',
				'image/svg+xml'                 => 'SVG',
				default => throw new Exception(
					'Image format not supported: ' .
					($mime_type !== '' ? $mime_type : 'unknown')
					),
			};
		}

		$this->format = self::normalizeFormatName($format);
	}

	/**
	 * Normalises the format aliases Imagick may report into canonical names.
	 *
	 * Imagick is not consistent about the name it uses: a JPEG file may be
	 * reported as "JPEG", "JPG" or "JPE", a TIFF as "TIFF" or "TIF", and so
	 * on. Everything downstream (save(), the plugins, the mime headers) works
	 * on the canonical name.
	 */
	protected static function normalizeFormatName(string $format): string
	{
		$format = strtoupper(trim($format));

		return match ($format) {
			'JPG', 'JPE', 'JPEG'  => 'JPEG',
			'TIF', 'TIFF'         => 'TIFF',
			'AVIF'                => 'AVIF',
			'HEIC'                => 'HEIC',
			'HEIF'                => 'HEIF',
			'GIF'                 => 'GIF',
			'PNG'                 => 'PNG',
			'WEBP'                => 'WEBP',
			'BMP', 'DIB'          => 'BMP',
			'ICO', 'PICON'        => 'ICO',
			'SVG', 'MSVG', 'SVGZ' => 'SVG',
			'JP2', 'J2K', 'JPC'   => 'JP2',
			'JXL', 'JPEGXL'       => 'JXL',
			'TGA'                 => 'TGA',
			'PSD'                 => 'PSD',
			default               => $format,
		};
	}

	/**
	 * Returns every format name that refers to the same codec.
	 *
	 * Used by verifyFormatCompatibility() so that a format reported under an
	 * alias (JPG vs. JPEG, TIF vs. TIFF, …) is not mistaken for an
	 * unsupported format.
	 *
	 * @return string[]
	 */
	protected static function formatAliases(string $format): array
	{
		$format = strtoupper($format);

		return match ($format) {
			'JPEG' => ['JPEG', 'JPG', 'JPE'],
			'TIFF' => ['TIFF', 'TIF'],
			'HEIC' => ['HEIC', 'HEIF'],
			'HEIF' => ['HEIF', 'HEIC'],
			'BMP'  => ['BMP', 'DIB'],
			'ICO'  => ['ICO', 'PICON'],
			'SVG'  => ['SVG', 'MSVG', 'SVGZ'],
			'JP2'  => ['JP2', 'J2K', 'JPC'],
			'JXL'  => ['JXL', 'JPEGXL'],
			default => [$format],
		};
	}

	/**
	 * Returns the mime type for a format name.
	 *
	 * @param string $format Format name in any case (e.g. "JPEG", "jpg").
	 */
	protected static function mimeTypeForFormat(string $format): string
	{
		$format = strtolower(trim($format));

		return match ($format) {
			'avif'             => 'image/avif',
			'bmp', 'dib'       => 'image/bmp',
			'gif'              => 'image/gif',
			'heic'             => 'image/heic',
			'heif'             => 'image/heif',
			'jpeg', 'jpg', 'jpe' => 'image/jpeg',
			'jxl', 'jpegxl'    => 'image/jxl',
			'png'              => 'image/png',
			'tif', 'tiff'      => 'image/tiff',
			'webp'             => 'image/webp',
			'ico', 'picon'     => 'image/x-icon',
			'svg', 'msvg', 'svgz' => 'image/svg+xml',
			default            => 'image/' . ($format !== '' ? $format : 'octet-stream'),
		};
	}

	/**
	 * Makes sure the correct ImageMagick format is supported
	 *
	 * Uses Imagick's own format registry via the static queryFormats()
	 * instead of spinning up a second Imagick instance just to ask the
	 * question.
	 *
	 * @throws Exception
	 */
	protected function verifyFormatCompatibility(): void
	{
		$format_to_check = strtoupper($this->format);

		$formats = \Imagick::queryFormats();

		$supported_formats = array_map('strtoupper', $formats);

		if (!in_array($format_to_check, $supported_formats, true))
		{
			// Imagick may register a codec under an alias only (JPG vs. JPEG,
			// TIF vs. TIFF, ...), so accept any known alias as a match.
			$aliases = self::formatAliases($format_to_check);

			foreach ($supported_formats as $format)
			{
				if (in_array($format, $aliases, true))
				{
					return;
				}
			}

			throw new Exception(
				'Your ImageMagick installation does not support ' . $this->format . ' image types'
				);
		}
	}
}
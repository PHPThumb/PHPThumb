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
	 * The prior image (before manipulation)
	 *
	 * @var \Imagick
	 */
	protected $old_image;

	/**
	 * The working image (used during manipulation)
	 *
	 * @var \Imagick
	 */
	protected $working_image;

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

		$this->determineFormat();
		$this->verifyFormatCompatibility();

		$this->old_image = new \Imagick();

		if ($this->remote_image)
		{
			$this->old_image->readImage($this->file_name);
		}
		else
		{
			$this->old_image->readImage($this->file_name);
		}

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
		if ($this->old_image instanceof \Imagick)
		{
			$this->old_image->clear();
			$this->old_image->destroy();
		}

		if ($this->working_image instanceof \Imagick)
		{
			$this->working_image->clear();
			$this->working_image->destroy();
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

		$this->working_image->setFormat($this->old_image->getFormat());

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
	 * Renders $text onto the image.
	 *
	 * Position keywords are identical to the GD backend — see the
	 * documentation on GD::text() for the full list of accepted values.
	 *
	 * Options:
	 *
	 *  - size      (int)   Font size in pixels (default: $this->options['textDefaultSize'])
	 *  - color     (string|array)  Hex string or [r, g, b] (default: '#FFFFFF')
	 *  - font      (string|null)   TTF file path OR font family name (resolved via fontconfig).
	 *                             null → system default (resolved via fontconfig).
	 *  - angle     (float) Rotation in degrees (default: 0)
	 *  - offsetX   (int)   Horizontal padding from anchor (default: 10)
	 *  - offsetY   (int)   Vertical padding from anchor (default: 10)
	 *  - align     (string) 'left' | 'center' | 'right' (default: 'center')
	 *  - alpha     (int)   Text opacity 0..100 (default: 100)
	 *  - shadow    (array)  ['enabled' => bool, 'color', 'offsetX', 'offsetY', 'blur']
	 *  - stroke    (array)  ['enabled' => bool, 'color', 'width']
	 *  - background(array)  ['enabled' => bool, 'color', 'padding', 'alpha']
	 *
	 * @param string $text     The text to render. Multi-line via "\n" supported.
	 * @param string $position Anchor keyword.
	 * @param array  $options  See above.
	 * @return $this
	 *
	 * @throws InvalidArgumentException For unknown position strings or bad colors.
	 * @throws RuntimeException If no usable font can be located on the system.
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

			// Resolve a usable TTF file path. We must always set a non-empty font
			// on ImagickDraw, otherwise freetype chokes on the empty default.
			$font_path = $this->resolveFont($cfg['font']);

			// Measure the text using Imagick's font metrics.
			$measure = new \ImagickDraw();
			$measure->setFont($font_path);
			$measure->setFontSize($cfg['size']);

			try
			{
				$metrics = $this->old_image->queryFontMetrics($measure, $text);
			}
			catch (\ImagickException $e)
			{
				$measure->clear();
				$measure->destroy();
				throw new RuntimeException(
					'Imagick: queryFontMetrics() failed for font "' . $font_path . '": ' . $e->getMessage(),
					0,
					$e
					);
			}

			$measure->clear();
			$measure->destroy();

			if ($metrics === false || empty($metrics))
			{
				throw new RuntimeException('Imagick: queryFontMetrics() returned empty metrics');
			}

			$text_width  = (int) $metrics['textWidth'];
			$text_height = (int) $metrics['textHeight'];

			if (isset($metrics['descender']))
			{
				$text_height += (int) abs($metrics['descender']);
			}

			[$x, $y] = $this->computeTextTopLeft(
				$anchor, $cfg['offsetX'], $cfg['offsetY'],
				$current_width, $current_height,
				$text_width, $text_height
			);

			$align_const = match (strtolower($cfg['align'])) {
				'left'   => \Imagick::ALIGN_LEFT,
				'right'  => \Imagick::ALIGN_RIGHT,
				default  => \Imagick::ALIGN_CENTER,
			};

			$draw = new \ImagickDraw();
			$draw->setFont($font_path);
			$draw->setFontSize($cfg['size']);
			$draw->setTextAlignment($align_const);
			$draw->setTextAntialias(true);

			$rgb = $this->parseColor($cfg['color']);
			$draw->setFillColor(new \ImagickPixel(sprintf(
				'rgba(%d, %d, %d, %f)',
				$rgb['r'], $rgb['g'], $rgb['b'],
				$cfg['alpha'] / 100
				)));

			if ($cfg['stroke']['enabled'])
			{
				$stroke_rgb = $this->parseColor($cfg['stroke']['color']);
				$draw->setStrokeColor(new \ImagickPixel(sprintf(
					'rgba(%d, %d, %d, 1)',
					$stroke_rgb['r'], $stroke_rgb['g'], $stroke_rgb['b']
					)));
				$draw->setStrokeWidth(max(1, (int) $cfg['stroke']['width']));
			}
			else
			{
				$draw->setStrokeColor(new \ImagickPixel('transparent'));
				$draw->setStrokeWidth(0);
			}

			if ($cfg['background']['enabled'])
			{
				$bg_rgb = $this->parseColor($cfg['background']['color']);
				$padding = (int) ($cfg['background']['padding'] ?? 4);
				$bg_alpha = max(0, min(100, (int) ($cfg['background']['alpha'] ?? 75))) / 100;

				$pill_x = max(0, $x - $padding);
				$pill_y = max(0, $y - $padding);
				$pill_w = $text_width + 2 * $padding;
				$pill_h = $text_height + 2 * $padding;

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

			if ($cfg['shadow']['enabled'])
			{
				$shadow_rgb = $this->parseColor($cfg['shadow']['color']);
				$shadow_draw = new \ImagickDraw();
				$shadow_draw->setFont($font_path);
				$shadow_draw->setFontSize($cfg['size']);
				$shadow_draw->setTextAlignment($align_const);
				$shadow_draw->setTextAntialias(true);
				$shadow_draw->setFillColor(new \ImagickPixel(sprintf(
					'rgba(%d, %d, %d, 1)',
					$shadow_rgb['r'], $shadow_rgb['g'], $shadow_rgb['b']
					)));
				$shadow_draw->setStrokeColor(new \ImagickPixel('transparent'));
				$shadow_draw->setStrokeWidth(0);

				$this->old_image->annotateImage(
					$shadow_draw,
					$x + $cfg['shadow']['offsetX'],
					$y + $text_height + $cfg['shadow']['offsetY'],
					$cfg['angle'],
					$text
				);

				$shadow_draw->clear();
				$shadow_draw->destroy();
			}

			$this->old_image->annotateImage(
				$draw,
				$x,
				$y + $text_height,
				$cfg['angle'],
				$text
				);

			$draw->clear();
			$draw->destroy();

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
				default  => (int) (($canvas_h - $text_h) / 2),
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
	 * Applies a filter to the image
	 */
	public function imageFilter(int $filter, bool $arg1 = false, bool $arg2 = false, bool $arg3 = false, bool $arg4 = false): Imagick
	{
		$arguments = [];

		if ($arg1 !== false)
		{
			$arguments[] = $arg1;
		}

		if ($arg2 !== false)
		{
			$arguments[] = $arg2;
		}

		if ($arg3 !== false)
		{
			$arguments[] = $arg3;
		}

		if ($arg4 !== false)
		{
			$arguments[] = $arg4;
		}

		$this->old_image->filter($filter, ...$arguments);

		return $this;
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

		$format = strtolower($this->old_image->getImageFormat());
		$mime_type = match ($format) {
			'avif'  => 'image/avif',
			'gif'   => 'image/gif',
			'jpeg', 'jpg' => 'image/jpeg',
			'png'   => 'image/png',
			'webp'  => 'image/webp',
			'bmp'   => 'image/bmp',
			default => 'image/' . $format,
		};

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
		$format = ($format !== null) ? strtoupper($format) : strtoupper($this->format);

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

		$output_format = match ($format) {
			'AVIF'  => 'AVIF',
			'GIF'   => 'GIF',
			'JPEG', 'JPG' => 'JPEG',
			'PNG'   => 'PNG',
			'WEBP'  => 'WEBP',
			default => strtoupper($format),
		};

		$this->old_image->setFormat($output_format);
		$this->old_image->setImageFormat($output_format);

		$quality = match ($output_format) {
			'AVIF'      => $this->options['avifQuality'],
			'JPEG', 'JPG' => $this->options['jpegQuality'],
			'WEBP'      => $this->options['webpQuality'],
			default     => null,
		};

		if ($quality !== null)
		{
			$this->old_image->setImageCompressionQuality($quality);
		}

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
		if (count($this->options) == 0)
		{
			$default_options = [
				'resizeUp'              => false,
				'avifQuality'           => 100,
				'jpegQuality'           => 100,
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
	public function getOldImage(): \Imagick
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
	public function getWorkingImage(): \Imagick
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
	 * Determines the file format by mime-type
	 */
	protected function determineFormat(): void
	{
		if ($this->remote_image)
		{
			$format_info = getimagesize($this->file_name);

			if ($format_info === false)
			{
				throw new Exception('Could not determine format of remote image: ' . $this->file_name);
			}

			$mime_type = $format_info['mime'] ?? null;
		}
		else
		{
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime_type = finfo_file($finfo, $this->file_name);
		}

		$this->format = match ($mime_type) {
			'image/avif'   => 'AVIF',
			'image/bmp'    => 'BMP',
			'image/gif'    => 'GIF',
			'image/heic'   => 'HEIC',
			'image/jpeg'   => 'JPEG',
			'image/png'    => 'PNG',
			'image/tiff'   => 'TIFF',
			'image/webp'   => 'WEBP',
			default        => throw new Exception('Image format not supported: ' . $mime_type),
		};
	}

	/**
	 * Makes sure the correct ImageMagick format is supported
	 */
	protected function verifyFormatCompatibility(): void
	{
		$imagick = new \Imagick();

		$formats = $imagick->queryFormats();

		$format_to_check = strtoupper($this->format);

		if (!in_array($format_to_check, $formats))
		{
			throw new Exception('Your ImageMagick installation does not support ' . $this->format . ' image types');
		}

		$imagick->destroy();
	}
}

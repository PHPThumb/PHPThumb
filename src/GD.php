<?php

namespace PHPThumb;

use Exception;
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

class GD extends PHPThumb
{
	/**
	 * The prior image (before manipulation)
	 *
	 * @var resource
	 */
	protected $old_image;

	/**
	 * The working image (used during manipulation)
	 *
	 * @var resource
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
	 *
	 * This array contains various options that determine the behavior in
	 * various functions throughout the class.  Functions note which specific
	 * option key / values are used in their documentation
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
		if (!extension_loaded('gd'))
		{
			throw new RuntimeException(
				'The GD extension is required to use PHPThumb\GD. ' .
				'Install ext-gd or use PHPThumb\Imagick instead.'
				);
		}

		parent::__construct($file_name, $options, $plugins);

		$this->determineFormat();
		$this->verifyFormatCompatibility();

		$this->old_image = match ($this->format) {
			'AVIF'		=> imagecreatefromavif		($this->file_name),
			'GIF'		=> imagecreatefromgif		($this->file_name),
			'JPEG'		=> imagecreatefromjpeg		($this->file_name),
			'PNG'		=> imagecreatefrompng		($this->file_name),
			'STRING'	=> imagecreatefromstring	($this->file_name),
			'WEBP'		=> imagecreatefromwebp		($this->file_name),
		};

		if ($this->old_image === false)
		{
			// Could not decode image
			throw new Exception('The image file is invalid, corrupted, or the required ' . $this->format . ' codec is not available in the GD library.');
		}

		$this->current_dimensions = [
			'width'		=> imagesx($this->old_image),
			'height'	=> imagesy($this->old_image)
		];
	}

	/**
	 * Pad an image to desired dimensions. Moves the image into the center and fills the rest with $color.
	 */
	public function pad(int $width, int $height, array $color = [255, 255, 255]): GD
	{
		// no resize - woohoo!
		if ($width == $this->current_dimensions['width'] && $height == $this->current_dimensions['height'])
		{
			return $this;
		}

		// create the working image
		$this->working_image = imagecreatetruecolor($width, $height);

		// create the fill color
		$fill_color = imagecolorallocate(
			$this->working_image,
			$color[0],
			$color[1],
			$color[2]
		);

		// fill our working image with the fill color
		imagefill(
			$this->working_image,
			0,
			0,
			$fill_color
		);

		// copy the image into the center of our working image
		imagecopyresampled(
			$this->working_image,
			$this->old_image,
			intval(($width-$this->current_dimensions['width']) / 2),
			intval(($height-$this->current_dimensions['height']) / 2),
			0,
			0,
			$this->current_dimensions['width'],
			$this->current_dimensions['height'],
			$this->current_dimensions['width'],
			$this->current_dimensions['height']
		);

		// update all the variables and resources to be correct
		$this->old_image					= $this->working_image;
		$this->current_dimensions['width']	= $width;
		$this->current_dimensions['height']	= $height;

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
	 * Alpha is preserved for PNG sources — the new border area is fully
	 * opaque unless $color carries an alpha component (only supported when
	 * passing a [r, g, b, a] array; hex strings are always opaque).
	 *
	 * @param int $thickness Frame thickness in pixels (positive integer).
	 * @param array|string $color Either a hex string ('#FF8800' / '#f80' / 'FF8800')
	 *                            or an [r, g, b] (or [r, g, b, a] 0-255) array.
	 * @return $this
	 *
	 * @throws InvalidArgumentException For negative thickness or invalid color.
	 */
	public function border(int $thickness, array|string $color = [0, 0, 0]): GD
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
		$this->working_image = imagecreatetruecolor($new_width, $new_height);

		if ($this->working_image === false)
		{
			throw new RuntimeException('GD: failed to create canvas for border()');
		}

		$border_color = imagecolorallocate(
			$this->working_image,
			$rgb['r'],
			$rgb['g'],
			$rgb['b']
			);

		if ($border_color === false)
		{
			throw new RuntimeException('GD: failed to allocate border color');
		}

		imagefilledrectangle(
			$this->working_image,
			0, 0,
			$new_width, $new_height,
			$border_color
		);

		// Preserve alpha when the source is PNG so the original's transparent
		// pixels stay transparent in the center.
		if ($this->format === 'PNG' && $this->options['preserveAlpha'] === true)
		{
			imagealphablending($this->working_image, false);
			imagesavealpha($this->working_image, true);
		}

		// Paste the original image into the center.
		imagecopy(
			$this->working_image,
			$this->old_image,
			$thickness,
			$thickness,
			0,
			0,
			$current_width,
			$current_height
		);

		// Commit.
		$this->old_image                 = $this->working_image;
		$this->current_dimensions['width']  = $new_width;
		$this->current_dimensions['height'] = $new_height;

		return $this;
	}

	/**
	 * Parses a color argument into an [r, g, b] array suitable for
	 * imagecolorallocate(). Accepts either a hex string or a 3- or 4-element
	 * [r, g, b[, a]] array. Alpha (if provided) is clamped to [0, 127] for GD.
	 *
	 * @param array|string $color
	 * @return array{r: int, g: int, b: int, a: int}
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
				'a' => isset($color[3]) ? max(0, min(127, (int) $color[3])) : 0,
			];
		}

		if (!is_string($color))
		{
			throw new InvalidArgumentException(
				'border() color must be a hex string or an [r, g, b] array.'
				);
		}

		$hex = ltrim(trim($color), '#');
		// Optional '0x' prefix
		if (str_starts_with($hex, '0x') || str_starts_with($hex, '0X'))
		{
			$hex = substr($hex, 2);
		}

		// Expand shorthand #abc → #aabbcc
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
			'a' => strlen($hex) === 8 ? (int) round(hexdec(substr($hex, 6, 2)) * 127 / 255) : 0,
		];
	}

	/**
	 * Renders $text onto the image.
	 *
	 * Position keywords (case-insensitive, multiple aliases):
	 *
	 *  - top-left / northwest
	 *  - top / north / top-center
	 *  - top-right / northeast
	 *  - left / west / center-left
	 *  - center
	 *  - right / east / center-right
	 *  - bottom-left / southwest
	 *  - bottom / south / bottom-center
	 *  - bottom-right / southeast
	 *
	 * Options (all optional):
	 *
	 *  - size      (int)   Font size in points (default: $this->options['textDefaultSize'])
	 *  - color     (string|array)  Hex string or [r, g, b] (default: '#FFFFFF')
	 *  - font      (string|null)   Path to TTF; null → built-in GD font fallback
	 *  - angle     (float) Rotation in degrees (default: 0)
	 *  - offsetX   (int)   Horizontal padding from anchor (default: 10)
	 *  - offsetY   (int)   Vertical padding from anchor (default: 10)
	 *  - align     (string) 'left' | 'center' | 'right' (default: 'center')
	 *  - alpha     (int)   Text opacity 0..100 (default: 100)
	 *  - shadow    (array)  ['enabled' => bool, 'color', 'offsetX', 'offsetY', 'blur']
	 *  - stroke    (array)  ['enabled' => bool, 'color', 'width']  (requires TTF)
	 *  - background(array)  ['enabled' => bool, 'color', 'padding', 'alpha']
	 *
	 * @param string $text     The text to render. Multi-line via "\n" supported.
	 * @param string $position Anchor keyword (see above).
	 * @param array  $options  See above.
	 * @return $this
	 *
	 * @throws InvalidArgumentException For unknown position strings, bad color
	 *                                  values, or missing imagettftext() support.
	 */
	public function text(
		string $text,
		string $position = 'bottom-right',
		array $options = []
		): GD
		{
			if ($text === '')
			{
				return $this; // no-op
			}

			$cfg = $this->resolveTextOptions($options);
			$anchor = $this->resolveTextAnchor($position);

			$current_width  = $this->current_dimensions['width'];
			$current_height = $this->current_dimensions['height'];

			$use_ttf = $cfg['font'] !== null && function_exists('imagettftext');

			if ($use_ttf)
			{
				$lines = explode("\n", $text);
				$line_height = (int) round($cfg['size'] * 1.2);

				$max_line_width = 0;
				foreach ($lines as $line)
				{
					$box = imagettfbbox($cfg['size'], $cfg['angle'], $cfg['font'], $line);
					if ($box === false)
					{
						throw new RuntimeException(
							'GD imagettfbbox() failed for font: ' . $cfg['font']
							);
					}
					$line_w = (int) (max($box[2], $box[4]) - min($box[0], $box[6]));
					if ($line_w > $max_line_width)
					{
						$max_line_width = $line_w;
					}
				}

				$text_width  = $max_line_width;
				$text_height = $line_height * count($lines);

				[$x, $y] = $this->computeTextTopLeft(
					$anchor, $cfg['offsetX'], $cfg['offsetY'],
					$current_width, $current_height,
					$text_width, $text_height
					);

				if ($cfg['background']['enabled'])
				{
					$this->drawTextBackgroundGd(
						$x, $y, $text_width, $text_height,
						$cfg['background']
						);
				}

				if ($cfg['shadow']['enabled'])
				{
					$shadow_rgb = $this->parseColor($cfg['shadow']['color']);
					$shadow_color = imagecolorallocatealpha(
						$this->old_image,
						$shadow_rgb['r'], $shadow_rgb['g'], $shadow_rgb['b'],
						$this->alphaToGd127(100)
						);
					if ($shadow_color !== false)
					{
						imagettftext(
							$this->old_image,
							$cfg['size'], $cfg['angle'],
							$x + $cfg['shadow']['offsetX'],
							$y + $cfg['shadow']['offsetY'] + $line_height,
							$shadow_color,
							$cfg['font'],
							implode("\n", $lines)
							);
					}
				}

				if ($cfg['stroke']['enabled'])
				{
					$stroke_rgb = $this->parseColor($cfg['stroke']['color']);
					$stroke_color = imagecolorallocatealpha(
						$this->old_image,
						$stroke_rgb['r'], $stroke_rgb['g'], $stroke_rgb['b'],
						$this->alphaToGd127(100)
						);
					if ($stroke_color !== false)
					{
						$stroke_w = max(1, (int) $cfg['stroke']['width']);
						for ($sx = -$stroke_w; $sx <= $stroke_w; $sx++)
						{
							for ($sy = -$stroke_w; $sy <= $stroke_w; $sy++)
							{
								if ($sx === 0 && $sy === 0)
								{
									continue;
								}
								imagettftext(
									$this->old_image,
									$cfg['size'], $cfg['angle'],
									$x + $sx,
									$y + $sy + $line_height,
									$stroke_color,
									$cfg['font'],
									implode("\n", $lines)
									);
							}
						}
					}
				}

				$rgb = $this->parseColor($cfg['color']);
				$text_color = imagecolorallocatealpha(
					$this->old_image,
					$rgb['r'], $rgb['g'], $rgb['b'],
					$this->alphaToGd127($cfg['alpha'])
					);
				if ($text_color === false)
				{
					throw new RuntimeException('GD: failed to allocate text color');
				}

				imagettftext(
					$this->old_image,
					$cfg['size'], $cfg['angle'],
					$x,
					$y + $line_height,
					$text_color,
					$cfg['font'],
					implode("\n", $lines)
					);
			}
			else
			{
				// Built-in GD font fallback
				$font = 5;
				$char_w = imagefontwidth($font);
				$char_h = imagefontheight($font);

				$lines = explode("\n", $text);
				$max_line_width = 0;
				foreach ($lines as $line)
				{
					$lw = strlen($line) * $char_w;
					if ($lw > $max_line_width)
					{
						$max_line_width = $lw;
					}
				}

				$text_width  = $max_line_width;
				$text_height = $char_h * count($lines);

				[$x, $y] = $this->computeTextTopLeft(
					$anchor, $cfg['offsetX'], $cfg['offsetY'],
					$current_width, $current_height,
					$text_width, $text_height
					);

				if ($cfg['background']['enabled'])
				{
					$this->drawTextBackgroundGd(
						$x, $y, $text_width, $text_height,
						$cfg['background']
						);
				}

				$rgb = $this->parseColor($cfg['color']);
				$text_color = imagecolorallocatealpha(
					$this->old_image,
					$rgb['r'], $rgb['g'], $rgb['b'],
					$this->alphaToGd127($cfg['alpha'])
					);
				if ($text_color === false)
				{
					throw new RuntimeException('GD: failed to allocate text color');
				}

				$yy = $y;
				foreach ($lines as $line)
				{
					imagestring($this->old_image, $font, $x, $yy, $line, $text_color);
					$yy += $char_h;
				}
			}

			$this->working_image = $this->old_image;

			return $this;
	}

	/**
	 * Resolves the text() $options array against the configured defaults,
	 * normalizing nested shadow/stroke/background arrays.
	 *
	 * @param array $options
	 * @return array Normalized options.
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
	 * Parses a position keyword into [horizontal, vertical] anchors.
	 *
	 * @return array{0: string, 1: string}  Two-element array: [h, v]
	 *                                       where h ∈ {left, center, right} and
	 *                                       v ∈ {top, center, bottom}.
	 */
	protected function resolveTextAnchor(string $position): array
	{
		$p = strtolower(trim($position));

		// Horizontal
		$h = 'center';
		if (str_contains($p, 'left') || str_contains($p, 'west'))
		{
			$h = 'left';
		}
		elseif (str_contains($p, 'right') || str_contains($p, 'east'))
		{
			$h = 'right';
		}

		// Vertical
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
			// Couldn't match any keyword
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
	 * (x, y) at which the text should be drawn so that its bounding box lands on
	 * the requested anchor.
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
	 * Maps 0..100 percentage alpha to GD's 0..127 (where 0 = opaque, 127 = transparent).
	 */
	protected function alphaToGd127(int $percent): int
	{
		$percent = max(0, min(100, $percent));
		return (int) round((100 - $percent) * 127 / 100);
	}

	/**
	 * Paints a filled rectangle behind a text bounding box.
	 */
	protected function drawTextBackgroundGd(
		int $x, int $y, int $w, int $h, array $bg
		): void
		{
			$padding = (int) ($bg['padding'] ?? 4);
			$rgb = $this->parseColor($bg['color']);
			$bg_color = imagecolorallocatealpha(
				$this->old_image,
				$rgb['r'], $rgb['g'], $rgb['b'],
				$this->alphaToGd127((int) ($bg['alpha'] ?? 75))
			);
			if ($bg_color === false)
			{
				return;
			}
			imagefilledrectangle(
				$this->old_image,
				$x - $padding, $y - $padding,
				$x + $w + $padding, $y + $h + $padding,
				$bg_color
			);
	}

	/**
	 * Check if the image can be scaled up
	 */
	private function checkingMaxSize(int $max_width, int $max_height): void
	{
		if ($this->options['resizeUp'] === false)
		{
			$this->max_height	= ($max_height > $this->current_dimensions['height'])	? $this->current_dimensions['height']	: $max_height;
			$this->max_width	= ($max_width > $this->current_dimensions['width'])		? $this->current_dimensions['width']	: $max_width;
		}
		else
		{
			$this->max_height	= $max_height;
			$this->max_width	= $max_width;
		}
	}

	/**
	 * Resizes an image to be no larger than $max_width or $max_height
	 *
	 * If either param is set to zero, then that dimension will not be considered as a part of the resize.
	 * Additionally, if $this->options['resizeUp'] is set to true (false by default), then this function will
	 * also scale the image up to the maximum dimensions provided.
	 *
	 * @param int $max_width  The maximum width of the image in pixels
	 * @param int $max_height The maximum height of the image in pixels
	 */
	public function resize(int $max_width = 0, int $max_height = 0): GD
	{
		// make sure we're not exceeding our image size if we're not supposed to
		$this->checkingMaxSize($max_width, $max_height);

		// get the new dimensions...
		$this->calcImageSize($this->current_dimensions['width'], $this->current_dimensions['height']);

		// create the working image
		$this->working_image = imagecreatetruecolor($this->new_dimensions['new_width'], $this->new_dimensions['new_height']);

		$this->preserveAlpha();

		// and create the newly sized image
		imagecopyresampled(
			$this->working_image,
			$this->old_image,
			0,
			0,
			0,
			0,
			$this->new_dimensions['new_width'],
			$this->new_dimensions['new_height'],
			$this->current_dimensions['width'],
			$this->current_dimensions['height']
		);

		// update all the variables and resources to be correct
		$this->old_image					= $this->working_image;
		$this->current_dimensions['width']	= $this->new_dimensions['new_width'];
		$this->current_dimensions['height']	= $this->new_dimensions['new_height'];

		return $this;
	}

	/**
	 * Adaptively Resizes the Image
	 *
	 * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
	 * remaining overflow (from the center) to get the image to be the size specified
	 */
	public function adaptiveResize(int $width, int $height): GD
	{
		// make sure our arguments are valid
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

		// make sure we're not exceeding our image size if we're not supposed to
		$this->checkingMaxSize($width, $height);

		$this->calcImageSizeStrict($this->current_dimensions['width'], $this->current_dimensions['height']);

		// resize the image to be close to our desired dimensions
		$this->resize($this->new_dimensions['new_width'], $this->new_dimensions['new_height']);

		// reset the max dimensions...
		$this->checkingMaxSize($width, $height);

		// create the working image
		$this->working_image = imagecreatetruecolor($this->max_width, $this->max_height);

		$this->preserveAlpha();

		$crop_width		= $this->max_width;
		$crop_height	= $this->max_height;
		$crop_x			= 0;
		$crop_y			= 0;

		// now, figure out how to crop the rest of the image...
		if ($this->current_dimensions['width'] > $this->max_width)
		{
			$crop_x = intval(($this->current_dimensions['width'] - $this->max_width) / 2);
		}
		else if ($this->current_dimensions['height'] > $this->max_height)
		{
			$crop_y = intval(($this->current_dimensions['height'] - $this->max_height) / 2);
		}

		imagecopyresampled(
			$this->working_image,
			$this->old_image,
			0,
			0,
			$crop_x,
			$crop_y,
			$crop_width,
			$crop_height,
			$crop_width,
			$crop_height
		);

		// update all the variables and resources to be correct
		$this->old_image					= $this->working_image;
		$this->current_dimensions['width']	= $this->max_width;
		$this->current_dimensions['height']	= $this->max_height;

		return $this;
	}

	/**
	 * Adaptively Resizes the Image and Crops Using a Percentage
	 *
	 * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
	 * remaining overflow using a provided percentage to get the image to be the size specified.
	 *
	 * The percentage mean different things depending on the orientation of the original image.
	 *
	 * For Landscape images:
	 * ---------------------
	 *
	 * A percentage of 1 would crop the image all the way to the left, which would be the same as
	 * using adaptiveResizeQuadrant() with $quadrant = 'L'
	 *
	 * A percentage of 50 would crop the image to the center which would be the same as using
	 * adaptiveResizeQuadrant() with $quadrant = 'C', or even the original adaptiveResize()
	 *
	 * A percentage of 100 would crop the image to the image all the way to the right, etc., etc.
	 * Note that you can use any percentage between 1 and 100.
	 *
	 * For Portrait images:
	 * --------------------
	 *
	 * This works the same as for Landscape images except that a percentage of 1 means top and 100 means bottom
	 */
	public function adaptiveResizePercent(int $width, int $height, int $percent = 50): GD
	{
		// make sure our arguments are valid
		if ($width == 0)
		{
			throw new InvalidArgumentException('$width must be numeric and greater than zero');
		}

		if ($height == 0)
		{
			throw new InvalidArgumentException('$height must be numeric and greater than zero');
		}

		// make sure we're not exceeding our image size if we're not supposed to
		$this->checkingMaxSize($width, $height);

		$this->calcImageSizeStrict($this->current_dimensions['width'], $this->current_dimensions['height']);

		// resize the image to be close to our desired dimensions
		$this->resize($this->new_dimensions['new_width'], $this->new_dimensions['new_height']);

		// reset the max dimensions...
		$this->checkingMaxSize($width, $height);

		// create the working image
		$this->working_image = imagecreatetruecolor($this->max_width, $this->max_height);

		$this->preserveAlpha();

		$crop_width		= $this->max_width;
		$crop_height	= $this->max_height;
		$crop_x			= 0;
		$crop_y			= 0;

		// Crop the rest of the image using the quadrant

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
			// Image is landscape
			$max_crop_x	= $this->current_dimensions['width'] - $this->max_width;
			$crop_x		= intval(($percent / 100) * $max_crop_x);

		}
		else if ($this->current_dimensions['height'] > $this->max_height)
		{
			// Image is portrait
			$max_crop_y	= $this->current_dimensions['height'] - $this->max_height;
			$crop_y		= intval(($percent / 100) * $max_crop_y);
		}

		imagecopyresampled(
			$this->working_image,
			$this->old_image,
			0,
			0,
			$crop_x,
			$crop_y,
			$crop_width,
			$crop_height,
			$crop_width,
			$crop_height
		);

		// update all the variables and resources to be correct
		$this->old_image					= $this->working_image;
		$this->current_dimensions['width']	= $this->max_width;
		$this->current_dimensions['height']	= $this->max_height;

		return $this;
	}

	/**
	 * Adaptively Resizes the Image and Crops Using a Quadrant
	 *
	 * This function attempts to get the image to as close to the provided dimensions as possible, and then crops the
	 * remaining overflow using the quadrant to get the image to be the size specified.
	 *
	 * The quadrants available are Top, Bottom, Center, Left, and Right:
	 *
	 *
	 * +---+---+---+
	 * |   | T |   |
	 * +---+---+---+
	 * | L | C | R |
	 * +---+---+---+
	 * |   | B |   |
	 * +---+---+---+
	 *
	 * Note that if your image is Landscape and you choose either of the Top or Bottom quadrants (which won't
	 * make sense since only the Left and Right would be available, then the Center quadrant will be used
	 * to crop. This would have exactly the same result as using adaptiveResize().
	 * The same goes if your image is portrait and you choose either the Left or Right quadrants.
	 */
	public function adaptiveResizeQuadrant(int $width, int $height, string $quadrant = 'C'): GD
	{
		// make sure our arguments are valid
		if ($width == 0)
		{
			throw new InvalidArgumentException('$width must be numeric and greater than zero');
		}

		if ($height == 0)
		{
			throw new InvalidArgumentException('$height must be numeric and greater than zero');
		}

		// make sure we're not exceeding our image size if we're not supposed to
		$this->checkingMaxSize($width, $height);

		$this->calcImageSizeStrict($this->current_dimensions['width'], $this->current_dimensions['height']);

		// resize the image to be close to our desired dimensions
		$this->resize($this->new_dimensions['new_width'], $this->new_dimensions['new_height']);

		// reset the max dimensions...
		$this->checkingMaxSize($width, $height);

		// create the working image
		$this->working_image = imagecreatetruecolor($this->max_width, $this->max_height);


		$this->preserveAlpha();

		$crop_width		= $this->max_width;
		$crop_height	= $this->max_height;
		$crop_x			= 0;
		$crop_y			= 0;

		// Crop the rest of the image using the quadrant

		if ($this->current_dimensions['width'] > $this->max_width)
		{
			// Image is landscape
			$crop_x = match ($quadrant) {
				'L'		=> 0,
				'R'		=> intval(($this->current_dimensions['width'] - $this->max_width)),
				default	=> intval(($this->current_dimensions['width'] - $this->max_width) / 2),
			};
		}
		else if ($this->current_dimensions['height'] > $this->max_height)
		{
			// Image is portrait
			$crop_y = match ($quadrant) {
				'T'		=> 0,
				'B'		=> intval(($this->current_dimensions['height'] - $this->max_height)),
				default	=> intval(($this->current_dimensions['height'] - $this->max_height) / 2),
			};
		}

		imagecopyresampled(
			$this->working_image,
			$this->old_image,
			0,
			0,
			$crop_x,
			$crop_y,
			$crop_width,
			$crop_height,
			$crop_width,
			$crop_height
		);

		// update all the variables and resources to be correct
		$this->old_image					= $this->working_image;
		$this->current_dimensions['width']	= $this->max_width;
		$this->current_dimensions['height']	= $this->max_height;

		return $this;
	}

	/**
	 * Resizes an image by a given percent uniformly,
	 * Percentage should be whole number representation (i.e. 1-100)
	 *
	 * @throws InvalidArgumentException
	 */
	public function resizePercent(int $percent = 0): GD
	{
		$this->percent = $percent;

		$this->calcImageSizePercent($this->current_dimensions['width'], $this->current_dimensions['height']);

		return $this->resize($this->new_dimensions['new_width'], $this->new_dimensions['new_height']);
	}

	/**
	 * Crops an image from the center with provided dimensions
	 *
	 * If no height is given, the width will be used as a height, thus creating a square crop
	 */
	public function cropFromCenter(int $crop_width, ?int $crop_height = 0): GD
	{
		if ($crop_height == 0)
		{
			$crop_height = $crop_width;
		}

		$crop_width		= ($this->current_dimensions['width'] < $crop_width)	? $this->current_dimensions['width']	: $crop_width;
		$crop_height	= ($this->current_dimensions['height'] < $crop_height)	? $this->current_dimensions['height']	: $crop_height;

		$crop_x = intval(($this->current_dimensions['width'] - $crop_width) / 2);
		$crop_y = intval(($this->current_dimensions['height'] - $crop_height) / 2);

		$this->crop($crop_x, $crop_y, $crop_width, $crop_height);

		return $this;
	}

	/**
	 * Vanilla Cropping - Crops from x,y with specified width and height
	 */
	public function crop(int $start_x, int $start_y, int $crop_width, int $crop_height): GD
	{
		// do some calculations
		$crop_width		= ($this->current_dimensions['width'] < $crop_width)	? $this->current_dimensions['width']	: $crop_width;
		$crop_height	= ($this->current_dimensions['height'] < $crop_height)	? $this->current_dimensions['height']	: $crop_height;

		// ensure everything's in bounds
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

		// create the working image
		$this->working_image = imagecreatetruecolor($crop_width, $crop_height);

		$this->preserveAlpha();

		imagecopyresampled(
			$this->working_image,
			$this->old_image,
			0,
			0,
			$start_x,
			$start_y,
			$crop_width,
			$crop_height,
			$crop_width,
			$crop_height
		);

		$this->old_image					= $this->working_image;
		$this->current_dimensions['width']	= $crop_width;
		$this->current_dimensions['height']	= $crop_height;

		return $this;
	}

	/**
	 * Rotates image either 90 degrees clockwise or counter-clockwise
	 */
	public function rotateImage(string $direction = 'CW'): GD
	{
		$degrees = match ($direction) {
			'CW'		=> 90,
			default		=> -90,
		};

		$this->rotateImageNDegrees($degrees);

		return $this;
	}

	/**
	 * Rotates image specified number of degrees
	 */
	public function rotateImageNDegrees(int $degrees): GD
	{
		if (!function_exists('imagerotate'))
		{
			throw new RuntimeException('Your version of GD does not support image rotation');
		}

		$this->working_image = imagerotate($this->old_image, $degrees, 0);

		$this->old_image					= $this->working_image;
		$this->current_dimensions['width']	= imagesx($this->working_image);
		$this->current_dimensions['height']	= imagesy($this->working_image);

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
	 * @param string $direction The flip direction (see above).
	 * @return $this
	 *
	 * @throws RuntimeException If the GD build does not have imageflip() available.
	 * @throws InvalidArgumentException For an unknown direction string.
	 */
	public function flip(string $direction = 'horizontal'): GD
	{
		if (!function_exists('imageflip'))
		{
			throw new RuntimeException(
				'Your version of GD does not support imageflip(). ' .
				'Upgrade to PHP 8.0+ with bundled GD 2.1.1+, or use PHPThumb\Imagick.'
				);
		}

		$normalized = strtolower(trim($direction));

		$mode = match ($normalized) {
			'horizontal', 'h', 'lr' => IMG_FLIP_HORIZONTAL,
			'vertical',   'v', 'tb' => IMG_FLIP_VERTICAL,
			'both',       'hv'      => IMG_FLIP_BOTH,
			default => throw new InvalidArgumentException(
				"Unknown flip direction: '{$direction}'. " .
				"Expected 'horizontal', 'vertical', or 'both'."
					),
		};

		if (imageflip($this->old_image, $mode) === false)
		{
			throw new RuntimeException('GD imageflip() failed.');
		}

		// imageflip() does not change dimensions, but keep working_image in sync.
		$this->working_image = $this->old_image;

		return $this;
	}

	/**
	 * Auto-orients the image based on its EXIF orientation tag.
	 *
	 * Many cameras (especially phones) store images in their sensor's
	 * natural orientation and rely on an EXIF tag indicating the rotation
	 * needed for display. Without this step, phone photos appear sideways
	 * on the web.
	 *
	 * Orientation values 1–8 are handled:
	 *
	 *  - 1 → no change (normal)
	 *  - 2 → mirror horizontal
	 *  - 3 → rotate 180°
	 *  - 4 → mirror vertical
	 *  - 5 → mirror horizontal + rotate 270° CW
	 *  - 6 → rotate 90° CW
	 *  - 7 → mirror horizontal + rotate 90° CW
	 *  - 8 → rotate 270° CW
	 *
	 * The method is a **graceful no-op** when:
	 *  - `ext-exif` is not loaded
	 *  - the source has no EXIF block
	 *  - the orientation tag is absent or `1` (normal)
	 *  - the source is not JPEG (only JPEG reliably carries EXIF in GD-land;
	 *    HEIC/TIFF go through Imagick, which has its own native handling)
	 *
	 * The image's current dimensions are refreshed after any rotation.
	 *
	 * @return $this
	 */
	public function autoOrient(): GD
	{
		// Graceful no-op when EXIF extension isn't available.
		if (!function_exists('exif_read_data'))
		{
			return $this;
		}

		// Only JPEG reliably carries EXIF in GD's toolchain. For other formats,
		// silently skip — Imagick handles them natively via its own autoOrient().
		if ($this->format !== 'JPEG')
		{
			return $this;
		}

		$exif = @exif_read_data($this->file_name, 'IFD0', false, false);

		if (!is_array($exif) || !isset($exif['Orientation']))
		{
			return $this;
		}

		$orientation = (int) $exif['Orientation'];

		if ($orientation === 1 || $orientation < 1 || $orientation > 8)
		{
			return $this;
		}

		switch ($orientation)
		{
			case 2: // mirror horizontal
				imageflip($this->old_image, IMG_FLIP_HORIZONTAL);
				break;

			case 3: // rotate 180
				$this->rotateImageNDegrees(180);
				break;

			case 4: // mirror vertical
				imageflip($this->old_image, IMG_FLIP_VERTICAL);
				break;

			case 5: // mirror horizontal + rotate 270 CW
				imageflip($this->old_image, IMG_FLIP_HORIZONTAL);
				$this->rotateImageNDegrees(270);
				break;

			case 6: // rotate 90 CW
				$this->rotateImageNDegrees(90);
				break;

			case 7: // mirror horizontal + rotate 90 CW
				imageflip($this->old_image, IMG_FLIP_HORIZONTAL);
				$this->rotateImageNDegrees(90);
				break;

			case 8: // rotate 270 CW (== 90 CCW)
				$this->rotateImageNDegrees(270);
				break;
		}

		return $this;
	}

	/**
	 * Applies output-gamma correction to the image.
	 *
	 * Wraps GD's imagegammacorrect(). The input gamma is assumed to be 1.0
	 * (sRGB). Values below 1.0 darken the image, values above 1.0 brighten.
	 * Realistic sRGB adjustment is in the 0.5–2.0 range.
	 *
	 * The image dimensions are unchanged. Alpha is preserved.
	 *
	 * @param float $correction The output gamma (1.0 = no change).
	 * @return $this
	 *
	 * @throws RuntimeException If the GD build does not have imagegammacorrect() available.
	 */
	public function gamma(float $correction): GD
	{
		if (!function_exists('imagegammacorrect'))
		{
			throw new RuntimeException(
				'Your version of GD does not support imagegammacorrect().'
				);
		}

		if (imagegammacorrect($this->old_image, 1.0, $correction) === false)
		{
			throw new RuntimeException('GD imagegammacorrect() failed.');
		}

		$this->working_image = $this->old_image;

		return $this;
	}

	/**
	 * Sharpens the image using an unsharp-mask style 3×3 convolution.
	 *
	 * The kernel is the classic "high-pass" sharpen matrix with the strength
	 * modulated by $amount. Higher values produce a stronger sharpening effect.
	 *
	 *  - $amount = 0   → no-op
	 *  - $amount = 50  → moderate sharpening (default)
	 *  - $amount = 100 → strongest sharpening
	 *
	 * Dimensions are unchanged. Alpha is preserved.
	 *
	 * @param int $amount Sharpening strength, 0–100.
	 * @return $this
	 *
	 * @throws RuntimeException If the GD build does not have imageconvolution() available.
	 * @throws InvalidArgumentException For out-of-range amounts.
	 */
	public function sharpen(int $amount = 50): GD
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

		if (!function_exists('imageconvolution'))
		{
			throw new RuntimeException(
				'Your version of GD does not support imageconvolution().'
				);
		}

		// Map $amount (0..100) → bias (0..1): 0 = neutral, 1 = max contrast.
		// The 3x3 high-pass kernel produces an unsharp-mask-like effect when
		// the central weight is biased toward "amount".
		//
		// For a 3x3 kernel of all -1 with center c, the sum of all weights
		// is c - 8. imageconvolution() divides by the divisor argument, so
		// divisor must equal that sum — otherwise flat regions are scaled
		// by (c-8)/c. The `max(9, …)` clamp keeps the center weight above 8
		// so the divisor is never zero (which would happen for very small
		// amounts once rounded).
		$bias = $amount / 100.0;

		$center = max(9, (int) round(8 + (8 * $bias)));
		$divisor = $center - 8;
		$offset = 0;

		$matrix = [
			[-1, -1, -1],
			[-1, $center, -1],
			[-1, -1, -1],
		];

		if (imageconvolution($this->old_image, $matrix, $divisor, $offset) === false)
		{
			throw new RuntimeException('GD imageconvolution() failed.');
		}

		$this->working_image = $this->old_image;

		return $this;
	}

	/**
	 * Applies a filter to the image
	 */
	public function imageFilter(int $filter, bool $arg1 = false, bool $arg2 = false, bool $arg3 = false, bool $arg4 = false): GD
	{
		if (!function_exists('imagefilter'))
		{
			throw new RuntimeException('Your version of GD does not support image filters');
		}

		if ($arg1 === false)
		{
			$result = imagefilter($this->old_image, $filter);
		}
		else if ($arg2 === false)
		{
			$result = imagefilter($this->old_image, $filter, $arg1);
		}
		else if ($arg3 === false)
		{
			$result = imagefilter($this->old_image, $filter, $arg1, $arg2);
		}
		else if ($arg4 === false)
		{
			$result = imagefilter($this->old_image, $filter, $arg1, $arg2, $arg3);
		}
		else
		{
			$result = imagefilter($this->old_image, $filter, $arg1, $arg2, $arg3, $arg4);
		}

		if (!$result)
		{
			throw new RuntimeException('GD imagefilter failed');
		}

		$this->working_image = $this->old_image;

		return $this;
	}

	/**
	 * Internal helper that applies imagefilter() with arbitrary numeric arguments
	 * and consistent error handling. Used by the chainable filter wrappers below.
	 *
	 * @throws RuntimeException If imagefilter() is unavailable or fails.
	 */
	protected function applyGdFilter(int $filter, int|float ...$args): GD
	{
		if (!function_exists('imagefilter'))
		{
			throw new RuntimeException('Your version of GD does not support image filters');
		}

		$result = imagefilter($this->old_image, $filter, ...$args);

		if (!$result)
		{
			throw new RuntimeException('GD imagefilter() failed for filter: ' . $filter);
		}

		$this->working_image = $this->old_image;

		return $this;
	}

	/**
	 * Desaturates the image (chainable convenience wrapper).
	 *
	 * Wraps IMG_FILTER_GRAYSCALE. The image dimensions are unchanged.
	 */
	public function grayscale(): GD
	{
		return $this->applyGdFilter(IMG_FILTER_GRAYSCALE);
	}

	/**
	 * Adjusts brightness (chainable convenience wrapper).
	 *
	 * Wraps IMG_FILTER_BRIGHTNESS. The image dimensions are unchanged.
	 *
	 * @param int $level Range -255 (full black) .. 255 (full white). 0 = no change.
	 */
	public function brightness(int $level): GD
	{
		return $this->applyGdFilter(IMG_FILTER_BRIGHTNESS, $level);
	}

	/**
	 * Adjusts contrast (chainable convenience wrapper).
	 *
	 * Wraps IMG_FILTER_CONTRAST. The image dimensions are unchanged.
	 *
	 * @param int $level Range -100 (flat) .. 100 (max contrast). 0 = no change.
	 */
	public function contrast(int $level): GD
	{
		return $this->applyGdFilter(IMG_FILTER_CONTRAST, $level);
	}

	/**
	 * Applies a Gaussian blur (chainable convenience wrapper).
	 *
	 * Wraps IMG_FILTER_GAUSSIAN_BLUR. The image dimensions are unchanged.
	 *
	 * @param int|float $amount Blur amount. Typical range 0..10; passed
	 *                          directly through to imagefilter().
	 */
	public function blur(int|float $amount = 1): GD
	{
		return $this->applyGdFilter(IMG_FILTER_GAUSSIAN_BLUR, $amount);
	}

	/**
	 * Applies a pixelation effect (chainable convenience wrapper).
	 *
	 * Wraps IMG_FILTER_PIXELATE. The image dimensions are unchanged.
	 *
	 * @param int $block_size Pixel block size in pixels. Must be >= 1.
	 *                        $block_size = 1 is a no-op.
	 */
	public function pixelate(int $block_size = 10): GD
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

		// GD's pixelate takes (block_size, use_advanced_effect).
		return $this->applyGdFilter(IMG_FILTER_PIXELATE, $block_size, true);
	}

	/**
	 * Applies an edge-detection filter (chainable convenience wrapper).
	 *
	 * Wraps IMG_FILTER_EDGEDETECT. The image dimensions are unchanged.
	 */
	public function edgeDetect(): GD
	{
		return $this->applyGdFilter(IMG_FILTER_EDGEDETECT);
	}

	/**
	 * Applies an emboss filter (chainable convenience wrapper).
	 *
	 * Wraps IMG_FILTER_EMBOSS. The image dimensions are unchanged.
	 */
	public function emboss(): GD
	{
		return $this->applyGdFilter(IMG_FILTER_EMBOSS);
	}

	/**
	 * Applies a smoothing pass (chainable convenience wrapper).
	 *
	 * Wraps IMG_FILTER_SMOOTH. The image dimensions are unchanged.
	 *
	 * @param int $level Range -10 (heavy blur) .. 10 (sharpening pass).
	 */
	public function smooth(int $level = 1): GD
	{
		return $this->applyGdFilter(IMG_FILTER_SMOOTH, $level);
	}

	/**
	 * Shows an image
	 *
	 * This function will show the current image by first sending the appropriate header
	 * for the format, and then outputting the image data. If headers have already been sent,
	 * a runtime exception will be thrown
	 *
	 * @param bool $raw_data Whether or not the raw image stream should be output
	 */
	public function show(bool $raw_data = false): GD
	{
		//Execute any plugins
		if ($this->plugins)
		{
			foreach ($this->plugins as $plugin)
			{
				/* @var $plugin PluginInterface */
				$plugin->execute($this);
			}
		}

		if (headers_sent() && php_sapi_name() != 'cli')
		{
			throw new RuntimeException('Cannot show image, headers have already been sent');
		}

		// When the interlace option equals true or false call imageinterlace else leave it to default
		if ($this->options['interlace'] === true)
		{
			imageinterlace($this->old_image, 1);
		}
		else if ($this->options['interlace'] === false)
		{
			imageinterlace($this->old_image, 0);
		}

		switch ($this->format)
		{
			case 'AVIF':
				if ($raw_data === false)
				{
					header('Content-type: image/avif');
				}
				imageavif($this->old_image, null, $this->options['avifQuality']);
				break;
			case 'GIF':
				if ($raw_data === false)
				{
					header('Content-type: image/gif');
				}
				imagegif($this->old_image);
				break;
			case 'JPEG':
				if ($raw_data === false)
				{
					header('Content-type: image/jpeg');
				}
				imagejpeg($this->old_image, null, $this->options['jpegQuality']);
				break;
			case 'PNG':
			case 'STRING':
				if ($raw_data === false)
				{
					header('Content-type: image/png');
				}
				imagepng($this->old_image);
				break;
			case 'WEBP':
				if ($raw_data === false)
				{
					header('Content-type: image/webp');
				}
				imagewebp($this->old_image, null, $this->options['webpQuality']);
				break;
		}

		return $this;
	}

	/**
	 * Returns the Working Image as a String
	 *
	 * This function is useful for getting the raw image data as a string for storage in
	 * a database, or other similar things.
	 */
	public function getImageAsString(): string
	{
		ob_start();
		$this->show(true);
		$data = ob_get_contents();
		ob_end_clean();

		return $data;
	}

	/**
	 * Saves an image
	 *
	 * This function will make sure the target directory is writeable, and then save the image.
	 *
	 * If the target directory is not writeable, the function will try to correct the permissions (if allowed, this
	 * is set as an option ($this->options['correctPermissions']).  If the target cannot be made writeable, then a
	 * \RuntimeException is thrown.
	 *
	 * @param string		$file_name	The full path and filename of the image to save
	 * @param string|null	$format		The format to save the image in (optional, must be one of [AVIF, GIF, JPEG, JPG, PNG, WEBP]
	 */
	public function save(string $file_name, ?string $format = null): GD
	{
		$valid_formats	= ['AVIF', 'GIF', 'JPEG', 'JPG', 'PNG', 'WEBP'];
		$format			= ($format !== null) ? strtoupper($format) : $this->format;

		if (!in_array($format, $valid_formats))
		{
			throw new InvalidArgumentException('Invalid format type specified in save function: ' . $format);
		}

		// make sure the directory is writeable
		if (!is_writeable(dirname($file_name)))
		{
			// try to correct the permissions
			if ($this->options['correctPermissions'] === true)
			{
				@chmod(dirname($file_name), 0777);

				// throw an exception if not writeable
				if (!is_writeable(dirname($file_name)))
				{
					throw new RuntimeException('File is not writeable, and could not correct permissions: ' . $file_name);
				}
			}
			else
			{ // throw an exception if not writeable
				throw new RuntimeException('File not writeable: ' . $file_name);
			}
		}

		// When the interlace option equals true or false call imageinterlace else leave it to default
		if ($this->options['interlace'] === true)
		{
			imageinterlace($this->old_image, 1);
		}
		else if ($this->options['interlace'] === false)
		{
			imageinterlace($this->old_image, 0);
		}

		$save = match ($format) {
			'AVIF'			=> imageavif	($this->old_image, $file_name, $this->options['avifQuality']),
			'GIF'			=> imagegif		($this->old_image, $file_name),
			'JPEG', 'JPG'	=> imagejpeg	($this->old_image, $file_name, $this->options['jpegQuality']),
			'PNG'			=> imagepng		($this->old_image, $file_name),
			'WEBP'			=> imagewebp	($this->old_image, $file_name, $this->options['webpQuality']),
		};

		return $this;
	}

	#################################
	# ----- GETTERS / SETTERS ----- #
	#################################

	/**
	 * Sets options for all operations.
	 */
	public function setOptions(array $options = []): GD
	{
		// we've yet to init the default options, so create them here
		if (count($this->options) == 0)
		{
			$default_options = [
				'resizeUp'				=> false,
				'avifQuality'			=> 100,
				'jpegQuality'			=> 100,
				'webpQuality'			=> 100,
				'correctPermissions'	=> false,
				'preserveAlpha'			=> true,
				'alphaMaskColor'		=> [255, 255, 255],
				'preserveTransparency'	=> true,
				'transparencyMaskColor'	=> [0, 0, 0],
				'interlace'				=> null,
				'sharpenAmount'         => 50,
				'textFont'              => null,
				'textDefaultSize'       => 12,
			];
		}
		else
		{
			// otherwise, let's use what we've got already
			$default_options = $this->options;
		}

		$this->options = array_merge($default_options, $options);

		return $this;
	}

	/**
	 * Returns $current_dimensions.
	 *
	 * @see GD
	 */
	public function getCurrentDimensions(): array
	{
		return $this->current_dimensions;
	}

	public function setCurrentDimensions(array $current_dimensions): GD
	{
		$this->current_dimensions = $current_dimensions;

		return $this;
	}

	public function getMaxHeight(): int
	{
		return $this->max_height;
	}

	public function setMaxHeight(int $max_height): GD
	{
		$this->max_height = $max_height;

		return $this;
	}

	public function getMaxWidth(): int
	{
		return $this->max_width;
	}

	public function setMaxWidth(int $max_width): GD
	{
		$this->max_width = $max_width;

		return $this;
	}

	/**
	 * Returns $new_dimensions.
	 *
	 * @see GD
	 */
	public function getNewDimensions(): array
	{
		return $this->new_dimensions;
	}

	/**
	 * Sets $new_dimensions.
	 *
	 * @see GD
	 */
	public function setNewDimensions(array $new_dimensions): GD
	{
		$this->new_dimensions = $new_dimensions;

		return $this;
	}

	/**
	 * Returns $options.
	 *
	 * @see GD
	 */
	public function getOptions(): array
	{
		return $this->options;
	}

	/**
	 * Returns $percent.
	 *
	 * @see GD
	 */
	public function getPercent(): int
	{
		return $this->percent;
	}

	/**
	 * Sets $percent.
	 *
	 * @see GD
	 */
	public function setPercent(int $percent): GD
	{
		$this->percent = $percent;

		return $this;
	}

	/**
	 * Returns $old_image.
	 *
	 * @see GD
	 */
	public function getOldImage()
	{
		return $this->old_image;
	}

	/**
	 * Sets $old_image.
	 *
	 * @see GD
	 */
	public function setOldImage(\GdImage $old_image): static
	{
		$this->old_image = $old_image;

		return $this;
	}

	/**
	 * Returns $working_image.
	 *
	 * @see GD
	 */
	public function getWorkingImage()
	{
		return $this->working_image;
	}

	/**
	 * Sets $working_image.
	 *
	 * @see GD
	 */
	public function setWorkingImage(\GdImage $working_image): static
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
		$new_width_percentage	= (100 * $this->max_width) / $width;
		$new_height				= ($height * $new_width_percentage) / 100;

		return [
			'new_width'		=> $this->max_width,
			'new_height'	=> intval($new_height)
		];
	}

	/**
	 * Calculates a new width and height for the image based on $this->max_height and the provided dimensions
	 */
	protected function calcHeight(int $width, int $height): array
	{
		$new_height_percentage	= (100 * $this->max_height) / $height;
		$new_width				= ($width * $new_height_percentage) / 100;

		return [
			'new_width'		=> ceil($new_width),
			'new_height'	=> ceil($this->max_height)
		];
	}

	/**
	 * Calculates a new width and height for the image based on $this->percent and the provided dimensions
	 */
	protected function calcPercent(int $width, int $height): array
	{
		$new_width	= ($width * $this->percent) / 100;
		$new_height	= ($height * $this->percent) / 100;

		return [
			'new_width'		=> ceil($new_width),
			'new_height'	=> ceil($new_height)
		];
	}

	/**
	 * Calculates the new image dimensions
	 *
	 * These calculations are based on both the provided dimensions and $this->max_width and $this->max_height
	 */
	protected function calcImageSize(int $width, int $height): void
	{
		$new_size = [
			'new_width'		=> $width,
			'new_height'	=> $height
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

		// first, we need to determine what the longest resize dimension is..
		if ($this->max_width >= $this->max_height)
		{
			// and determine the longest original dimension
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
	 *
	 * This function will throw exceptions for invalid images / mime-types
	 *
	 * @throws Exception
	 */
	protected function determineFormat(): void
	{
		$format_info = getimagesize($this->file_name);

		// non-image files will return false
		if ($format_info === false)
		{
			if ($this->remote_image)
			{
				throw new Exception('Could not determine format of remote image: ' . $this->file_name);
			}
			else
			{
				throw new Exception('File is not a valid image: ' . $this->file_name);
			}
		}

		$mime_type = $format_info['mime'] ?? null;

		$this->format = match ($mime_type) {
			'image/avif'	=> 'AVIF',
			'image/gif'		=> 'GIF',
			'image/jpeg'	=> 'JPEG',
			'image/png'		=> 'PNG',
			'image/webp'	=> 'WEBP',
			default			=> throw new Exception('Image format not supported: ' . $mime_type),
		};
	}

	/**
	 * Makes sure the correct GD implementation exists for the file type
	 *
	 * @throws Exception
	 */
	protected function verifyFormatCompatibility(): void
	{
		$gd_info = gd_info();

		$is_compatible = match ($this->format) {
			'AVIF', 'JPEG', 'PNG'	=> $gd_info[$this->format . ' Support'],
			'GIF'					=> $gd_info['GIF Create Support'],
			'WEBP'					=> $gd_info['WebP Support'],
			default					=> false,
		};

		$suffix = strtolower($this->format);

		$is_compatible =
			   function_exists('image' . $suffix)
			&& function_exists('imagecreatefrom' . $suffix)
			&& $is_compatible;

		if (!$is_compatible)
		{
			throw new Exception('Your GD installation does not support ' . $this->format . ' image types');
		}
	}

	/**
	 * Preserves the alpha or transparency for PNG and GIF files
	 *
	 * Alpha / transparency will not be preserved if the appropriate options are set to false.
	 * Also, the GIF transparency is pretty skunky (the results aren't awesome), but it works like a
	 * champ... that's the nature of GIFs tho, so no huge surprise.
	 */
	protected function preserveAlpha(): void
	{
		if ($this->format === 'PNG' && $this->options['preserveAlpha'] === true)
		{
			imagealphablending($this->working_image, false);

			$color_transparent = imagecolorallocatealpha(
				$this->working_image,
				$this->options['alphaMaskColor'][0],
				$this->options['alphaMaskColor'][1],
				$this->options['alphaMaskColor'][2],
				0
			);

			imagefill		($this->working_image, 0, 0, $color_transparent);
			imagesavealpha	($this->working_image, true);
		}

		// preserve transparency in GIFs... this is usually pretty rough tho
		if ($this->format === 'GIF' && $this->options['preserveTransparency'] === true)
		{
			$color_transparent = imagecolorallocate(
				$this->working_image,
				$this->options['transparencyMaskColor'][0],
				$this->options['transparencyMaskColor'][1],
				$this->options['transparencyMaskColor'][2]
			);

			imagecolortransparent	($this->working_image, $color_transparent);
			imagetruecolortopalette	($this->working_image, true, 256);
		}

		if ($this->format === 'WEBP' && $this->options['preserveTransparency'] === true)
		{
			imagealphablending($this->working_image, false);

			// Create a palette image to copy the color palette from
			$palette_image = imagecreatetruecolor(1, 1);

			imagepalettecopy($palette_image, $this->working_image);
			imagepalettecopy($this->working_image, $palette_image);
		}
	}
}

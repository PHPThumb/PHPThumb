<?php

namespace PHPThumb\Plugins;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPThumb\Imagick;
use PHPThumb\PHPThumb;
use PHPThumb\PluginInterface;
use RuntimeException;

/**
 * Trim Lib Plugin Definition File
 *
 * This file contains the plugin definition for the GD Trim Lib for PHP Thumb
 *
 * PhpThumb : PHP Thumb Library <https://github.com/PHPThumb/PHPThumb>
 * Copyright (c) 2016, Oleg Sherbakov
 *
 * Licensed under the MIT License
 *
 * @author Oleg Sherbakov <holdmann@yandex.ru>
 * @copyright Copyright (c) 2016
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @version 1.0
 * @package PhpThumb
 * @subpackage Plugins
 */
class Trim implements PluginInterface
{
	/**
	 * @var array<int, int> RGB color values [R, G, B]
	 */
	protected array $color;

	/**
	 * @var array<string> Sides to trim (T, B, L, R)
	 */
	protected array $sides;

	/**
	 * Trim constructor
	 *
	 * @param array<int, int> $color RGB color to trim as array [R, G, B] (0-255 each)
	 * @param string $sides Sides to trim: 'T' (top), 'B' (bottom), 'L' (left), 'R' (right)
	 * @throws InvalidArgumentException If color or sides are invalid
	 */
	public function __construct(array $color = [255, 255, 255], string $sides = 'TBLR')
	{
		if (!$this->validateColor($color)) {
			throw new InvalidArgumentException(
				'Color must be an array of RGB color model parts [R, G, B] where each value is 0-255'
			);
		}

		if (!$this->validateSides($sides)) {
			throw new InvalidArgumentException(
				'Sides must be a string containing any combination of T, B, L, and R'
			);
		}

		$this->color  = $color;
		$this->sides  = str_split($sides);
	}

	/**
	 * Validates whether RGB color array is valid
	 *
	 * @param array<int, int|float> $colors Color array to validate
	 * @return bool True if valid, false otherwise
	 */
	protected function validateColor(array $colors): bool
	{
		if (count($colors) !== 3) {
			return false;
		}

		foreach ($colors as $color) {
			if (!is_numeric($color) || $color < 0 || $color > 255) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validates whether sides string is valid
	 *
	 * @param string $sides_string Sides string to validate
	 * @return bool True if valid, false otherwise
	 */
	protected function validateSides(string $sides_string): bool
	{
		$sides = str_split($sides_string);

		if (count($sides) === 0 || count($sides) > 4) {
			return false;
		}

		$valid_sides = ['T', 'B', 'L', 'R'];

		foreach ($sides as $side) {
			if (!in_array($side, $valid_sides, true)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Executes the trim operation
	 */
	public function execute(PHPThumb $phpthumb): PHPThumb
	{
		if ($phpthumb instanceof Imagick) {
			return $this->executeImagick($phpthumb);
		}

		if ($phpthumb instanceof GD) {
			return $this->executeGD($phpthumb);
		}

		throw new InvalidArgumentException('Unsupported PHPThumb instance type');
	}

	/**
	 * Execute trim for Imagick-based PHPThumb.
	 *
	 * Mirrors the GD branch:
	 *   - Walks inward from each requested side looking for a row/column that
	 *     contains at least one pixel NOT matching the trim colour (RGB only).
	 *   - Alpha is intentionally NOT considered in the match decision. The GD
	 *     branch has a special case "white-with-alpha counts as a match" but it
	 *     only ever fires for fully-transparent pixels, which carry no visual
	 *     content and can be safely cropped. We skip the special case entirely
	 *     to avoid Imagick's opaque-by-default alpha returning false matches.
	 *
	 * Imagick returns RGB from ImagickPixel::getColor() as int values in
	 * [0, 255] regardless of the build's Quantum depth (this is documented
	 * Imagick 3.x behaviour). So we compare against the user's 8-bit input
	 * directly — no Quantum scaling needed.
	 */
	protected function executeImagick(Imagick $phpthumb): PHPThumb
	{
		$current_image      = $phpthumb->getOldImage();
		$current_dimensions = $phpthumb->getCurrentDimensions();

		if ($current_image === null) {
			throw new RuntimeException('Image is not initialized');
		}

		$width  = $current_dimensions['width'];
		$height = $current_dimensions['height'];

		$border_top    = 0;
		$border_bottom = 0;
		$border_left   = 0;
		$border_right  = 0;

		// Imagick's ImagickPixel::getColor() returns RGB as int in [0, 255] on
		// all currently-supported builds. Compare against the user-supplied
		// values directly.
		$target_r = $this->color[0];
		$target_g = $this->color[1];
		$target_b = $this->color[2];

		// ----- Detect top border -----
		if (in_array('T', $this->sides, true)) {
			for ($border_top = 0; $border_top < $height; $border_top++) {
				if ($this->rowHasNonMatchingPixel($current_image, $width, $height, $border_top, $target_r, $target_g, $target_b)) {
					break;
				}
			}
		}

		// ----- Detect bottom border -----
		if (in_array('B', $this->sides, true)) {
			for ($border_bottom = 0; $border_bottom < $height; $border_bottom++) {
				$y = $height - $border_bottom - 1;

				if ($this->rowHasNonMatchingPixel($current_image, $width, $height, $y, $target_r, $target_g, $target_b)) {
					break;
				}
			}
		}

		// ----- Detect left border -----
		if (in_array('L', $this->sides, true)) {
			for ($border_left = 0; $border_left < $width; $border_left++) {
				if ($this->columnHasNonMatchingPixel($current_image, $width, $height, $border_left, $target_r, $target_g, $target_b)) {
					break;
				}
			}
		}

		// ----- Detect right border -----
		if (in_array('R', $this->sides, true)) {
			for ($border_right = 0; $border_right < $width; $border_right++) {
				$x = $width - $border_right - 1;

				if ($this->columnHasNonMatchingPixel($current_image, $width, $height, $x, $target_r, $target_g, $target_b)) {
					break;
				}
			}
		}

		// ----- Calculate new dimensions -----
		$new_width  = $width  - $border_left - $border_right;
		$new_height = $height - $border_top  - $border_bottom;

		if ($new_width <= 0 || $new_height <= 0) {
			throw new RuntimeException('Trim operation would result in empty image');
		}

		// ----- Crop to the bounding box -----
		$current_image->cropImage(
			$new_width,
			$new_height,
			$border_left,
			$border_top
			);
		$current_image->setImagePage($new_width, $new_height, 0, 0);

		// Update PHPThumb
		$phpthumb->setOldImage($current_image);
		$phpthumb->setCurrentDimensions([
			'width'  => $new_width,
			'height' => $new_height,
		]);

		return $phpthumb;
	}

	/**
	 * Execute trim for GD-based PHPThumb.
	 *
	 * Mirrors the Imagick branch:
	 *
	 *   - Walks inward from each requested side looking for a row/column
	 *     that contains at least one pixel NOT matching the trim colour
	 *     (RGB only).
	 *   - Fully transparent pixels count as a match regardless of their RGB
	 *     value: they carry no visual content, so they can safely be
	 *     cropped away. The Imagick branch skips the special case because
	 *     its alpha channel is opaque by default.
	 *
	 * GD has no in-place crop, so the trimmed region is copied into a fresh
	 * image. Truecolor sources (JPEG, PNG, WEBP) are copied into a truecolor
	 * canvas; palette sources (GIF) keep their palette and their transparent
	 * colour index.
	 */
	protected function executeGD(GD $phpthumb): PHPThumb
	{
		$current_image      = $phpthumb->getOldImage();
		$current_dimensions = $phpthumb->getCurrentDimensions();
		$options            = $phpthumb->getOptions();

		if ($current_image === null)
		{
			throw new RuntimeException('Image is not initialized');
		}

		$width  = $current_dimensions['width'];
		$height = $current_dimensions['height'];

		$border_top    = 0;
		$border_bottom = 0;
		$border_left   = 0;
		$border_right  = 0;

		$target_r = $this->color[0];
		$target_g = $this->color[1];
		$target_b = $this->color[2];

		// ----- Detect top border -----
		if (in_array('T', $this->sides, true))
		{
			for ($border_top = 0; $border_top < $height; $border_top++)
			{
				if ($this->gdRowHasNonMatchingPixel($current_image, $width, $height, $border_top, $target_r, $target_g, $target_b))
				{
					break;
				}
			}
		}

		// ----- Detect bottom border -----
		if (in_array('B', $this->sides, true)) {
			for ($border_bottom = 0; $border_bottom < $height; $border_bottom++) {
				$y = $height - $border_bottom - 1;

				if ($this->gdRowHasNonMatchingPixel($current_image, $width, $height, $y, $target_r, $target_g, $target_b)) {
					break;
				}
			}
		}

		// ----- Detect left border -----
		if (in_array('L', $this->sides, true)) {
			for ($border_left = 0; $border_left < $width; $border_left++) {
				if ($this->gdColumnHasNonMatchingPixel($current_image, $width, $height, $border_left, $target_r, $target_g, $target_b)) {
					break;
				}
			}
		}

		// ----- Detect right border -----
		if (in_array('R', $this->sides, true)) {
			for ($border_right = 0; $border_right < $width; $border_right++) {
				$x = $width - $border_right - 1;

				if ($this->gdColumnHasNonMatchingPixel($current_image, $width, $height, $x, $target_r, $target_g, $target_b)) {
					break;
				}
			}
		}

		// ----- Calculate new dimensions -----
		$new_width  = $width  - $border_left - $border_right;
		$new_height = $height - $border_top  - $border_bottom;

		if ($new_width <= 0 || $new_height <= 0) {
			throw new RuntimeException('Trim operation would result in empty image');
		}

		// ----- Copy the trimmed region into a new image -----
		if (imageistruecolor($current_image)) {
			$working_image = imagecreatetruecolor($new_width, $new_height);
		} else {
			// Palette source (GIF): keep the palette so the trimmed image
			// still uses the original colours.
			$working_image = imagecreate($new_width, $new_height);
			imagepalettecopy($working_image, $current_image, 0, 0, 0, 0, $width, $height);
		}

		if ($working_image === false) {
			throw new RuntimeException('Failed to create canvas for trim operation');
		}

		// PNG sources keep their alpha channel.
		if ($phpthumb->getFormat() === 'PNG' && ($options['preserveAlpha'] ?? true) === true) {
			imagealphablending($working_image, false);
			imagesavealpha($working_image, true);
		}

		imagecopy(
			$working_image,
			$current_image,
			0, 0,
			$border_left, $border_top,
			$new_width, $new_height
			);

		// Palette sources keep their transparent colour index.
		if (!imageistruecolor($current_image) && ($options['preserveTransparency'] ?? true) === true) {
			$transparent = imagecolortransparent($current_image);

			if ($transparent >= 0) {
				imagecolortransparent($working_image, $transparent);
			}
		}

		// Update PHPThumb
		$phpthumb->setOldImage($working_image);
		$phpthumb->setCurrentDimensions([
			'width'  => $new_width,
			'height' => $new_height,
		]);

		return $phpthumb;
	}

	/**
	 * Returns true if the given row contains at least one pixel that does NOT
	 * match the trim colour (RGB only; fully transparent pixels count as a
	 * match).
	 */
	protected function gdRowHasNonMatchingPixel(
		\GdImage $image,
		int $width,
		int $height,
		int $y,
		int $target_r,
		int $target_g,
		int $target_b
		): bool {
			for ($x = 0; $x < $width; $x++) {
				[$r, $g, $b, $a] = $this->gdPixelRgba($image, $x, $y);

				// Fully transparent pixels carry no visual content, so they always
				// count as a match (and can therefore be trimmed away).
				if ($a === 127) {
					continue;
				}

				if ($r !== $target_r || $g !== $target_g || $b !== $target_b) {
					return true;
				}
			}

			return false;
	}

	/**
	 * Returns true if the given column contains at least one pixel that does
	 * NOT match the trim colour (RGB only; fully transparent pixels count as
	 * a match).
	 */
	protected function gdColumnHasNonMatchingPixel(
		\GdImage $image,
		int $width,
		int $height,
		int $x,
		int $target_r,
		int $target_g,
		int $target_b
		): bool {
			for ($y = 0; $y < $height; $y++) {
				[$r, $g, $b, $a] = $this->gdPixelRgba($image, $x, $y);

				if ($a === 127) {
					continue;
				}

				if ($r !== $target_r || $g !== $target_g || $b !== $target_b) {
					return true;
				}
			}

			return false;
	}

	/**
	 * Reads a pixel and returns its [r, g, b, a] components.
	 *
	 * Truecolor images are decoded straight from the ARGB value; palette
	 * images are looked up through imagecolorsforindex(). The alpha value is
	 * GD's 0..127 scale, where 0 is opaque and 127 is fully transparent.
	 *
	 * @return array{0: int, 1: int, 2: int, 3: int}
	 */
	protected function gdPixelRgba(\GdImage $image, int $x, int $y): array
	{
		$color = imagecolorat($image, $x, $y);

		if (imageistruecolor($image)) {
			return [
				($color >> 16) & 0xFF,
				($color >> 8) & 0xFF,
				$color & 0xFF,
				($color >> 24) & 0x7F,
			];
		}

		$rgba = imagecolorsforindex($image, $color);

		return [
			(int) $rgba['red'],
			(int) $rgba['green'],
			(int) $rgba['blue'],
			(int) $rgba['alpha'],
		];
	}

	/**
	 * Returns true if the given row contains at least one pixel that does NOT
	 * match the trim colour (RGB only, no alpha considered).
	 */
	protected function rowHasNonMatchingPixel(
		\Imagick $image,
		int $width,
		int $height,
		int $y,
		int $target_r,
		int $target_g,
		int $target_b
		): bool {
			$pixel_iterator = $image->getPixelRegionIterator(0, $y, $width, 1);

			foreach ($pixel_iterator as $row) {
				foreach ($row as $pixel) {
					$colors = $pixel->getColor();

					// getColor() returns RGB as int in [0, 255]. Compare directly.
					if ($colors['r'] !== $target_r
						|| $colors['g'] !== $target_g
						|| $colors['b'] !== $target_b) {
							$pixel_iterator->clear();
							return true;
						}
				}
			}

			$pixel_iterator->clear();
			return false;
	}

	/**
	 * Returns true if the given column contains at least one pixel that does NOT
	 * match the trim colour (RGB only, no alpha considered).
	 */
	protected function columnHasNonMatchingPixel(
		\Imagick $image,
		int $width,
		int $height,
		int $x,
		int $target_r,
		int $target_g,
		int $target_b
		): bool {
			$pixel_iterator = $image->getPixelRegionIterator($x, 0, 1, $height);

			foreach ($pixel_iterator as $col) {
				foreach ($col as $pixel) {
					$colors = $pixel->getColor();

					if ($colors['r'] !== $target_r
						|| $colors['g'] !== $target_g
						|| $colors['b'] !== $target_b) {
							$pixel_iterator->clear();
							return true;
						}
				}
			}

			$pixel_iterator->clear();
			return false;
	}
}

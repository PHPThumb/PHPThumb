<?php

namespace PHPThumb\Plugins;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPThumb\PHPThumb;
use PHPThumb\PluginInterface;
use RuntimeException;

/**
 * GD Trim Lib Plugin Definition File
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
	 * Converts RGB array to 24-bit integer color value
	 *
	 * @param array<int, int|float> $rgb RGB array [R, G, B]
	 * @return int 24-bit color value
	 */
	protected function rgbToInt(array $rgb): int
	{
		return ((int)$rgb[0] << 16) | ((int)$rgb[1] << 8) | (int)$rgb[2];
	}

	/**
	 * Executes the trim operation
	 */
	public function execute(PHPThumb $phpthumb): PHPThumb
	{
		$current_image    = $phpthumb->getOldImage();
		$current_dimensions = $phpthumb->getCurrentDimensions();

		$border_top    = 0;
		$border_bottom = 0;
		$border_left   = 0;
		$border_right  = 0;

		$target_color = $this->rgbToInt($this->color);
		$width        = $current_dimensions['width'];
		$height       = $current_dimensions['height'];

		// Detect top border
		if (in_array('T', $this->sides, true)) {
			for (; $border_top < $height; $border_top++) {
				for ($x = 0; $x < $width; $x++) {
					$pixel_color = imagecolorat($current_image, $x, $border_top);

					// Handle alpha transparency for comparison
					$alpha = ($pixel_color >> 24) & 0x7F;
					if ($alpha > 0 && $this->color === [255, 255, 255]) {
						continue;
					}

					if (($pixel_color & 0xFFFFFF) !== $target_color) {
						break;
					}
				}

				// Only break if we found a non-matching pixel
				if ($x < $width) {
					break;
				}
			}
		}

		// Detect bottom border
		if (in_array('B', $this->sides, true)) {
			for (; $border_bottom < $height; $border_bottom++) {
				$y = $height - $border_bottom - 1;

				for ($x = 0; $x < $width; $x++) {
					$pixel_color = imagecolorat($current_image, $x, $y);

					$alpha = ($pixel_color >> 24) & 0x7F;
					if ($alpha > 0 && $this->color === [255, 255, 255]) {
						continue;
					}

					if (($pixel_color & 0xFFFFFF) !== $target_color) {
						break;
					}
				}

				if ($x < $width) {
					break;
				}
			}
		}

		// Detect left border
		if (in_array('L', $this->sides, true)) {
			for (; $border_left < $width; $border_left++) {
				for ($y = 0; $y < $height; $y++) {
					$pixel_color = imagecolorat($current_image, $border_left, $y);

					$alpha = ($pixel_color >> 24) & 0x7F;
					if ($alpha > 0 && $this->color === [255, 255, 255]) {
						continue;
					}

					if (($pixel_color & 0xFFFFFF) !== $target_color) {
						break;
					}
				}

				if ($y < $height) {
					break;
				}
			}
		}

		// Detect right border
		if (in_array('R', $this->sides, true)) {
			for (; $border_right < $width; $border_right++) {
				$x = $width - $border_right - 1;

				for ($y = 0; $y < $height; $y++) {
					$pixel_color = imagecolorat($current_image, $x, $y);

					$alpha = ($pixel_color >> 24) & 0x7F;
					if ($alpha > 0 && $this->color === [255, 255, 255]) {
						continue;
					}

					if (($pixel_color & 0xFFFFFF) !== $target_color) {
						break;
					}
				}

				if ($y < $height) {
					break;
				}
			}
		}

		// Calculate new dimensions
		$new_width  = $width - $border_left - $border_right;
		$new_height = $height - $border_top - $border_bottom;

		// Ensure we have something to show
		if ($new_width <= 0 || $new_height <= 0) {
			throw new RuntimeException('Trim operation would result in empty image');
		}

		// Create new trimmed image
		$new_image = imagecreatetruecolor($new_width, $new_height);

		if ($new_image === false) {
			throw new RuntimeException('Failed to create trimmed image');
		}

		// Preserve transparency
		imagealphablending($new_image, false);
		imagesavealpha($new_image, true);

		// Copy the trimmed portion
		imagecopy(
			$new_image,
			$current_image,
			0,
			0,
			$border_left,
			$border_top,
			$new_width,
			$new_height
			);

		// Update PHPThumb
		$phpthumb->setOldImage($new_image);
		$phpthumb->setCurrentDimensions([
			'width'  => $new_width,
			'height' => $new_height,
		]);

		return $phpthumb;
	}
}
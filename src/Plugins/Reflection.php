<?php

namespace PHPThumb\Plugins;

use InvalidArgumentException;
use PHPThumb\PHPThumb;
use PHPThumb\PluginInterface;

/**
 * GD Reflection Lib Plugin Definition File
 *
 * This file contains the plugin definition for the GD Reflection Lib for PHP Thumb
 *
 * PhpThumb : PHP Thumb Library <https://github.com/PHPThumb/PHPThumb>
 * Copyright (c) 2009, Ian Selby
 *
 * Licensed under the MIT License
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @copyright Copyright (c) 2009 Ian Selby
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @version 3.0
 * @package PhpThumb
 * @subpackage Plugins
 */
class Reflection implements PluginInterface
{
	/**
	 * @var int Reflection percentage (0-100)
	 */
	protected int $percent;

	/**
	 * @var int Reflection height percentage (0-100)
	 */
	protected int $reflection;

	/**
	 * @var int White transparency for reflection gradient (0-100)
	 */
	protected int $white;

	/**
	 * @var bool Whether to add a border
	 */
	protected bool $border;

	/**
	 * @var string Border color in hex format
	 */
	protected string $border_color;

	/**
	 * @param int $percent How much of the original image to include in reflection (0-100)
	 * @param int $reflection Height of the reflection as a percentage of the original (0-100)
	 * @param int $white White transparency for the gradient (0-100)
	 * @param bool $border Whether to add a border
	 * @param string $border_color Hex color for the border (e.g., '#FFFFFF')
	 */
	public function __construct(
		int $percent = 50,
		int $reflection = 50,
		int $white = 80,
		bool $border = false,
		string $border_color = '#FFFFFF'
		) {
			$this->percent      = $percent;
			$this->reflection   = $reflection;
			$this->white        = $white;
			$this->border       = $border;
			$this->border_color = $border_color;
	}

	/**
	 * Executes the reflection effect on the image
	 */
	public function execute(PHPThumb $phpthumb): PHPThumb
	{
		$current_dimensions = $phpthumb->getCurrentDimensions();
		$options             = $phpthumb->getOptions();

		$width              = $current_dimensions['width'];
		$height             = $current_dimensions['height'];
		$reflection_height  = intval($height * ($this->reflection / 100));
		$new_height         = $height + $reflection_height;
		$reflected_part     = $height * ($this->percent / 100);

		// Create the reflection image
		$working_image = imagecreatetruecolor($width, $new_height);

		if ($working_image === false) {
			throw new RuntimeException('Failed to create reflection image');
		}

		imagealphablending($working_image, true);

		$color_to_paint = imagecolorallocatealpha(
			$working_image,
			255,
			255,
			255,
			0
			);

		if ($color_to_paint === false) {
			imagedestroy($working_image);
			throw new RuntimeException('Failed to allocate color for reflection');
		}

		imagefilledrectangle(
			$working_image,
			0,
			0,
			$width,
			$new_height,
			$color_to_paint
			);

		// Get the current image
		$current_image = $phpthumb->getOldImage();

		// Copy the portion to be reflected
		imagecopyresampled(
			$working_image,
			$current_image,
			0,
			0,
			0,
			intval($reflected_part),
			$width,
			$reflection_height,
			$width,
			intval($height - $reflected_part)
			);

		// Flip the reflection vertically
		$this->imageFlipVertical($working_image);

		// Copy the original image on top
		imagecopy(
			$working_image,
			$current_image,
			0,
			0,
			0,
			0,
			$width,
			$height
			);

		imagealphablending($working_image, true);

		// Apply gradient fade to reflection
		for ($i = 0; $i < $reflection_height; $i++) {
			$alpha = ($i / $reflection_height) * $this->white;
			$alpha = intval($alpha);

			$color_to_paint = imagecolorallocatealpha(
				$working_image,
				255,
				255,
				255,
				$alpha
				);

			imagefilledrectangle(
				$working_image,
				0,
				$height + $i,
				$width,
				$height + $i,
				$color_to_paint
				);
		}

		// Add border if requested
		if ($this->border) {
			$rgb = $this->hex2rgb($this->border_color, false);
			$border_color = imagecolorallocate(
				$working_image,
				$rgb[0],
				$rgb[1],
				$rgb[2]
				);

			// Top border
			imageline($working_image, 0, 0, $width, 0, $border_color);
			// Bottom border
			imageline($working_image, 0, $height, $width, $height, $border_color);
			// Left border
			imageline($working_image, 0, 0, 0, $height, $border_color);
			// Right border
			imageline($working_image, $width - 1, 0, $width - 1, $height, $border_color);
		}

		// Preserve alpha for PNG images
		if ($phpthumb->getFormat() === 'PNG') {
			$color_transparent = imagecolorallocatealpha(
				$working_image,
				$options['alphaMaskColor'][0],
				$options['alphaMaskColor'][1],
				$options['alphaMaskColor'][2],
				0
				);

			imagefill($working_image, 0, 0, $color_transparent);
			imagesavealpha($working_image, true);
		}

		// Update the PHPThumb instance
		$phpthumb->setOldImage($working_image);
		$phpthumb->setCurrentDimensions([
			'width'  => $width,
			'height' => $new_height,
		]);

		return $phpthumb;
	}

	/**
	 * Flips the image vertically using imageflip (efficient GD function)
	 */
	protected function imageFlipVertical($image): void
	{
		if (function_exists('imageflip')) {
			imageflip($image, IMG_FLIP_VERTICAL);
		} else {
			// Fallback for older GD versions using efficient row copying
			$x_i = imagesx($image);
			$y_i = imagesy($image);

			// Create temp image for flipping
			$tmp = imagecreatetruecolor($x_i, $y_i);

			if ($tmp !== false) {
				for ($y = 0; $y < $y_i; $y++) {
					imagecopy($tmp, $image, 0, $y, 0, $y_i - $y - 1, $x_i, 1);
				}

				// Copy back
				for ($y = 0; $y < $y_i; $y++) {
					imagecopy($image, $tmp, 0, $y_i - $y - 1, 0, $y, $x_i, 1);
				}

				imagedestroy($tmp);
			}
		}
	}

	/**
	 * Converts a hex color to RGB array or string
	 *
	 * @param string $hex Color in hex format (#FFFFFF or FFFFFF)
	 * @param bool $as_string Return as "R G B" string instead of array
	 * @return array|string RGB values
	 */
	protected function hex2rgb(string $hex, bool $as_string = false): array|string
	{
		// Strip leading #
		$hex = ltrim($hex, '#');

		// Handle &H prefix (VB-style)
		if (str_starts_with($hex, '&H')) {
			$hex = substr($hex, 2);
		}

		// Ensure we have 6 characters
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$rgb = [
			hexdec(substr($hex, 0, 2)),
			hexdec(substr($hex, 2, 2)),
			hexdec(substr($hex, 4, 2)),
		];

		return $as_string ? implode(' ', $rgb) : $rgb;
	}
}
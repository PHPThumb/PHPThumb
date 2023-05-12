<?php

namespace PHPThumb\Plugins;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPThumb\PHPThumb;
use PHPThumb\PluginInterface;

	/**
	 * GD Trim Lib Plugin Definition File
	 *
	 * This file contains the plugin definition for the GD Trim Lib for PHP Thumb
	 *
	 * PHP Version 8 with GD 2.3+
	 * PhpThumb : PHP Thumb Library <https://github.com/PHPThumb/PHPThumb>
	 * Copyright (c) 2009, Ian Selby
	 *
	 * Author(s): Ian Selby <ianrselby@gmail.com>
	 *
	 * Licensed under the MIT License
	 * Redistributions of files must retain the above copyright notice.
	 *
	 * @author Oleg Sherbakov <holdmann@yandex.ru>
	 * @copyright Copyright (c) 2016
	 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
	 * @version 1.0
	 * @package PhpThumb
	 * @filesource
	 */

/**
 * GD Trim Lib Plugin
 *
 * This plugin allows you to trim unnecessary single color borders from any side of image
 *
 * @package PhpThumb
 * @subpackage Plugins
 */
class Trim implements PluginInterface
{
	/**
	 * @var array Contains trimmed color in array of RGB parts
	 */
	protected array $color;

	/**
	 * @var array Contains array of sides which will be trim
	 */
	protected array $sides;

	/**
	 * Validate whether RGB color parts array valid or not
	 */
	private function validateColor(array $colors): bool
	{
		if (!(is_array($colors) && count($colors) == 3))
		{
			return false;
		}

		foreach($colors as $color)
		{
			if ($color < 0 || $color > 255)
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Validates whether sides is valid or not
	 */
	private function validateSides(string $sides_string): bool
	{
		$sides = str_split($sides_string);

		if (count($sides) > 4 || count($sides) == 0)
		{
			return false;
		}

		foreach($sides as $side)
		{
			if (!in_array($side, ['T', 'B', 'L', 'R']))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Trim constructor
	 */
	public function __construct(array $color = [255, 255, 255], string $sides = 'TBLR')
	{
		// make sure our arguments are valid
		if (!$this->validateColor($color))
		{
			throw new InvalidArgumentException('Color must be array of RGB color model parts');
		}

		if (!$this->validateSides($sides))
		{
			throw new InvalidArgumentException('Sides must be string with T, B, L, and/or R coordinates');
		}

		$this->color	= $color;
		$this->sides	= str_split($sides);
	}

	/**
	 * Converts rgb parts array to integer representation
	 */
	private function rgb2int(array $rgb): float|int
	{
		return hexdec(
			sprintf('%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2])
		);
	}

	public function execute(PHPThumb $phpthumb): PHPThumb
	{
		$current_image		= $phpthumb->getOldImage();
		$current_dimensions	= $phpthumb->getCurrentDimensions();

		$border_top		= 0;
		$border_bottom	= 0;
		$border_left	= 0;
		$border_right	= 0;

		if (in_array('T', $this->sides))
		{
			for (; $border_top < $current_dimensions['height']; ++$border_top)
			{
				for ($x = 0; $x < $current_dimensions['width']; ++$x)
				{
					if (imagecolorat(
							$current_image,
							$x,
							$border_top
						) != $this->rgb2int($this->color))
					{
						break 2;
					}
				}
			}
		}

		if (in_array('B', $this->sides))
		{
			for (; $border_bottom < $current_dimensions['height']; ++$border_bottom)
			{
				for ($x = 0; $x < $current_dimensions['width']; ++$x)
				{
					if (imagecolorat(
							$current_image,
							$x,
							$current_dimensions['height'] - $border_bottom - 1
						) != $this->rgb2int($this->color))
					{
						break 2;
					}
				}
			}
		}

		if (in_array('L', $this->sides))
		{
			for (; $border_left < $current_dimensions['width']; ++$border_left)
			{
				for ($y = 0; $y < $current_dimensions['height']; ++$y)
				{
					if (imagecolorat(
							$current_image,
							$border_left,
							$y
						) != $this->rgb2int($this->color))
					{
						break 2;
					}
				}
			}
		}

		if (in_array('R', $this->sides))
		{
			for (; $border_right < $current_dimensions['width']; ++$border_right)
			{
				for ($y = 0; $y < $current_dimensions['height']; ++$y)
				{
					if (imagecolorat(
							$current_image,
							$current_dimensions['width'] - $border_right - 1,
							$y
						) != $this->rgb2int($this->color))
					{
						break 2;
					}
				}
			}
		}

		$new_width	= $current_dimensions['width'] - ($border_left + $border_right);
		$new_height	= $current_dimensions['height'] - ($border_top + $border_bottom);

		$new_image = imagecreatetruecolor(
			$new_width,
			$new_height
		);

		imagecopy(
			$new_image,
			$current_image,
			0,
			0,
			$border_left,
			$border_top,
			$current_dimensions['width'],
			$current_dimensions['height']
		);

		$phpthumb->setOldImage($new_image);

		$phpthumb->setCurrentDimensions([
			'width'		=> $new_width,
			'height'	=> $new_height
		]);

		return $phpthumb;
	}
}

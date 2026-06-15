<?php

namespace PHPThumb\Plugins;

use PHPThumb\PHPThumb;
use PHPThumb\GD;
use PHPThumb\PluginInterface;

	/**
	 * GD Watermark Lib Plugin Definition File
	 *
	 * This file contains the plugin definition for the GD Watermark Lib for PHP Thumb
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
 * GD Watermark Lib Plugin
 *
 * This plugin allows you to add watermark above the image
 *
 * @package PhpThumb
 * @subpackage Plugins
 */
class Watermark implements PluginInterface
{
	protected GD $wm;
	protected string $position;
	protected int $opacity;
	protected int $offset_x;
	protected int $offset_y;

	/**
	 * Watermark constructor.
	 *
	 * @param GD $wm Watermark image as \PHPThumb\GD instance
	 * @param string $position Can be: left/west, right/east, center for the x-axis and top/north/upper, bottom/lower/south, center for the y-axis
	 * @param int $opacity Opacity of the watermark in percent, 0 = total transparent, 100 = total opaque
	 * @param int $offset_x Offset on the x-axis. can be negative to set an offset to the left
	 * @param int $offset_y Offset on the y-axis. can be negative to set an offset to the top
	 */
	public function __construct(GD $wm, string $position = 'center', int $opacity = 100, int $offset_x = 0, int $offset_y = 0)
	{
		$this->wm		= $wm;
		$this->position	= $position;
		$this->opacity	= $opacity;
		$this->offset_x	= $offset_x;
		$this->offset_y	= $offset_y;
	}

	/**
	 * @param GD $phpthumb
	 * @return GD
	 */
	public function execute(PHPThumb $phpthumb): PHPThumb
	{
		$current_dimensions		= $phpthumb->getCurrentDimensions();
		$watermark_dimensions	= $this->wm->getCurrentDimensions();

		$watermark_position_x	= $this->offset_x;
		$watermark_position_y	= $this->offset_y;

		if (preg_match('/right|east/i', $this->position))
		{
			$watermark_position_x += $current_dimensions['width'] - $watermark_dimensions['width'];
		}
		else if (!preg_match('/left|west/i', $this->position))
		{
			$watermark_position_x += intval($current_dimensions['width']/2 - $watermark_dimensions['width']/2);
		}

		if (preg_match('/bottom|lower|south/i', $this->position))
		{
			$watermark_position_y += $current_dimensions['height'] - $watermark_dimensions['height'];
		}
		else if (!preg_match('/upper|top|north/i', $this->position))
		{
			$watermark_position_y += intval($current_dimensions['height']/2 - $watermark_dimensions['height']/2);
		}

		$working_image		= $phpthumb->getWorkingImage();
		$watermark_image	= ($this->wm->getWorkingImage() ?: $this->wm->getOldImage());

		$this->imageCopyMergeAlpha(
			$working_image,
			$watermark_image,
			$watermark_position_x,
			$watermark_position_y,
			0,
			0,
			$watermark_dimensions['width'],
			$watermark_dimensions['height'],
			$this->opacity
		);

		$phpthumb->setWorkingImage($working_image);

		return $phpthumb;
	}

	/**
	 * Function copied from: http://www.php.net/manual/en/function.imagecopymerge.php#92787
	 * Does the same as "imagecopymerge" but preserves the alpha-channel
	 */
	private function imageCopyMergeAlpha(&$dst_im, &$src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct): void
	{
		$cut = imagecreatetruecolor($src_w, $src_h);
		imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
		imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
		imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct);
	}
}

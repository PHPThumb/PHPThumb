<?php

namespace PHPThumb\Plugins;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPThumb\Imagick;
use PHPThumb\PHPThumb;
use PHPThumb\PluginInterface;

/**
 * Watermark Lib Plugin Definition File
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
class Watermark implements PluginInterface
{
	/**
	 * @var GD|Imagick The watermark image instance
	 */
	protected GD|Imagick $wm;

	/**
	 * @var string Position for the watermark
	 */
	protected string $position;

	/**
	 * @var int Opacity of the watermark (0-100)
	 */
	protected int $opacity;

	/**
	 * @var int X-axis offset
	 */
	protected int $offset_x;

	/**
	 * @var int Y-axis offset
	 */
	protected int $offset_y;

	/**
	 * Watermark constructor
	 *
	 * @param GD|Imagick $wm Watermark image as \PHPThumb\GD or \PHPThumb\Imagick instance
	 * @param string $position Position: combinations of left/west/right/east for X
	 *                         and top/north/upper/bottom/south/lower for Y
	 * @param int $opacity Opacity of the watermark in percent (0 = transparent, 100 = opaque)
	 * @param int $offset_x Horizontal offset (can be negative)
	 * @param int $offset_y Vertical offset (can be negative)
	 * @throws InvalidArgumentException If watermark is not GD or Imagick instance
	 */
	public function __construct(
		GD|Imagick $wm,
		string $position = 'center',
		int $opacity = 100,
		int $offset_x = 0,
		int $offset_y = 0
		) {
			if (!$wm instanceof GD && !$wm instanceof Imagick) {
				throw new InvalidArgumentException(
					'Watermark must be an instance of \PHPThumb\GD or \PHPThumb\Imagick'
					);
			}

			$this->wm        = $wm;
			$this->position = $position;
			$this->opacity  = max(0, min(100, $opacity));
			$this->offset_x = $offset_x;
			$this->offset_y = $offset_y;
	}

	/**
	 * Executes the watermark operation
	 */
	public function execute(PHPThumb $phpthumb): PHPThumb
	{
		if ($phpthumb instanceof GD) {
			return $this->executeGD($phpthumb);
		}

		if ($phpthumb instanceof Imagick) {
			return $this->executeImagick($phpthumb);
		}

		throw new InvalidArgumentException('Unsupported PHPThumb instance type');
	}

	/**
	 * Execute watermark for GD-based PHPThumb
	 */
	protected function executeGD(GD $phpthumb): PHPThumb
	{
		$current_dimensions    = $phpthumb->getCurrentDimensions();
		$watermark_dimensions = $this->wm->getCurrentDimensions();

		[$watermark_position_x, $watermark_position_y] = $this->calculatePosition(
			$current_dimensions,
			$watermark_dimensions
			);

		$base_image       = $phpthumb->getOldImage();
		$watermark_image  = $this->wm->getOldImage();

		if ($base_image === null) {
			throw new \RuntimeException('Base image is not initialized');
		}

		if ($watermark_image === null) {
			throw new \RuntimeException('Watermark image is not initialized');
		}

		// Create a fresh canvas so we don't mutate the original
		$output_image = imagecreatetruecolor(
			$current_dimensions['width'],
			$current_dimensions['height']
			);

		if ($output_image === false) {
			throw new \RuntimeException('Failed to create canvas for watermarking');
		}

		// Preserve alpha for PNG
		if ($phpthumb->getFormat() === 'PNG') {
			imagealphablending($output_image, false);
			imagesavealpha($output_image, true);
		}

		// Copy the base image onto the new canvas
		imagecopy(
			$output_image,
			$base_image,
			0, 0, 0, 0,
			$current_dimensions['width'],
			$current_dimensions['height']
			);

		// Apply the watermark
		if ($this->opacity < 100) {
			$this->imageCopyMergeAlpha(
				$output_image,
				$watermark_image,
				$watermark_position_x,
				$watermark_position_y,
				0,
				0,
				$watermark_dimensions['width'],
				$watermark_dimensions['height'],
				$this->opacity
				);
		} else {
			imagecopy(
				$output_image,
				$watermark_image,
				$watermark_position_x,
				$watermark_position_y,
				0,
				0,
				$watermark_dimensions['width'],
				$watermark_dimensions['height']
				);
		}

		// Replace old_image - this is what show()/save() output
		$phpthumb->setOldImage($output_image);

		return $phpthumb;
	}

	/**
	 * Execute watermark for Imagick-based PHPThumb
	 */
	protected function executeImagick(Imagick $phpthumb): PHPThumb
	{
		$current_dimensions    = $phpthumb->getCurrentDimensions();
		$watermark_dimensions = $this->wm->getCurrentDimensions();

		[$watermark_position_x, $watermark_position_y] = $this->calculatePosition(
			$current_dimensions,
			$watermark_dimensions
			);

		$base_image = $phpthumb->getOldImage();
		$watermark  = clone $this->wm->getOldImage();

		if ($base_image === null || $watermark === null) {
			throw new \RuntimeException('Image is not initialized');
		}

		// Apply opacity to watermark.
		//
		// Imagick::setImageOpacity() was removed in PECL Imagick 3.8.0.
		// Use evaluateImage() with EVALUATE_MULTIPLY on the alpha channel
		// to scale the existing alpha by $this->opacity / 100.
		//
		// Note: only apply this if the watermark actually has alpha — otherwise
		// the result is meaningless (and on some Imagick builds will throw
		// "unable to set image alpha channel").
		if ($this->opacity < 100) {
			if ($watermark->getImageAlphaChannel()) {
				$multiplier = $this->opacity / 100;
				$watermark->evaluateImage(
					\Imagick::EVALUATE_MULTIPLY,
					$multiplier,
					\Imagick::CHANNEL_ALPHA
					);
			}
		}

		// Composite watermark onto base image
		$base_image->compositeImage(
			$watermark,
			\Imagick::COMPOSITE_DEFAULT,
			$watermark_position_x,
			$watermark_position_y
			);

		// Clean up the cloned watermark
		$watermark->clear();
		$watermark->destroy();

		return $phpthumb;
	}

	/**
	 * Calculate watermark position based on current dimensions and position string
	 *
	 * @param array<string, int> $current_dimensions Current image dimensions
	 * @param array<string, int> $watermark_dimensions Watermark dimensions
	 * @return array<int> [x, y] position coordinates
	 */
	protected function calculatePosition(array $current_dimensions, array $watermark_dimensions): array
	{
		$watermark_position_x = $this->offset_x;
		$watermark_position_y = $this->offset_y;

		// Horizontal position
		if (preg_match('/\b(right|east)\b/i', $this->position)) {
			$watermark_position_x += $current_dimensions['width'] - $watermark_dimensions['width'];
		} elseif (!preg_match('/\b(left|west)\b/i', $this->position)) {
			$watermark_position_x += intval(
				($current_dimensions['width'] - $watermark_dimensions['width']) / 2
				);
		}

		// Vertical position
		if (preg_match('/\b(bottom|lower|south)\b/i', $this->position)) {
			$watermark_position_y += $current_dimensions['height'] - $watermark_dimensions['height'];
		} elseif (!preg_match('/\b(upper|top|north)\b/i', $this->position)) {
			$watermark_position_y += intval(
				($current_dimensions['height'] - $watermark_dimensions['height']) / 2
				);
		}

		return [$watermark_position_x, $watermark_position_y];
	}

	/**
	 * Copy image with alpha blending (for GD)
	 *
	 * Based on: http://www.php.net/manual/en/function.imagecopymerge.php#92787
	 */
	protected function imageCopyMergeAlpha(
		$dst_im,
		$src_im,
		int $dst_x,
		int $dst_y,
		int $src_x,
		int $src_y,
		int $src_w,
		int $src_h,
		int $pct
		): void {
			$cut = imagecreatetruecolor($src_w, $src_h);

			if ($cut === false) {
				return;
			}

			imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
			imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
			imagecopymerge($dst_im, $cut, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct);
	}
}
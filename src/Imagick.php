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
		$degrees = match($direction) {
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
			'AVIF'  => $this->options['avifQuality'],
			'JPEG', 'JPG' => $this->options['jpegQuality'],
			'WEBP'  => $this->options['webpQuality'],
			default => null,
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
				'interlace'             => null
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

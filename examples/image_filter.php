<?php
/**
 * PhpThumb Library Example File
 *
 * This file contains example usage for the PHP Thumb Library
 *
 * PHP Version 7 with GD 2.2+
 * PhpThumb : PHP Thumb Library <https://github.com/PHPThumb/PHPThumb>
 * Copyright (c) 2014, Marcel Domke
 *
 * Author(s): Marcel Domke <contact@marcel-domke.de>
 *
 * Licensed under the MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author Marcel Domke <marcel-domke.de>
 * @copyright Copyright (c) 2014 Marcel Domke
 * @link https://github.com/PHPThumb/PHPThumb
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @version 3.0
 * @package PhpThumb
 * @subpackage Examples
 * @filesource
 */

require_once '../vendor/autoload.php';

$thumb = new PHPThumb\GD(__DIR__ .'/../tests/resources/test.jpg');
// for more informations visit http://www.php.net/manual/de/function.imagefilter.php
$thumb->imageFilter(IMG_FILTER_COLORIZE, 160, 20, 20);
// or e.g. $thumb->imageFilter(IMG_FILTER_GRAYSCALE);

$thumb->show();

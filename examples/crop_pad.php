<?php
/**
 * PhpThumb Library Example File
 *
 * This file contains example usage for the PHP Thumb Library
 *
 * PHP Version 8 with GD 2.3+
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
$thumb->pad(1024, 350, [192, 212, 45]);

$thumb->show();

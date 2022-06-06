<?php
/**
 * PhpThumb Library Example File
 *
 * This file contains example usage for the PHP Thumb Library
 *
 * PHP Version 7 with GD 2.2+
 * PhpThumb : PHP Thumb Library <https://github.com/PHPThumb/PHPThumb>
 * Copyright (c) 2009, Ian Selby/Gen X Design
 *
 * Author(s): Ian Selby <ianrselby@gmail.com>
 *
 * Licensed under the MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @author Ian Selby <ianrselby@gmail.com>
 * @copyright Copyright (c) 2009 Gen X Design
 * @link https://github.com/PHPThumb/PHPThumb
 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
 * @version 3.0
 * @package PhpThumb
 * @subpackage Examples
 * @filesource
 */


$fileData = file_get_contents('test.jpg');

$thumb = new PHPThumb\GD($fileData);
$thumb->crop(100, 100, 300, 200);

// $imageAsString will contain the image data suitable for saving in a database.
$imageAsString = $thumb->getImageAsString();

?>
<h2>Here's the Image Data:</h2>
<strong>Note:</strong> This should be a bunch of gibberish<br />
<div style="overflow: auto; width: 500px; height: 400px; border: 1px solid #e4e4e4; padding: 5px;"><?php echo htmlentities($imageAsString); ?></div>

<h2>Here's that data as an image:</h2>
<img src="data:image/png;base64,<?php echo base64_encode($imageAsString); ?>" />

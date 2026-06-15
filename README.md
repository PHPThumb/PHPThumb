# PHP Thumb

> **A lightweight, chainable image manipulation library for PHP**

PHP Thumb is a simple, modern image manipulation library aimed primarily at thumbnail generation. It provides a clean, fluent interface for common image operations built on top of the **GD** extension.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Supported Formats](#supported-formats)
- [Core API](#core-api)
  - [Resize Operations](#resize-operations)
  - [Crop Operations](#crop-operations)
  - [Rotate & Filter](#rotate--filter)
  - [Pad](#pad)
  - [Output](#output)
- [Plugins](#plugins)
- [Configuration Options](#configuration-options)
- [Examples](#examples)
- [Documentation](#documentation)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## Features

- **GD-based** — Built on PHP's widely-available GD extension
- **Fluent chaining API** — Perform multiple manipulations on a single instance
- **Flexible resizing** — By width, height, percentage, or adaptive (with crop)
- **Crop operations** — From-center, quadrant-based, percentage-based, or vanilla x/y cropping
- **Rotation** — 90° clockwise/counter-clockwise or arbitrary degrees
- **Image filters** — Grayscale, negate, brightness, blur, emboss, and more
- **Plugin system** — Extend the library with custom manipulations (Reflection, Trim, Watermark included)
- **Modern PHP 8.2+** — Strict types, enums where appropriate, modern syntax
- **Composer-ready** — PSR-4 autoloading and Composer integration out of the box

---

## Requirements

- **PHP 8.2** or higher
- **GD** extension (>= 2.3.2)
- **Composer** (recommended for installation)

> **Note:** Imagick support was explored but is not currently included. The library is GD-only at this time.

---

## Installation

Install via Composer:

```bash
composer require phpthumb/phpthumb
```

Then autoload in your project:

```php
require_once 'vendor/autoload.php';
```

### Manual Installation

If you prefer not to use Composer, clone the repository and include the files manually:

```bash
git clone https://github.com/PHPThumb/PHPThumb.git
```

Then include the autoloader or files manually — but **Composer is strongly recommended**.

---

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use PHPThumb\GD;

$thumb = new GD('path/to/image.jpg');
$thumb->resize(300, 300)
      ->cropFromCenter(200, 200);
$thumb->show();              // Output to browser
// $thumb->save('out.jpg');   // Or save to file
// $thumb->getImageAsString(); // Or get raw binary as a string
```

---

## Supported Formats

| Format | Supported |
|--------|:---------:|
| JPEG   | ✅ |
| PNG    | ✅ |
| GIF    | ✅ |
| WEBP   | ✅ |
| AVIF   | ✅ |

Format support is detected automatically; an exception is thrown if your environment can't handle the loaded file.

> **Note:** Loading from binary strings is supported by passing the raw image data as the filename parameter (GD only). Loading directly from URLs is **not** currently supported.

---

## Core API

### Resize Operations

#### `resize(int $max_width = 0, int $max_height = 0): self`
Resizes the image so it fits within the given box, preserving aspect ratio. Passing `0` for a dimension leaves it unconstrained.

```php
$thumb->resize(800, 600);
$thumb->resize(800, 0);   // Width only
$thumb->resize(0, 600);   // Height only
```

#### `resizePercent(int $percent): self`
Uniformly scales the image by a percentage (1–100).

```php
$thumb->resizePercent(50); // Half size
```

#### `adaptiveResize(int $width, int $height): self`
Resize so the image **covers** the target box, then crop the overflow from the center. Ideal for thumbnails with strict dimensions.

```php
$thumb->adaptiveResize(400, 400);
```

#### `adaptiveResizeQuadrant(int $width, int $height, string $quadrant = 'C'): self`
Like `adaptiveResize`, but crops from a specific quadrant:
- `'T'` Top, `'B'` Bottom, `'L'` Left, `'R'` Right, `'C'` Center (default)

```php
$thumb->adaptiveResizeQuadrant(400, 400, 'R');
```

#### `adaptiveResizePercent(int $width, int $height, int $percent = 50): self`
Like `adaptiveResize`, but controls where the crop occurs on a 1–100 scale (1 = top/left, 100 = bottom/right).

```php
$thumb->adaptiveResizePercent(400, 400, 25);
```

### Crop Operations

#### `crop(int $start_x, int $start_y, int $crop_width, int $crop_height): self`
Vanilla crop from coordinates.

```php
$thumb->crop(50, 50, 300, 300);
```

#### `cropFromCenter(int $crop_width, ?int $crop_height = 0): self`
Crop from the image center. If `$crop_height` is omitted, produces a square crop.

```php
$thumb->cropFromCenter(200);      // 200x200 square
$thumb->cropFromCenter(300, 200); // 300x200 from center
```

### Rotate & Filter

#### `rotateImage(string $direction = 'CW'): self`
Rotates 90° either clockwise (`'CW'`) or counter-clockwise (`'CCW'`).

```php
$thumb->rotateImage('CW');
```

#### `rotateImageNDegrees(int $degrees): self`
Arbitrary rotation by N degrees.

```php
$thumb->rotateImageNDegrees(45);
```

#### `imageFilter(int $filter, ...args): self`
Applies a GD-style filter. Supported filters include:
`IMG_FILTER_GRAYSCALE`, `IMG_FILTER_NEGATE`, `IMG_FILTER_BRIGHTNESS`, `IMG_FILTER_CONTRAST`, `IMG_FILTER_COLORIZE`, `IMG_FILTER_EDGEDETECT`, `IMG_FILTER_EMBOSS`, `IMG_FILTER_GAUSSIAN_BLUR`, `IMG_FILTER_SELECTIVE_BLUR`, `IMG_FILTER_MEAN_REMOVAL`, `IMG_FILTER_SMOOTH`, `IMG_FILTER_PIXELATE`, etc.

```php
$thumb->imageFilter(IMG_FILTER_GRAYSCALE);
$thumb->imageFilter(IMG_FILTER_COLORIZE, 100, 0, 0); // Red tint
$thumb->imageFilter(IMG_FILTER_BRIGHTNESS, 50);
```

### Pad

#### `pad(int $width, int $height, array $color = [255, 255, 255]): self`
Centers the image on a canvas of the given size, filling the rest with `$color`.

```php
$thumb->pad(500, 500, [255, 255, 255]); // White background
$thumb->pad(800, 600, [0, 0, 0]);       // Black background
```

### Output

#### `show(bool $raw_data = false): self`
Outputs the image directly to the browser (sets the appropriate `Content-Type` header). Pass `true` for `$raw_data` to skip the header.

#### `save(string $file_name, ?string $format = null): self`
Writes the image to disk. Format is auto-detected from the current image, or you can force a format:

```php
$thumb->save('output.jpg');         // Keep original format
$thumb->save('output.png', 'PNG');  // Force conversion to PNG
```

Valid `$format` values: `AVIF`, `GIF`, `JPEG`, `JPG`, `PNG`, `WEBP`.

#### `getImageAsString(): string`
Returns the raw image binary as a string — perfect for storage in databases or inline `<img>` data URIs.

```php
$dataUri = 'data:image/jpeg;base64,' . base64_encode($thumb->getImageAsString());
```

---

## Plugins

Plugins extend PHPThumb with custom operations. They implement `PHPThumb\PluginInterface` and receive the active `PHPThumb` instance, which they can manipulate via public getters/setters.

Plugins are passed as the third constructor argument and are executed automatically before `show()` or `save()`.

### Built-in Plugins

#### `PHPThumb\Plugins\Watermark`
Adds an image watermark with full positioning and opacity control.

```php
$wm = new PHPThumb\GD('watermark.png');

$thumb = new PHPThumb\GD('photo.jpg', [], [
    new PHPThumb\Plugins\Watermark(
        $wm,
        position: 'bottom right',
        opacity: 75,
        offset_x: 10,
        offset_y: 10
    )
]);

$thumb->resize(800, 0)->show();
```

**Position keywords** — combine horizontal + vertical:
- Horizontal: `left`/`west`, `right`/`east`, `center`
- Vertical: `top`/`north`/`upper`, `bottom`/`south`/`lower`, `center`

#### `PHPThumb\Plugins\Trim`
Auto-trims borders of a specific color (great for scanned documents or images on a uniform background).

```php
$thumb = new PHPThumb\GD('scan.jpg', [], [
    new PHPThumb\Plugins\Trim(
        color: [255, 255, 255], // RGB white
        sides: 'TBLR'           // Top, Bottom, Left, Right
    )
]);

$thumb->show();
```

#### `PHPThumb\Plugins\Reflection`
Generates a classic glossy reflection effect.

```php
$thumb = new PHPThumb\GD('logo.png', [], [
    new PHPThumb\Plugins\Reflection(
        percent: 50,        // % of source to reflect
        reflection: 50,     // Reflection height as % of source
        white: 80,          // Gradient fade intensity
        border: true,       // Draw a separator border
        border_color: '#FFFFFF'
    )
]);

$thumb->show();
```

### Writing Custom Plugins

Implement `PHPThumb\PluginInterface`:

```php
namespace App\Thumb\Plugins;

use PHPThumb\PluginInterface;
use PHPThumb\PHPThumb;

class Sepia implements PluginInterface
{
    public function execute(PHPThumb $phpthumb): PHPThumb
    {
        return $phpthumb->imageFilter(IMG_FILTER_GRAYSCALE)
                        ->imageFilter(IMG_FILTER_COLORIZE, 90, 60, 40);
    }
}
```

Then register it:

```php
$thumb = new PHPThumb\GD('photo.jpg', [], [new App\Thumb\Plugins\Sepia()]);
```

---

## Configuration Options

Options are passed as the second constructor argument:

```php
$thumb = new PHPThumb\GD('image.jpg', [
    'jpegQuality' => 85,
    'resizeUp'    => true,
]);
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `resizeUp` | `bool` | `false` | Allow upscaling images that are smaller than the target size |
| `jpegQuality` | `int` | `100` | JPEG output quality (0–100) |
| `webpQuality` | `int` | `100` | WebP output quality (0–100) |
| `avifQuality` | `int` | `100` | AVIF output quality (0–100) |
| `correctPermissions` | `bool` | `false` | Attempt to `chmod` the target directory if not writable |
| `preserveAlpha` | `bool` | `true` | Preserve PNG alpha channel |
| `alphaMaskColor` | `array` | `[255, 255, 255]` | RGB color used for PNG alpha mask |
| `preserveTransparency` | `bool` | `true` | Preserve GIF / WebP transparency |
| `transparencyMaskColor` | `array` | `[0, 0, 0]` | RGB color used for GIF transparency mask |
| `interlace` | `bool\|null` | `null` | Enable/disable interlacing (progressive rendering). `null` = leave default |

---

## Examples

The `examples/` directory contains runnable demos for every feature. A quick tour:

```php
// Basic resize
$thumb = new PHPThumb\GD('photo.jpg');
$thumb->resize(400, 300)->save('photo-small.jpg');

// Percentage resize
(new PHPThumb\GD('photo.jpg'))->resizePercent(25)->save('photo-thumb.jpg');

// Adaptive square thumbnail (cover + center crop)
(new PHPThumb\GD('photo.jpg'))->adaptiveResize(200, 200)->show();

// Square crop from center
(new PHPThumb\GD('photo.jpg'))->cropFromCenter(500)->save('square.jpg');

// Rotate 90° clockwise
(new PHPThumb\GD('photo.jpg'))->rotateImage('CW')->save('rotated.jpg');

// Grayscale + slight blur
(new PHPThumb\GD('photo.jpg'))
    ->imageFilter(IMG_FILTER_GRAYSCALE)
    ->imageFilter(IMG_FILTER_GAUSSIAN_BLUR)
    ->save('muted.jpg');

// Pad onto a colored canvas
(new PHPThumb\GD('photo.jpg'))->pad(1200, 800, [0, 0, 0])->show();

// Format conversion
(new PHPThumb\GD('photo.jpg'))->save('photo.webp', 'WEBP');

// Chain everything
(new PHPThumb\GD('photo.jpg'))
    ->resize(0, 800)
    ->cropFromCenter(800, 800)
    ->imageFilter(IMG_FILTER_CONTRAST, -10)
    ->save('final.jpg', 'JPEG');
```

Run any example directly:

```bash
php examples/resize_basic.php
```

---

## Documentation

Full documentation — including detailed guides, tutorials, and plugin recipes — is available on the project wiki:

📚 **[https://github.com/PHPThumb/PHPThumb/wiki](https://github.com/PHPThumb/PHPThumb/wiki)**

> **Note:** The wiki is currently being updated for this version of PHPThumb. Some pages may still reference the older API. Refer to this README and the runnable examples in `examples/` for the most accurate information in the meantime.

💬 **[Discussions](https://github.com/PHPThumb/PHPThumb/discussions)** — Got questions, comments, or feedback? This is the place to visit.

---

## Testing

PHPThumb ships with a PHPUnit test suite under `tests/`.

```bash
composer install
vendor/bin/phpunit
```

Test fixtures live in `tests/resources/`.

---

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Add tests for any new behavior
4. Ensure the test suite passes (`vendor/bin/phpunit`)
5. Submit a pull request

For bugs and feature requests, please use the [issue tracker](https://github.com/PHPThumb/PHPThumb/issues) — and join the conversation in [Discussions](https://github.com/PHPThumb/PHPThumb/discussions).

---

## License

PHP Thumb is released under the **MIT License**. See [LICENSE](LICENSE) for details.

---

## Credits

- Original author: **Ian Selby**
- Contributors listed on [GitHub](https://github.com/PHPThumb/PHPThumb/graphs/contributors)

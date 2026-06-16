# PHP Thumb

> **A lightweight, chainable image manipulation library for PHP**

PHP Thumb is a simple, modern image manipulation library aimed primarily at thumbnail generation. It provides a clean, fluent interface for common image operations, with **two interchangeable backends**: the ubiquitous **GD** extension and the more feature-rich **Imagick** extension.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Choosing a Backend: GD vs Imagick](#choosing-a-backend-gd-vs-imagick)
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

- **Two interchangeable backends** — Use `PHPThumb\GD` (built on PHP's GD extension) or `PHPThumb\Imagick` (built on the PECL Imagick extension). Same API, switch with one `use` statement.
- **Fluent chaining API** — Perform multiple manipulations on a single instance
- **Flexible resizing** — By width, height, percentage, or adaptive (with crop)
- **Crop operations** — From-center, quadrant-based, percentage-based, or vanilla x/y cropping
- **Rotation** — 90° clockwise/counter-clockwise or arbitrary degrees
- **Image filters** — Grayscale, negate, brightness, blur, emboss, and more
- **Plugin system** — Extend the library with custom manipulations (Reflection, Trim, Watermark included). Plugins transparently dispatch to the correct backend.
- **Modern PHP 8.2+** — Strict types, enums where appropriate, modern syntax
- **Composer-ready** — PSR-4 autoloading and Composer integration out of the box

---

## Requirements

- **PHP 8.2** or higher
- At least one of:
  - **GD** extension (>= 2.3.2)
  - **Imagick** extension (>= 3.7.0; required for BMP, HEIC, TIFF, and other Imagick-only formats)
- **Composer** (recommended for installation)

The library auto-detects format support at runtime — an exception is thrown if your environment cannot handle the loaded file.

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

// Pick your backend — same API:
use PHPThumb\GD;
// use PHPThumb\Imagick; // ← uncomment to switch

$thumb = new GD('path/to/image.jpg');
$thumb->resize(300, 300)
      ->cropFromCenter(200, 200);
$thumb->show();              // Output to browser
// $thumb->save('out.jpg');   // Or save to file
// $thumb->getImageAsString(); // Or get raw binary as a string
```

To use Imagick instead, change only the `use` statement and the constructor call:

```php
use PHPThumb\Imagick;

$thumb = new Imagick('path/to/image.jpg');
$thumb->resize(300, 300)
      ->cropFromCenter(200, 200);
$thumb->show();
```

The fluent API is identical across both backends.

---

## Choosing a Backend: GD vs Imagick

Both backends implement the same `PHPThumb\PHPThumb` abstract API. They differ in format support, performance characteristics, and image quality on edge cases.

| Concern | `PHPThumb\GD` | `PHPThumb\Imagick` |
|---------|---------------|--------------------|
| Default availability | Bundled with PHP since PHP 8 | Requires PECL extension |
| Formats | JPEG, PNG, GIF, WebP, AVIF | JPEG, PNG, GIF, WebP, AVIF, **BMP, HEIC, TIFF** |
| Memory usage | Lower | Higher (full pixel buffer) |
| Resize quality | Good (bicubic in modern GD) | Excellent (Lanczos / Catmull on Imagick 7) |
| Rotation | Bicubic approximation | Pixel-accurate |
| Plugin output | Single-pass GD ops | Compositing with full alpha support |
| Constructor return on plugin dispatch | `PHPThumb\GD` | `PHPThumb\Imagick` |

**Recommendation**: use GD for typical web thumbnails (smaller memory footprint, no extension dependency). Use Imagick when you need BMP/HEIC/TIFF support, higher-quality resampling, or pixel-accurate rotation.

You can mix backends within the same project — for example, use GD for JPEG thumbnails and Imagick for HEIC processing.

---

## Supported Formats

| Format | GD | Imagick |
|--------|:--:|:-------:|
| JPEG   | ✅ | ✅ |
| PNG    | ✅ | ✅ |
| GIF    | ✅ | ✅ |
| WEBP   | ✅ | ✅ |
| AVIF   | ✅ | ✅ |
| BMP    | ❌ | ✅ |
| HEIC   | ❌ | ✅ |
| TIFF   | ❌ | ✅ |

Format support is detected automatically; an exception is thrown if your environment can't handle the loaded file.

> Loading directly from URLs is supported by both backends.

---

## Core API

> All methods return the backend-specific class (`PHPThumb\GD` or `PHPThumb\Imagick`) for chaining. Documented as `self` below for brevity.

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
Applies a filter. The argument list matches the underlying extension:

- **GD**: any `IMG_FILTER_*` constant (`IMG_FILTER_GRAYSCALE`, `IMG_FILTER_NEGATE`, `IMG_FILTER_BRIGHTNESS`, `IMG_FILTER_CONTRAST`, `IMG_FILTER_COLORIZE`, `IMG_FILTER_EDGEDETECT`, `IMG_FILTER_EMBOSS`, `IMG_FILTER_GAUSSIAN_BLUR`, `IMG_FILTER_SELECTIVE_BLUR`, `IMG_FILTER_MEAN_REMOVAL`, `IMG_FILTER_SMOOTH`, `IMG_FILTER_PIXELATE`, etc.).
- **Imagick**: any `Imagick::FILTER_*` constant (`Imagick::FILTER_GRAYSCALE`, `Imagick::FILTER_NEGATE`, etc.).

```php
// GD
$thumb->imageFilter(IMG_FILTER_GRAYSCALE);
$thumb->imageFilter(IMG_FILTER_COLORIZE, 100, 0, 0); // Red tint
$thumb->imageFilter(IMG_FILTER_BRIGHTNESS, 50);

// Imagick
$thumb->imageFilter(\Imagick::FILTER_GRAYSCALE);
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

Valid `$format` values: `AVIF`, `GIF`, `JPEG`, `JPG`, `PNG`, `WEBP` (BMP/HEIC/TIFF on Imagick via `save($path, 'BMP')` etc.).

#### `getImageAsString(): string`
Returns the raw image binary as a string — perfect for storage in databases or inline `<img>` data URIs.

```php
$dataUri = 'data:image/jpeg;base64,' . base64_encode($thumb->getImageAsString());
```

---

## Plugins

Plugins extend PHPThumb with custom operations. They implement `PHPThumb\PluginInterface` and receive the active `PHPThumb` instance, which they can manipulate via public getters/setters.

Plugins are passed as the third constructor argument and are executed automatically before `show()` or `save()`. All built-in plugins transparently dispatch to the correct backend — you can pass `PHPThumb\GD` or `PHPThumb\Imagick` instances interchangeably, and the plugin will call the right code path.

### Built-in Plugins

#### `PHPThumb\Plugins\Watermark`
Adds an image watermark with full positioning and opacity control.

```php
// GD watermark on GD image
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

```php
// Imagick watermark on Imagick image
$wm = new PHPThumb\Imagick('watermark.png');

$thumb = new PHPThumb\Imagick('photo.jpg', [], [
    new PHPThumb\Plugins\Watermark(
        $wm,
        position: 'center',
        opacity: 50
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

```php
// Imagick version works identically
$thumb = new PHPThumb\Imagick('scan.tiff', [], [
    new PHPThumb\Plugins\Trim(color: [0, 0, 0], sides: 'TB')
]);

$thumb->save('scan-trimmed.png', 'PNG');
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

The Imagick plugin uses a single multi-pass composite that approximates the GD per-row gradient. Visual fidelity is slightly different from GD (constant-fade vs. linear gradient) but the overall effect is similar.

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
        // Branch on the backend type if your plugin needs extension-specific code.
        if ($phpthumb instanceof \PHPThumb\Imagick) {
            $phpthumb->getOldImage()->sepiaToneImage(80);
            return $phpthumb;
        }

        // GD fallback
        return $phpthumb->imageFilter(IMG_FILTER_GRAYSCALE)
                        ->imageFilter(IMG_FILTER_COLORIZE, 90, 60, 40);
    }
}
```

Then register it:

```php
$thumb = new PHPThumb\GD('photo.jpg', [], [new App\Thumb\Plugins\Sepia()]);
// or
$thumb = new PHPThumb\Imagick('photo.jpg', [], [new App\Thumb\Plugins\Sepia()]);
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

| Option | Type | Default | Backend | Description |
|--------|------|---------|---------|-------------|
| `resizeUp` | `bool` | `false` | GD & Imagick | Allow upscaling images that are smaller than the target size |
| `jpegQuality` | `int` | `100` | GD & Imagick | JPEG output quality (0–100) |
| `webpQuality` | `int` | `100` | GD & Imagick | WebP output quality (0–100) |
| `avifQuality` | `int` | `100` | GD & Imagick | AVIF output quality (0–100) |
| `correctPermissions` | `bool` | `false` | GD & Imagick | Attempt to `chmod` the target directory if not writable |
| `preserveAlpha` | `bool` | `true` | GD & Imagick | Preserve PNG alpha channel |
| `alphaMaskColor` | `array` | `[255, 255, 255]` | GD & Imagick | RGB color used for PNG alpha mask |
| `preserveTransparency` | `bool` | `true` | GD & Imagick | Preserve GIF / WebP transparency |
| `transparencyMaskColor` | `array` | `[0, 0, 0]` | GD & Imagick | RGB color used for GIF transparency mask |
| `interlace` | `bool\|null` | `null` | GD & Imagick | Enable/disable interlacing (progressive rendering). `null` = leave default |

---

## Examples

The `examples/` directory contains runnable demos for every feature. Every example is implemented with **both backends** — switch the `use` statement at the top of any example to swap between them.

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

// Grayscale + slight blur (GD)
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

The same examples with Imagick:

```php
// Imagick: load a HEIC photo, convert to JPEG
$thumb = new PHPThumb\Imagick('photo.heic');
$thumb->resize(800, 0)->save('photo.jpg', 'JPEG');

// Imagick: pixel-accurate 45° rotation
(new PHPThumb\Imagick('photo.jpg'))
    ->rotateImageNDegrees(45)
    ->pad(1000, 1000, [255, 255, 255])
    ->save('rotated-padded.png', 'PNG');

// Imagick: grayscale via Imagick's own filter constant
(new PHPThumb\Imagick('photo.jpg'))
    ->imageFilter(\Imagick::FILTER_GRAYSCALE)
    ->save('grayscale.jpg');

// Imagick: composite a watermark with 50% opacity
$wm = (new PHPThumb\Imagick('logo.png'))->resizePercent(20);

(new PHPThumb\Imagick('photo.jpg', [], [
    new PHPThumb\Plugins\Watermark($wm, 'bottom right', 50, 10, 10)
]))->resize(800, 0)->show();
```

Run any example directly:

```bash
php examples/resize_basic.php
```

To run the Imagick variant, edit the `use` line at the top of the example (or pass `--imagick` if the example script supports a flag — see individual example files).

---

## Documentation

Full documentation — including detailed guides, tutorials, and plugin recipes — is available on the project wiki:

📚 **[https://github.com/PHPThumb/PHPThumb/wiki](https://github.com/PHPThumb/PHPThumb/wiki)**

💬 **[Discussions](https://github.com/PHPThumb/PHPThumb/discussions)** — Got questions, comments, or feedback? This is the place to visit.

---

## Testing

PHPThumb ships with a PHPUnit test suite under `tests/`. Tests are split by backend and feature area.

```bash
composer install
vendor/bin/phpunit
```

Test fixtures live in `tests/resources/`.

### Backend-specific test selection

Run only the GD tests:

```bash
vendor/bin/phpunit tests/PHPThumb/Tests/GDTest.php tests/PHPThumb/Tests/LoadTest.php
```

Run only the Imagick tests:

```bash
vendor/bin/phpunit tests/PHPThumb/Tests/ImagickTest.php \
                   tests/PHPThumb/Tests/ImagickLoadTest.php \
                   tests/PHPThumb/Tests/ImagickOperationsTest.php \
                   tests/PHPThumb/Tests/ImagickOutputTest.php \
                   tests/PHPThumb/Tests/ImagickAdvancedTest.php \
                   tests/PHPThumb/Tests/ImagickPluginTest.php
```

Imagick tests skip automatically if `ext-imagick` is not loaded.

Network-dependent tests (e.g. loading remote images from URLs) are gated behind the `RUN_NETWORK_TESTS=1` environment variable:

```bash
RUN_NETWORK_TESTS=1 vendor/bin/phpunit tests/PHPThumb/Tests/ImagickRemoteImageTest.php
```

Format-coverage tests for BMP/HEIC/TIFF skip silently if the corresponding fixture file is absent.

---

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/my-feature`)
3. Add tests for any new behavior (in **both** `*GD*` and `*Imagick*` test files when adding to the core API)
4. Ensure the test suite passes (`vendor/bin/phpunit`)
5. Submit a pull request

For bugs and feature requests, please use the [issue tracker](https://github.com/PHPThumb/PHPThumb/issues) — and join the conversation in [Discussions](https://github.com/PHPThumb/PHPThumb/discussions).

---

## License

PHP Thumb is released under the **MIT License**. See [LICENSE](LICENSE) for details.

---

## Credits

- Original author: **Ian Selby**
- Imagick backend implementation
- Contributors listed on [GitHub](https://github.com/PHPThumb/PHPThumb/graphs/contributors)

# PHP Thumb

> **A lightweight, chainable image manipulation library for PHP**

PHP Thumb is a simple, modern image manipulation library aimed primarily at thumbnail generation. It provides a clean, fluent interface for common image operations, with **two interchangeable backends**: the ubiquitous **GD** extension and the more feature-rich **Imagick** extension.

---

## Table of Contents

- [What's New in 2.5](#whats-new-in-25)
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
  - [Orientation](#orientation)
  - [Flip](#flip)
  - [Sharpen](#sharpen)
  - [Gamma Correction](#gamma-correction)
  - [Border](#border)
  - [Text Overlay](#text-overlay)
  - [Advanced Filters](#advanced-filters)
  - [Output](#output)
- [Plugins](#plugins)
- [Configuration Options](#configuration-options)
- [Examples](#examples)
- [Documentation](#documentation)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## What's New in 2.5

PHPThumb 2.5 is a **feature expansion** release — every manipulation method still returns `$this` for chaining, and the API stays unified across the GD and Imagick backends. New capabilities:

| Feature | One-liner |
|---------|-----------|
| `autoOrient()` | Auto-rotate phone photos based on EXIF orientation |
| `flip()` | Mirror horizontally, vertically, or both |
| `gamma()` | Lighten/darken with output gamma |
| `sharpen()` | Unsharp-mask style sharpening |
| `border()` | Add a solid-color frame of any thickness |
| `text()` | Render text with font, color, size, shadow, stroke, background, and 9-grid positioning |
| **Advanced filter wrappers** | `grayscale()`, `brightness()`, `contrast()`, `blur()`, `pixelate()`, `edgeDetect()`, `emboss()`, `smooth()` — chainable convenience wrappers around `imageFilter()` |

All new methods are available on **both** backends. See the dedicated sections below for full documentation.

**Migration notes for 2.4 → 2.5:**

* The default options array now includes three additional keys: `sharpenAmount`, `textFont`, `textDefaultSize`. Existing code that compares `getOptions()` output byte-for-byte (i.e. test assertions) will need to be updated to include the new keys.
* No public method signatures were changed. No methods were renamed or removed.

---

## Features

- **Two interchangeable backends** — Use `PHPThumb\GD` (built on PHP's GD extension) or `PHPThumb\Imagick` (built on the PECL Imagick extension). Same API, switch with one `use` statement.
- **Fluent chaining API** — Perform multiple manipulations on a single instance
- **Flexible resizing** — By width, height, percentage, or adaptive (with crop)
- **Crop operations** — From-center, quadrant-based, percentage-based, or vanilla x/y cropping
- **Rotation** — 90° clockwise/counter-clockwise or arbitrary degrees
- **EXIF auto-orientation** — Correct phone-photo rotation based on embedded EXIF tags
- **Text overlay / captioning** — Render text with font, color, size, alignment, shadow, stroke, and background
- **Flip / mirror** — Horizontal, vertical, or both axes
- **Sharpen / unsharp mask** — One-call image sharpening
- **Gamma correction** — Adjust output gamma to brighten or darken
- **Border / frame** — Solid-color border around the image
- **Advanced filter wrappers** — Grayscale, brightness, contrast, blur, pixelate, edge detect, emboss, smooth
- **Image filters** — Grayscale, negate, brightness, blur, emboss, and more (via `imageFilter()`)
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
$thumb->autoOrient()              // correct EXIF rotation
      ->resize(300, 300)
      ->cropFromCenter(200, 200)
      ->sharpen()                 // crisp thumbnails
      ->border(4, '#000000')      // thin frame
      ->show();                   // Output to browser
// $thumb->save('out.jpg');       // Or save to file
// $thumb->getImageAsString();    // Or get raw binary as a string
```

To use Imagick instead, change only the `use` statement and the constructor call:

```php
use PHPThumb\Imagick;

$thumb = new Imagick('path/to/image.jpg');
$thumb->autoOrient()
      ->resize(300, 300)
      ->cropFromCenter(200, 200)
      ->sharpen()
      ->text('© Acme', 'bottom right', [
          'size'   => 14,
          'color'  => '#FFFFFF',
          'shadow' => ['enabled' => true],
      ])
      ->show();
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
| Text rendering | `imagettftext()` for TTF, built-in fonts as fallback | `ImagickDraw` with full TTF + built-in font support |
| Plugin output | Single-pass GD ops | Compositing with full alpha support |
| Constructor return on plugin dispatch | `PHPThumb\GD` | `PHPThumb\Imagick` |

**Recommendation**: use GD for typical web thumbnails (smaller memory footprint, no extension dependency). Use Imagick when you need BMP/HEIC/TIFF support, higher-quality resampling, pixel-accurate rotation, or richer text rendering.

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

### Orientation

#### `autoOrient(): self`
Reads the EXIF orientation tag (JPEG / TIFF / HEIC inputs) and rotates/mirrors the image so it displays upright. No-op if no EXIF tag is present, if the tag is `1` (normal), or if the `ext-exif` extension isn't loaded.

```php
$thumb = new GD('phone-photo.jpg');
$thumb->autoOrient()->resize(800, 0)->show();
```

### Flip

#### `flip(string $direction = 'horizontal'): self`
Mirrors the image. `$direction` accepts:

| Value | Aliases | Effect |
|-------|---------|--------|
| `'horizontal'` | `'h'`, `'lr'` | Mirror left ↔ right |
| `'vertical'` | `'v'`, `'tb'` | Mirror top ↔ bottom |
| `'both'` | `'hv'` | Mirror both axes |

```php
$thumb->flip();              // horizontal (default)
$thumb->flip('vertical');    // upside-down
$thumb->flip('both');        // 180° rotation equivalent
```

### Sharpen

#### `sharpen(int $amount = 50): self`
Sharpens the image with an unsharp-mask style kernel. `$amount` ranges from `0` (no sharpening) to `100` (strong sharpening).

```php
$thumb->sharpen();      // default 50
$thumb->sharpen(80);    // aggressive sharpening for previews
```

### Gamma Correction

#### `gamma(float $correction): self`
Adjusts the output gamma. Values < 1 darken, > 1 brighten. Realistic sRGB adjustment is in the `0.5`–`2.0` range.

```php
$thumb->gamma(1.5);   // brighten
$thumb->gamma(0.7);   // darken
```

### Border

#### `border(int $thickness, array|string $color = [0, 0, 0]): self`
Adds a solid-color frame of `$thickness` pixels around the image. `$color` may be an `[r, g, b]` array or a hex string (`'#FF8800'`).

`$color` accepts:

- A 3-element `[r, g, b]` array (e.g. `[255, 128, 0]`)
- A hex string (`'#FF8800'`, `'FF8800'`, `'#f80'`, or `'f80'` — with or without leading `#`, case-insensitive, 3/6/8-digit forms supported)

Alpha is preserved for PNG sources. Throws `\InvalidArgumentException` for negative thickness, malformed hex strings, or wrong-shaped color arrays.

```php
$thumb->border(10);                  // 10px black frame
$thumb->border(4, '#FFFFFF');        // 4px white frame
$thumb->border(2, [200, 200, 200]);  // 2px light-grey frame
```

### Text Overlay

#### `text(string $text, string $position = 'bottom-right', array $options = []): self`
Renders `$text` onto the image. The full options array is described below.

**Position keywords** (9-grid + edge aliases):

| String | Anchor |
|--------|--------|
| `'top-left'`, `'northwest'` | upper-left corner |
| `'top'`, `'north'`, `'top-center'` | top-center |
| `'top-right'`, `'northeast'` | upper-right corner |
| `'left'`, `'west'`, `'center-left'` | left-center |
| `'center'` | center |
| `'right'`, `'east'`, `'center-right'` | right-center |
| `'bottom-left'`, `'southwest'` | lower-left corner |
| `'bottom'`, `'south'`, `'bottom-center'` | bottom-center |
| `'bottom-right'`, `'southeast'` | lower-right corner |

**Options array:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `size` | `int` | `12` | Font size (px for Imagick, pt for GD TTF) |
| `color` | `string\|array` | `'#FFFFFF'` | Text color (hex or `[r, g, b]`) |
| `font` | `string\|null` | `null` | Path to TTF file (GD) or built-in font name (Imagick). `null` → built-in fallback on GD. |
| `angle` | `float` | `0` | Rotation in degrees |
| `offsetX` | `int` | `10` | Padding from the horizontal anchor |
| `offsetY` | `int` | `10` | Padding from the vertical anchor |
| `align` | `string` | `'center'` | Per-line horizontal alignment: `'left'` / `'center'` / `'right'` |
| `alpha` | `int` | `100` | Text opacity, 0–100 |
| `shadow` | `array` | `['enabled' => false]` | Drop shadow: keys `enabled`, `color`, `offsetX`, `offsetY`, `blur` |
| `stroke` | `array` | `['enabled' => false]` | Text outline: keys `enabled`, `color`, `width` |
| `background` | `array` | `['enabled' => false]` | Text background pill: keys `enabled`, `color`, `padding`, `alpha` |

```php
// Simple caption
$thumb->text('© 2025 Acme', 'bottom-right', [
    'size'  => 14,
    'color' => '#FFFFFF',
    'shadow' => ['enabled' => true],
]);

// Centered title with background pill
$thumb->text('SALE', 'center', [
    'size'   => 48,
    'color'  => '#FF0000',
    'background' => [
        'enabled' => true,
        'color'   => '#FFFFFF',
        'padding' => 12,
        'alpha'   => 85,
    ],
]);

// TTF font with stroke (Imagick only — built-in fonts can't be stroked reliably)
$thumb->text('Brand', 'top-left', [
    'font'   => '/path/to/brand.ttf',
    'size'   => 36,
    'color'  => '#222222',
    'stroke' => ['enabled' => true, 'color' => '#FFFFFF', 'width' => 2],
]);
```

> **Backend note:** On GD, stroke rendering requires a TTF font (`imagettftext()` does the stroke internally). On Imagick, both built-in and TTF fonts support stroke via `ImagickDraw::setStrokeColor` + `setStrokeWidth`.

### Advanced Filters

Convenience wrappers around the platform-native `imageFilter()` — all available on **both** backends, all chainable.

| Method | Underlying GD constant | Underlying Imagick method |
|--------|------------------------|----------------------------|
| `grayscale(): self` | `IMG_FILTER_GRAYSCALE` | `setImageColorspace(GRAY)` |
| `brightness(int $level): self` | `IMG_FILTER_BRIGHTNESS` | `modulateImage(100+$level, 100, 100)` |
| `contrast(int $level): self` | `IMG_FILTER_CONTRAST` | `contrastImage($level)` |
| `blur(int|float $amount = 1): self` | `IMG_FILTER_GAUSSIAN_BLUR` | `gaussianBlurImage(0, $amount)` |
| `pixelate(int $blockSize = 10): self` | `IMG_FILTER_PIXELATE` | scale down + scale up |
| `edgeDetect(): self` | `IMG_FILTER_EDGEDETECT` | `edgeImage(1)` |
| `emboss(): self` | `IMG_FILTER_EMBOSS` | `embossImage(0, 1)` |
| `smooth(int $level = 1): self` | `IMG_FILTER_SMOOTH` | `gaussianBlurImage($level, 1)` |

```php
// Muted preview: desaturate + soften
$thumb->grayscale()->smooth(3);

// "Incognito mode" blur for previews
$thumb->pixelate(8);

// Dramatic edge-detected poster
$thumb->edgeDetect()->contrast(-20);
```

For backend-specific magic (e.g. Imagick's `sepiaToneImage`, GD's `IMG_FILTER_MEAN_REMOVAL`), use `imageFilter()` directly with the native constant.

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
| `sharpenAmount` | `int` | `50` | GD & Imagick | Default sharpening strength used by `sharpen()` when no argument is passed |
| `textFont` | `string\|null` | `null` | GD & Imagick | Default TTF font path used by `text()` when `options.font` is not provided |
| `textDefaultSize` | `int` | `12` | GD & Imagick | Default font size used by `text()` when `options.size` is not provided |

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

// Auto-orient a phone photo
(new PHPThumb\GD('phone-photo.jpg'))->autoOrient()->resize(800, 0)->show();

// Mirror horizontally
(new PHPThumb\GD('photo.jpg'))->flip()->save('mirror.jpg');

// Sharpen after resize
(new PHPThumb\GD('photo.jpg'))
    ->resize(400, 0)
    ->sharpen(75)
    ->save('crisp.jpg');

// Add a frame
(new PHPThumb\GD('photo.jpg'))
    ->resize(400, 0)
    ->border(6, '#000000')
    ->save('framed.jpg');

// Brighten + add a watermark-style text caption
(new PHPThumb\GD('photo.jpg'))
    ->gamma(1.3)
    ->text('© Acme', 'bottom-right', [
        'size'  => 14,
        'color' => '#FFFFFF',
        'shadow' => ['enabled' => true],
    ])
    ->save('captioned.jpg');

// Grayscale + slight blur (GD)
(new PHPThumb\GD('photo.jpg'))
    ->imageFilter(IMG_FILTER_GRAYSCALE)
    ->imageFilter(IMG_FILTER_GAUSSIAN_BLUR)
    ->save('muted.jpg');

// Grayscale + blur (chainable wrapper, both backends)
(new PHPThumb\GD('photo.jpg'))
    ->grayscale()
    ->blur(3)
    ->save('soft.jpg');

// Pad onto a colored canvas
(new PHPThumb\GD('photo.jpg'))->pad(1200, 800, [0, 0, 0])->show();

// Format conversion
(new PHPThumb\GD('photo.jpg'))->save('photo.webp', 'WEBP');

// Chain everything
(new PHPThumb\GD('photo.jpg'))
    ->autoOrient()
    ->resize(0, 800)
    ->cropFromCenter(800, 800)
    ->sharpen()
    ->text('Final', 'bottom-right', ['color' => '#FFFFFF', 'shadow' => ['enabled' => true]])
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

// Imagick: text caption with TTF font and stroke
(new PHPThumb\Imagick('photo.jpg'))
    ->text('Hello', 'center', [
        'font'   => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        'size'   => 48,
        'color'  => '#FF8800',
        'stroke' => ['enabled' => true, 'color' => '#000000', 'width' => 2],
    ])
    ->show();

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
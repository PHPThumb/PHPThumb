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
- [Quality Presets](#quality-presets)
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
| `text()` | Render text with font (auto-resolved via fontconfig), color, size, shadow, stroke, background, multi-line alignment, and 9-grid positioning |
| **Advanced filter wrappers** | `grayscale()`, `brightness()`, `contrast()`, `blur()`, `pixelate()`, `edgeDetect()`, `emboss()`, `smooth()` — chainable convenience wrappers around the platform-native filter call |
| **Quality presets** | `setQualityPreset('off' \| 'max' \| 'high' \| 'medium' \| 'low')` — one call sets a perceptually comparable quality tier across JPEG, WebP, AVIF and JXL |
| **Per-format quality options** | `jpegQuality`, `webpQuality`, `avifQuality` and the new `jxlQuality` are independent knobs; `null` means "use the codec's own default" |

All new methods are available on **both** backends. See the dedicated sections below for full documentation.

**Migration notes for 2.4 → 2.5:**

* The default options array now includes four additional keys: `sharpenAmount`, `textFont`, `textDefaultSize`, `jxlQuality`. Existing code that compares `getOptions()` output byte-for-byte (i.e. test assertions) will need to be updated to include the new keys.
* The quality options now accept `null`, which means "apply no quality setting" and leaves the codec's own default in place. This is distinct from a quality of `0`.
* Quality options are now honoured by `show()` as well as `save()`. Previously, streamed output silently ignored them.
* The only public signature change is `imageFilter()`, whose optional arguments were widened from `bool` to `int|float|bool`. This is a widening, so existing calls are unaffected — but numeric arguments now reach the filter instead of being coerced to `true`.
* `sharpen()` is now `sharpen(?int $amount = null)`. Called without an argument it uses the `sharpenAmount` option (default `50`); an explicit argument always wins, and `0` remains a no-op.
* The plugin stack now runs on `save()` and `getImageAsString()` as well as `show()`, **at most once per instance** — so streaming *and* saving the same thumbnail no longer composites a watermark twice.
* `PHPThumb\Imagick::FILTER_*` class constants are new public API.

---

## Features

### Core image operations

- **Two interchangeable backends** — Use `PHPThumb\GD` (built on PHP's GD extension) or `PHPThumb\Imagick` (built on the PECL Imagick extension). Same API, switch with one `use` statement.
- **Fluent chaining API** — Perform multiple manipulations on a single instance
- **Flexible resizing** — By width, height, percentage, or adaptive (with crop)
- **Crop operations** — From-center, quadrant-based, percentage-based, or vanilla x/y cropping
- **Rotation** — 90° clockwise/counter-clockwise or arbitrary degrees
- **Remote image support** — Load images from URLs as well as local files
- **Raw image bytes** — Pass the binary content of an image directly (a database blob, an upload, a data-URI payload) — supported by both backends

### Image correction & enhancement

- **EXIF auto-orientation** — Correct phone-photo rotation based on embedded EXIF tags
- **Flip / mirror** — Horizontal, vertical, or both axes
- **Sharpen** — One-call unsharp-mask sharpening
- **Gamma correction** — Adjust output gamma to brighten or darken
- **Filters** — Chainable wrappers (`grayscale`, `brightness`, `contrast`, `blur`, `pixelate`, `edgeDetect`, `emboss`, `smooth`) for the common cases, plus a low-level `imageFilter()` escape hatch for any backend-native filter constant.

### Text & decoration

- **Text overlay / captioning** — Render text with font (auto-resolved via fontconfig on Linux/macOS), color, size, multi-line alignment (`align`), per-line spacing (`lineHeight`), shadow, stroke, and background pill
- **Border / frame** — Solid-color border around the image

### Output quality control

- **Independent per-format quality** — `jpegQuality`, `webpQuality`, `avifQuality` and `jxlQuality` are separate knobs, because the 0–100 scale is not interchangeable between codecs
- **Quality presets** — One call (`setQualityPreset()` or `'qualityPreset' => …`) applies a perceptually comparable quality tier across every supported format

### Extensibility & integration

- **Plugin system** — Extend the library with custom manipulations (Reflection, Trim, Watermark included). Plugins transparently dispatch to the correct backend.
- **Modern PHP 8.2+** — Strict types, enums where appropriate, modern syntax
- **Composer-ready** — PSR-4 autoloading and Composer integration out of the box

---

## Requirements

- **PHP 8.2** or higher
- At least one of:
  - **GD** extension (>= 2.3.2)
  - **Imagick** extension (>= 3.7.0; required for BMP, HEIC, JXL, TIFF, and other Imagick-only formats)
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
      ->text('© PHPThumb', 'bottom right', [
          'size'   => 14,
          'color'  => '#FFFFFF',
          'shadow' => ['enabled' => true],
      ])
      ->show();
```

The fluent API is identical across both backends.

### Loading from raw bytes

Both backends also accept the **binary content** of an image directly — useful for a database column, an upload, or a data-URI payload:

```php
$bytes = file_get_contents('path/to/image.jpg');

$thumb = new GD($bytes);        // or new Imagick($bytes)
$thumb->resize(400, 0)->show();
```

A source is treated as raw bytes when it is neither an existing file nor a valid URL, and it looks like image data — it carries a known image signature, or it is binary. A path that does not exist is still reported as `Image file not found`, so a typo is never mistaken for a byte string.

---

## Choosing a Backend: GD vs Imagick

Both backends implement the same `PHPThumb\PHPThumb` abstract API. They differ in format support, performance characteristics, and image quality on edge cases.

| Concern | `PHPThumb\GD` | `PHPThumb\Imagick` |
|---------|---------------|--------------------|
| Default availability | Bundled with PHP since PHP 8 | Requires PECL extension |
| Formats | JPEG, PNG, GIF, WebP, AVIF | JPEG, PNG, GIF, WebP, AVIF, **BMP, HEIC, JXL, TIFF** |
| Sources accepted | File path, URL, raw bytes | File path, URL, raw bytes |
| Memory usage | Lower | Higher (full pixel buffer) |
| Resize quality | Good (bilinear resampling) | Excellent (Lanczos / Catmull on Imagick 7) |
| Rotation | Approximate — black triangles in the corners where the canvas expands | Pixel-accurate, with an explicit fill colour |
| Text rendering | TTF via `imagettftext()` with fontconfig auto-resolution; built-in fonts as fallback. Renders ~25–35% larger than Imagick at the same `size` on Linux. | `ImagickDraw` with TTF and built-in font support. Smaller at the same `size` than GD. |
| PNG alpha through `border()` | Preserved | Lost — the frame is composited onto an opaque canvas |
| Plugin output | Single-pass GD ops | Compositing with full alpha support |
| Instance type after a manipulation | `PHPThumb\GD` | `PHPThumb\Imagick` |

**Recommendation**: use GD for typical web thumbnails (smaller memory footprint, no extension dependency). Use Imagick when you need BMP/HEIC/JXL/TIFF support, higher-quality resampling, pixel-accurate rotation, or richer text rendering.

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
| JXL    | ❌ | ✅ |
| TIFF   | ❌ | ✅ |

Format support is detected automatically; an exception is thrown if your environment can't handle the loaded file.

> **JPEG XL** requires ImageMagick to be compiled with `libjxl`. On a build without the delegate, loading a JXL file or writing one throws an `ImagickException`. The `jxlQuality` option exists only on the Imagick backend — GD has no JPEG XL codec at all.

> **HEIC** likewise depends on the ImageMagick build carrying the HEIC delegate (typically `libheif`).

> Loading directly from URLs is supported by both backends, as is passing the **raw bytes** of an image.

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

#### `resizePercent(int $percent = 0): self`
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

> **Note:** the three adaptive resizers clamp their result to the source dimensions unless the `resizeUp` option is `true`. Asking for 200×400 from a 500×375 landscape source yields 200×375, not 200×400.
>
> A **width** of `0` is rejected with an `\InvalidArgumentException` on both backends. A **height** of `0` is derived from the source image's aspect ratio, so `adaptiveResizePercent(200, 0, 25)` produces a 200×150 crop from a 500×375 source.

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

> **Backend difference:** GD rotates onto a **black** background, so arbitrary rotations leave black triangles in the corners where the canvas expanded beyond the source. Imagick rotates onto the `alphaMaskColor` option (default white) and produces clean corners.

#### `imageFilter(int $filter, int|float|bool $arg1 = false, int|float|bool $arg2 = false, int|float|bool $arg3 = false, int|float|bool $arg4 = false): self`
Applies a low-level filter. The filter ids are **backend-specific** — the two backends do not share an id space, so there is no portable "grayscale id".

**GD** — any `IMG_FILTER_*` constant:

```php
$thumb->imageFilter(IMG_FILTER_GRAYSCALE);
$thumb->imageFilter(IMG_FILTER_COLORIZE, 100, 0, 0); // Red tint
$thumb->imageFilter(IMG_FILTER_BRIGHTNESS, 50);
```

**Imagick** — use `PHPThumb\Imagick::FILTER_*`.

> **Why not `Imagick::FILTER_*`?** The legacy `Imagick::filter()` method and its `Imagick::FILTER_*` constants wrapped ImageMagick 6's `MagickFilterImage()` API. ImageMagick 7 removed that API, so ext-imagick 3.5+ dropped both. Referring to the global constant on a modern build is an *undefined constant* error at the call site. (There were never `Imagick::FILTER_GRAYSCALE` or `Imagick::FILTER_NEGATE` constants in any version — those names are GD's.)

PHPThumb republishes the same ids as class constants and implements the filters on primitives that exist in **both** ImageMagick 6 and 7:

| Constant | Id | Effect | Native primitive |
|----------|----|--------|------------------|
| `PHPThumb\Imagick::FILTER_POINT` | 1 | No-op (each pixel onto itself) | — |
| `PHPThumb\Imagick::FILTER_EDGEDETECT` | 3 | Edge detection | `edgeImage()` |
| `PHPThumb\Imagick::FILTER_EMBOSS` | 4 | Emboss | `embossImage()` |
| `PHPThumb\Imagick::FILTER_GAUSSIAN` | 5 | Gaussian blur | `gaussianBlurImage()` |
| `PHPThumb\Imagick::FILTER_LAPLACE` | 6 | Laplacian | 3×3 convolution |
| `PHPThumb\Imagick::FILTER_LAPLACE_ROBERTS` | 7 | Laplacian of Roberts | 3×3 convolution |
| `PHPThumb\Imagick::FILTER_OCTAGON` | 8 | Octagon | 3×3 convolution |
| `PHPThumb\Imagick::FILTER_PREWITT` | 9 | Prewitt | 3×3 convolution |
| `PHPThumb\Imagick::FILTER_ROBERTS` | 10 | Roberts cross | 3×3 convolution |
| `PHPThumb\Imagick::FILTER_SOBEL` | 11 | Sobel | 3×3 convolution |
| `PHPThumb\Imagick::FILTER_UNSHARP` | 12 | Unsharp mask | `sharpenImage()` |

```php
// Imagick
$thumb->imageFilter(\PHPThumb\Imagick::FILTER_SOBEL);
$thumb->imageFilter(\PHPThumb\Imagick::FILTER_GAUSSIAN, 2, 2); // radius, sigma
```

Notes:

- `FILTER_CELLULAR` (id `2`) has no ImageMagick 7 equivalent and throws `\InvalidArgumentException`.
- `FILTER_UNDEFINED` (id `0`) is a sentinel, not a filter — it also throws.
- An unknown id throws `\InvalidArgumentException`.
- The convolution filters produce the raw kernel response. That is close to, but not bit-identical with, the legacy ImageMagick 6 result.
- The optional arguments are typed `int|float|bool`; `false` means *"not supplied"*. Numeric values are honoured (radius / sigma for the filters that take them).
- `Imagick::convolveImage()` changed signature between ext-imagick generations, and on some builds a kernel built from a PHP matrix comes back empty. When the native path is unusable, a pure-PHP convolution is used instead, so the result is identical on every build.

> ⚠️ **Never pass a GD constant to the Imagick backend.** The two id spaces overlap numerically, and the overlap **fails silently** — it applies a different filter rather than raising an error:
>
> | GD constant | Id | What the Imagick backend would apply |
> |---|---|---|
> | `IMG_FILTER_NEGATE` | 0 | throws (`FILTER_UNDEFINED`) |
> | `IMG_FILTER_GRAYSCALE` | 1 | `FILTER_POINT` — a **no-op** |
> | `IMG_FILTER_BRIGHTNESS` | 2 | throws (`FILTER_CELLULAR`) |
> | `IMG_FILTER_CONTRAST` | 3 | `FILTER_EDGEDETECT` |
> | `IMG_FILTER_COLORIZE` | 4 | `FILTER_EMBOSS` |
> | `IMG_FILTER_EDGEDETECT` | 5 | `FILTER_GAUSSIAN` |
> | `IMG_FILTER_EMBOSS` | 6 | `FILTER_LAPLACE` |
> | `IMG_FILTER_SMOOTH` | 7 | `FILTER_LAPLACE_ROBERTS` |
> | `IMG_FILTER_PIXELATE` | 8 | `FILTER_OCTAGON` |
> | `IMG_FILTER_MEAN_REMOVAL` | 9 | `FILTER_PREWITT` |
> | `IMG_FILTER_SELECTIVE_BLUR` | 10 | `FILTER_ROBERTS` |
> | `IMG_FILTER_RESIZE` | 11 | `FILTER_SOBEL` |
>
> Only two of the twelve fail loudly. Use the chainable wrappers (`grayscale()`, `brightness()`, `contrast()`, `blur()`, …) when you want the same effect on both backends.

### Pad

#### `pad(int $width, int $height, array $color = [255, 255, 255]): self`
Centers the image on a canvas of the given size, filling the rest with `$color`.

```php
$thumb->pad(500, 500, [255, 255, 255]); // White background
$thumb->pad(800, 600, [0, 0, 0]);       // Black background
```

### Orientation

#### `autoOrient(): self`
Reads the EXIF orientation tag and rotates/mirrors the image so it displays upright.

**Backend scope:**

- **GD** — only handles **JPEG** sources. For PNG, GIF, WEBP, AVIF, HEIC and TIFF it is a silent no-op. Requires `ext-exif`.
- **Imagick** — handles any format that carries EXIF (JPEG, TIFF, HEIC) natively, via `Imagick::autoOrient()`. Does **not** require `ext-exif`.

No-op when the image has no EXIF tag, when the tag is `1` (normal), or when it falls outside the 1–8 range. Throws no exceptions — every failure degrades to a no-op.

Both backends are **idempotent**: calling `autoOrient()` twice never rotates twice.

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

#### `sharpen(?int $amount = null): self`
Sharpens the image with an unsharp-mask style kernel. `$amount` ranges from `0` (no sharpening) to `100` (strong sharpening).

Called **without an argument**, it uses the `sharpenAmount` option (default `50`). An explicit argument always wins over the option, and `0` remains a no-op.

```php
$thumb->sharpen();      // uses the sharpenAmount option (default 50)
$thumb->sharpen(80);    // explicit — always wins over the option

$thumb = new GD('photo.jpg', ['sharpenAmount' => 70]);
$thumb->sharpen();      // sharpens at 70
```

> **Backend difference:** the two backends sharpen with different algorithms. Imagick uses a Gaussian-style sharpen (`Imagick::sharpenImage()`); GD uses an unsharp-mask style 3×3 convolution. Both preserve flat regions, but the pixel response differs — don't expect byte-identical output at the same amount.

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

> **Backend difference — PNG transparency.** GD preserves the alpha channel of a PNG source, so transparent regions stay transparent inside the frame. Imagick composites onto an **opaque** canvas, so those regions take the border colour instead. Use a JPEG source if you want identical output on both backends.

### Text Overlay

#### `text(string $text, string $position = 'bottom-right', array $options = []): self`
Renders `$text` onto the image. The full options array is described below.

**Position keywords** (9-grid + edge aliases):

| String | Anchor |
|--------|--------|
| `'top-left'`, `'northwest'` | upper-left corner |
| `'top'`, `'north'`, `'upper'`, `'top-center'` | top-center |
| `'top-right'`, `'northeast'` | upper-right corner |
| `'left'`, `'west'`, `'center-left'` | left-center |
| `'center'` | center |
| `'right'`, `'east'`, `'center-right'` | right-center |
| `'bottom-left'`, `'southwest'` | lower-left corner |
| `'bottom'`, `'south'`, `'lower'`, `'bottom-center'` | bottom-center |
| `'bottom-right'`, `'southeast'` | lower-right corner |

**Options array:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `size` | `int` | `12` | Font size in pixels. Both backends interpret this as a pixel size; see the [backend parity note](#backend-parity-text) below for the known size-rendering difference. |
| `color` | `string\|array` | `'#FFFFFF'` | Text color: hex string (`'#FF8800'`, `'FF8800'`, `'#f80'`), shorthand hex, or `[r, g, b]` array. |
| `font` | `string\|null` | `null` | TTF file path, fontconfig family name (e.g. `'sans-serif'`, `'DejaVu Sans'`), or Imagick built-in font name (`'Courier'`, `'Helvetica'`, `'Times'`). See the [font resolution strategy](#font-resolution-strategy) below. |
| `angle` | `float` | `0` | Rotation in degrees. Positive = counter-clockwise. The Imagick backend negates this internally so the same value produces visually identical rotation on both backends. |
| `offsetX` | `int` | `10` | Padding from the horizontal anchor. |
| `offsetY` | `int` | `10` | Padding from the vertical anchor. |
| `align` | `string` | `'center'` | Per-line horizontal alignment: `'left'` / `'center'` / `'right'`. Multi-line text (`"\n"`) is rendered line-by-line so per-line alignment works correctly. |
| `alpha` | `int` | `100` | Text opacity, 0–100. |
| `lineHeight` | `float` | `1.3` with a TTF font; `1.5` with GD's built-in fallback font | Multi-line spacing multiplier on `size`. `1.0` = tight (no gap), `1.5` = ~50% gap, `2.0` = doubles line spacing. The effective line height is the larger of `size × lineHeight` and a font-metric floor (`ascender + descender`, buffered by 30%), so lines never overlap regardless of the multiplier. Lowering it below the floor has no effect. |
| `shadow` | `array` | `['enabled' => false]` | Drop shadow: keys `enabled`, `color`, `offsetX`, `offsetY`, `blur`. The `blur` key is currently parsed but unused (both backends use vector text rendering; blurred shadows would require a separate Gaussian blur pass). |
| `stroke` | `array` | `['enabled' => false]` | Text outline: keys `enabled`, `color`, `width`. Requires a TTF font on GD; on Imagick, built-in fonts don't stroke reliably — pass a TTF for consistent results. |
| `background` | `array` | `['enabled' => false]` | Text background pill: keys `enabled`, `color`, `padding`, `alpha`. Drawn once around the full multi-line block. |

#### Font resolution strategy

The `'font'` option accepts three shapes of input, resolved in order:

1. **Explicit TTF file path** — used directly if the file exists. If the path doesn't exist, the GD backend emits an `E_USER_WARNING` and falls through; the Imagick backend falls through silently.
2. **fontconfig family name** (no `/`, no file extension) — resolved via `fc-match` if fontconfig is installed. Examples: `'sans-serif'`, `'DejaVu Sans'`, `'Liberation Serif'`. This is the recommended approach on Linux/macOS — no need to hard-code system paths.
3. **System font path scan** — last-resort lookup of well-known paths: `/usr/share/fonts/dejavu/DejaVuSans*.ttf`, `/Library/Fonts/Arial.ttf`, `C:\Windows\Fonts\arial.ttf`, etc.
4. **Built-in font** (GD only) — bitmap font index 5. **This ignores `size`, `angle`, `shadow`, and `stroke`.** Install `fonts-dejavu-core` (Debian/Ubuntu), `dejavu-sans-fonts` (Fedora), or `ttf-dejavu` (Arch) to get a real TTF font and unlock the full option surface.

#### Draw order

For each line, layers are rendered in this order:

1. **Background pill** — drawn once around the full multi-line block (not per-line), with the configured `padding` margin.
2. **Shadow** — separate render pass (GD: a second `imagettftext()` call; GD: a second `imagettftext()` call; Imagick: separate `annotateImage()` call with a shadow-only `ImagickDraw`), offset by `shadow.offsetX/Y`.
3. **Stroke** — separate render pass. GD uses a per-pixel-offset replication loop (since `imagettftext()` has no native stroke); Imagick uses an `ImagickDraw` with transparent fill + `setStrokeWidth()`. Both produce a stroke outline that sits at the glyph edge.
4. **Main text** — final render pass, on top of all the above.

#### Multi-line metrics

Line spacing uses font-level metrics measured from a probe string (`'Hgjpqy0123456789'` — covers both tall caps and tall descenders) rather than per-string metrics. This avoids the quirk where `imagettfbbox()` (and Imagick's `queryFontMetrics()`) under-report the descender for strings without descender glyphs — e.g. `'PHPThumb 2.5'` has no `g`, `p`, `q`, or `y`, so its per-string bbox returns a near-zero descender. With probe-based metrics, line spacing is correct for any input string.

When stroke is enabled, the formula adds `2 × stroke_w` to the line height — each stroked line expands by `stroke_w` in all directions, so the inter-line gap is reduced by `stroke_w` on each side. The compensation cancels this.

```php
// Simple caption (uses fontconfig default font)
$thumb->text('© 2026 PHPThumb', 'bottom-right', [
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

// TTF font with stroke, shadow, and rotation
$thumb->text('Brand', 'top-left', [
    'font'   => '/path/to/brand.ttf',
    'size'   => 36,
    'color'  => '#222222',
    'angle'  => -15,    // 15° clockwise on both backends
    'shadow' => ['enabled' => true, 'color' => '#000000', 'offsetX' => 2, 'offsetY' => 2],
    'stroke' => ['enabled' => true, 'color' => '#FFFFFF', 'width' => 2],
]);

// Multi-line, right-aligned, with a background pill and explicit line spacing
$thumb->text("© PHPThumb\n© 2026", 'bottom-right', [
    'size'       => 14,
    'color'      => '#FFFFFF',
    'align'      => 'right',
    'lineHeight' => 1.4,
    'background' => ['enabled' => true, 'color' => '#000000', 'padding' => 8, 'alpha' => 60],
]);
```

#### Backend parity: text

The GD and Imagick backends use **opposite native rotation conventions** (`imagettftext()` rotates CCW for positive angles; `annotateImage()` rotates CW). To preserve a consistent API, the Imagick backend negates `angle` internally, so `'angle' => -15` means 15° clockwise on both backends regardless of which one you instantiate.

The GD backend renders text noticeably larger than Imagick at the same `size` value — typically ~25–35% larger on Linux, due to different DPI conventions. If you need pixel-identical output across backends, scale the `size` value per backend in your calling code (e.g. multiply by 0.78 for GD if your target value is calibrated for Imagick). This is a documented backend difference, not a bug.

> **Backend note (stroke):** On GD, stroke rendering requires a TTF font (`imagettftext()` does the stroke internally). On Imagick, stroke works for both built-in and TTF fonts via `ImagickDraw::setStrokeColor` + `setStrokeWidth`, but built-in fonts render less cleanly than TTFs — pass a TTF for consistent results.

### Advanced Filters

Convenience wrappers around the platform-native `imageFilter()` — all available on **both** backends, all chainable.

| Method | Underlying GD constant | Underlying Imagick method |
|--------|------------------------|----------------------------|
| `grayscale(): self` | `IMG_FILTER_GRAYSCALE` | `modulateImage(100, 0, 100)` |
| `brightness(int $level): self` | `IMG_FILTER_BRIGHTNESS` | `modulateImage(100+$level, 100, 100)` |
| `contrast(int $level): self` | `IMG_FILTER_CONTRAST` | `contrastImage($level)` |
| `blur(int\|float $amount = 1): self` | `IMG_FILTER_GAUSSIAN_BLUR` | `gaussianBlurImage(0, $amount)` |
| `pixelate(int $block_size = 10): self` | `IMG_FILTER_PIXELATE` | scale down + scale up |
| `edgeDetect(): self` | `IMG_FILTER_EDGEDETECT` | `edgeImage(1)` |
| `emboss(): self` | `IMG_FILTER_EMBOSS` | `embossImage(0, 1)` |
| `smooth(int $level = 1): self` | `IMG_FILTER_SMOOTH` | `gaussianBlurImage(max(0.5, $level), 1)` |

```php
// Muted preview: desaturate + soften
$thumb->grayscale()->smooth(3);

// "Incognito mode" blur for previews
$thumb->pixelate(8);

// Dramatic edge-detected poster
$thumb->edgeDetect()->contrast(-20);
```
For backend-specific magic, use the native constant on **GD** (`IMG_FILTER_MEAN_REMOVAL`, `IMG_FILTER_SELECTIVE_BLUR`, `IMG_FILTER_COLORIZE`, …).

The Imagick backend's `imageFilter()` covers only the spatial-filter family listed above. For anything else — `sepiaToneImage()`, `cannyEdgeImage()`, `posterizeImage()` — reach the underlying `Imagick` object through `getOldImage()`:

```php
$thumb->getOldImage()->sepiaToneImage(80);
```

> **Backend difference:** `brightness()`, `contrast()` and `smooth()` map onto different native operations in each backend, and the magnitude is not calibrated between them — a given `$level` is not equally strong on both. The direction of the effect matches.

### Output

#### `show(bool $raw_data = false): self`
Outputs the image directly to the browser (sets the appropriate `Content-Type` header). Pass `true` for `$raw_data` to skip the header.

The configured encoder quality (see [Configuration Options](#configuration-options)) is applied before the image is emitted, so streamed output matches what `save()` would have written.

#### `save(string $file_name, ?string $format = null): self`
Writes the image to disk. Format is auto-detected from the current image, or you can force a format:

```php
$thumb->save('output.jpg');         // Keep original format
$thumb->save('output.png', 'PNG');  // Force conversion to PNG
```

Valid `$format` values: `AVIF`, `GIF`, `JPEG`, `JPG`, `PNG`, `WEBP` (BMP/HEIC/JXL/TIFF on Imagick via `save($path, 'BMP')` etc.).

#### `getImageAsString(): string`
Returns the raw image binary as a string — perfect for storage in databases or inline `<img>` data URIs.

```php
$dataUri = 'data:image/jpeg;base64,' . base64_encode($thumb->getImageAsString());
```

> **Plugins run on output.** The plugin stack is executed automatically by `show()`, `save()` and `getImageAsString()` — but **at most once per instance**. Whichever of those you call first applies the plugins; the later calls write or stream the already-plugged image. Without that guard a watermark would be composited twice when you both stream and save the same thumbnail.
>
> The `Content-Type` header emitted by `show()` is derived from the image's format through the library's alias table, so builds that report a delegate alias still emit a correct type — `JPE` → `image/jpeg`, `TIF` → `image/tiff`, `JPEGXL` → `image/jxl`.

---

## Plugins

Plugins extend PHPThumb with custom operations. They implement `PHPThumb\PluginInterface` and receive the active `PHPThumb` instance, which they can manipulate via public getters/setters.

Plugins are passed as the third constructor argument and are executed automatically before `show()`, `save()` or `getImageAsString()`. All built-in plugins transparently dispatch to the correct backend — you can pass `PHPThumb\GD` or `PHPThumb\Imagick` instances interchangeably, and the plugin will call the right code path.

> **The plugin stack runs at most once per instance.** Whichever of `show()` / `save()` / `getImageAsString()` you call first applies the plugins; later calls write or stream the already-plugged image. Without that guard a watermark would be composited twice when you both stream and save the same thumbnail.

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
$wm = new PHPThumb\Imagick('logo.png');

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

> **Note:** the watermark image must be a `PHPThumb\GD` or `PHPThumb\Imagick` instance — not a raw file path and not a bare `\GdImage` / `\Imagick`. The plugin reads the watermark's current dimensions and pixels through that interface.

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

**Sides** — any combination of `T` (top), `B` (bottom), `L` (left), `R` (right). An empty string or more than four characters throws `\InvalidArgumentException`, as does an RGB array that isn't three values in `0..255`.

> **Backend difference:** the Imagick branch matches on **RGB only** and ignores alpha. The GD branch additionally treats a fully-transparent white pixel as a match. The practical effect is the same for ordinary scans.

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

**Parameters:**

| Parameter | Default | Meaning |
|-----------|---------|---------|
| `percent` | 50 | Which slice of the source is mirrored — 50 means the bottom half |
| `reflection` | 50 | Height of the reflection as a percentage of the source height |
| `white` | 80 | Fade intensity — how strongly the white overlay washes out the reflection |
| `border` | `false` | Draw a separator border between the image and its reflection |
| `border_color` | `'#FFFFFF'` | Border colour, as a hex string or RGB array |

> **Backend difference — the fade shape.** The GD branch paints a white rectangle on each row with a rising alpha, producing a **linear** gradient down the reflection. The Imagick branch composites a single solid-white overlay whose alpha is scaled by `white / 100`, producing a **constant** fade. The overall effect is similar, but the two are not pixel-identical.

> **Backend difference — the mirror.** The GD branch flips with `imageflip(IMG_FLIP_VERTICAL)`. The Imagick branch rotates 180° and then flops horizontally, which is the canonical Imagick equivalent of a vertical flip.

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
| `jpegQuality` | `int\|null` | `100` | GD & Imagick | JPEG output quality (0–100). `null` = codec default |
| `jxlQuality` | `int\|null` | `90` | **Imagick only** | JPEG XL output quality (0–100). Independent of `jpegQuality`. `null` = codec default |
| `webpQuality` | `int\|null` | `100` | GD & Imagick | WebP output quality (0–100). `null` = codec default |
| `avifQuality` | `int\|null` | `100` | GD & Imagick | AVIF output quality (0–100). `null` = codec default |
| `qualityPreset` | `string` | — | GD & Imagick | Constructor / `setOptions()` shortcut; applies a named preset (`off`, `max`, `high`, `medium`, `low`). Not retained as an option |
| `correctPermissions` | `bool` | `false` | GD & Imagick | Attempt to `chmod` the target directory if not writable |
| `preserveAlpha` | `bool` | `true` | GD & Imagick | Preserve PNG alpha channel |
| `alphaMaskColor` | `array` | `[255, 255, 255]` | GD & Imagick | RGB colour used for the PNG transparent fill on GD; the **rotation background** on Imagick |
| `preserveTransparency` | `bool` | `true` | GD & Imagick | Preserve GIF / WebP transparency |
| `transparencyMaskColor` | `array` | `[0, 0, 0]` | GD & Imagick | RGB colour used for the GIF transparency mask |
| `interlace` | `bool\|null` | `null` | GD & Imagick | Enable/disable interlacing (progressive rendering). `null` = leave default |
| `sharpenAmount` | `int` | `50` | GD & Imagick | Default sharpening strength (0–100) used by `sharpen()` when it is called **without an argument**. Setting this option does not by itself apply sharpening to the image — call `sharpen()` for that. An explicit argument to `sharpen()` overrides it. |
| `textFont` | `string\|null` | `null` | GD & Imagick | Default TTF font path used by `text()` when `options.font` is not provided |
| `textDefaultSize` | `int` | `12` | GD & Imagick | Default font size used by `text()` when `options.size` is not provided |

### Quality options are per-format, not interchangeable

The 0–100 scale is **not** comparable between codecs. Each maps it onto a different internal parameter:

| Format | ImageMagick / codec parameter | Notes |
|--------|------------------------------|-------|
| JPEG | libjpeg quantization table scale | `100` is still **lossy** (DCT rounding, chroma subsampling) |
| WebP | libwebp VBR quality target | `100` is still **lossy** |
| AVIF | AV1 quantizer (0–63, inverted) | `100` selects quantizer `0` — **mathematically lossless** |
| JXL | libjxl `distance` (inverted) | `100` selects distance `0` — **mathematically lossless** |

Because the curves differ, `quality 80` in JPEG and `quality 80` in JXL are two different things that merely share an integer. At comparable perceptual quality, typical file sizes rank roughly:

```
JXL ≈ AVIF  <  WebP  <  JPEG
```

This is why each format has its own option, and why `jxlQuality` deliberately does **not** fall back to `jpegQuality`.

> `null` means "apply no quality setting" and leaves the codec's own default in place — it is **not** the same as a quality of `0`. On GD this maps to the `-1` sentinel that `imagejpeg()` / `imagewebp()` / `imageavif()` accept; on Imagick the `setImageCompressionQuality()` call is skipped entirely.

---

## Quality Presets

Because the raw numbers aren't interchangeable, PHPThumb ships with a small table of **presets** that pick per-codec values which produce a comparable *perceptual* result. Apply one with a single call:

```php
$thumb = new PHPThumb\Imagick('photo.jpg');
$thumb->setQualityPreset('medium')
      ->resize(800, 0)
      ->save('photo.webp');
```

Or via the constructor / `setOptions()`:

```php
$thumb = new PHPThumb\Imagick('photo.jpg', ['qualityPreset' => 'medium']);
```

The preset is expanded **before** your own options, so an explicit per-format value always wins:

```php
$thumb = new PHPThumb\Imagick('photo.jpg', [
    'qualityPreset' => 'low',
    'jpegQuality'   => 92,   // overrides the preset for JPEG only
]);
```

### Available presets

| Preset | jpeg | webp | avif | jxl | Meaning |
|--------|------|------|------|-----|---------|
| `off` | `null` | `null` | `null` | `null` | No quality applied; each codec's own default |
| `max` | 100 | 100 | 100 | 100 | Maximum quality each codec offers |
| `high` | 90 | 88 | 65 | 90 | Visually near-lossless |
| `medium` | 80 | 78 | 50 | 78 | Good quality; typical for web delivery |
| `low` | 65 | 62 | 35 | 62 | Aggressive compression; small files |

Notes:

- The top tier is named **`max`**, not `lossless` — JPEG and WebP remain lossy at their maximum quality; only AVIF and JXL can reach true lossless.
- GD has no JPEG XL codec, so presets leave `jxlQuality` untouched on the GD backend.
- Preset names are matched case-insensitively after trimming (`'MAX'`, `' Max '` both work). An unknown name throws `\InvalidArgumentException`.

### Inspecting the table

```php
PHPThumb\PHPThumb::getQualityPresets();
// ['off', 'max', 'high', 'medium', 'low']

PHPThumb\PHPThumb::getQualityPreset('high');
// ['jpeg' => 90, 'webp' => 88, 'avif' => 65, 'jxl' => 90]
```

### Tuning it yourself

The preset values are starting points, not gospel — validate them against your own content. Two things shift the result significantly at an identical quality number:

- **Encoder effort/speed** — AVIF and JXL can be tuned with `heic:speed` / `jxl:effort` defines; slower encoding usually means smaller files.
- **Chroma subsampling** — affects JPEG and WebP heavily at lower qualities, independently of the quality number.

To measure on your build and content, compare against a lossless PNG reference (not the source JPEG, or you'll measure the source JPEG's own artifacts):

```bash
SRC=reference.png

for fmt in jpg webp avif jxl; do
    echo "=== $fmt ==="
    for q in 40 50 60 70 80 90 100; do
        magick "$SRC" -quality "$q" "/tmp/t.$fmt" 2>/dev/null || continue
        size=$(stat -c%s "/tmp/t.$fmt")
        ssim=$(magick compare -metric SSIM "$SRC" "/tmp/t.$fmt" null: 2>&1 | awk '{print $1}')
        printf "  q=%-4s %9d bytes   SSIM=%s\n" "$q" "$size" "$ssim"
    done
done
```

Then pick, for each format, the lowest quality whose SSIM still clears your threshold (e.g. `0.98`). Those are your real per-format constants — set them with the individual quality options.

---

## Examples

The `examples/` directory contains runnable demos for every feature. Most are implemented with **both backends** — switch the `$backend` class-constant line near the top of any example to swap between them.

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

// Sharpen at the configured default
(new PHPThumb\GD('photo.jpg', ['sharpenAmount' => 70]))
    ->resize(400, 0)
    ->sharpen()
    ->save('crisp.jpg');

// Add a frame
(new PHPThumb\GD('photo.jpg'))
    ->resize(400, 0)
    ->border(6, '#000000')
    ->save('framed.jpg');

// Brighten + add a watermark-style text caption
(new PHPThumb\GD('photo.jpg'))
    ->gamma(1.3)
    ->text('© PHPThumb', 'bottom-right', [
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

// Apply a quality preset (both backends)
(new PHPThumb\GD('photo.jpg'))
    ->setQualityPreset('medium')
    ->resize(800, 0)
    ->save('preset.webp', 'WEBP');

// Chain everything
(new PHPThumb\GD('photo.jpg'))
    ->autoOrient()
    ->resize(0, 800)
    ->cropFromCenter(800, 800)
    ->sharpen()
    ->text('Final', 'bottom-right', ['color' => '#FFFFFF', 'shadow' => ['enabled' => true]])
    ->save('final.jpg', 'JPEG');

// Multi-line caption with right alignment, custom line spacing, and a background pill
(new PHPThumb\GD('photo.jpg'))
    ->resize(800, 0)
    ->text("© PHPThumb\n© 2026", 'bottom-right', [
        'size'       => 14,
        'color'      => '#FFFFFF',
        'align'      => 'right',
        'lineHeight' => 1.4,
        'background' => ['enabled' => true, 'color' => '#000000', 'padding' => 8, 'alpha' => 60],
    ])
    ->save('captioned-multi.jpg', 'JPEG');

// Stylized watermark: large rotated text with stroke + shadow + background
(new PHPThumb\GD('photo.jpg'))
    ->text('SALE', 'center', [
        'size'   => 48,
        'color'  => '#FF3300',
        'angle'  => -15,       // 15° clockwise on both backends
        'shadow' => ['enabled' => true, 'color' => '#000000', 'offsetX' => 3, 'offsetY' => 3],
        'stroke' => ['enabled' => true, 'color' => '#FFFFFF', 'width' => 2],
    ])
    ->save('sale-watermark.jpg', 'JPEG');

// Text with explicit fontconfig family name (no path needed on Linux/macOS)
(new PHPThumb\GD('photo.jpg'))
    ->text('Welcome', 'center', [
        'font'  => 'DejaVu Sans',   // resolved via fc-match
        'size'  => 36,
        'color' => '#FFFFFF',
    ])
    ->show();

// Load the raw bytes of an image (both backends)
$bytes = file_get_contents('photo.jpg');
(new PHPThumb\GD($bytes))->resize(400, 0)->save('from-bytes.jpg');
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

// Imagick: grayscale via the chainable wrapper (works on both backends)
(new PHPThumb\Imagick('photo.jpg'))
    ->grayscale()
    ->save('grayscale.jpg');

// Imagick: a raw spatial filter via PHPThumb's own constants
(new PHPThumb\Imagick('photo.jpg'))
    ->imageFilter(\PHPThumb\Imagick::FILTER_SOBEL)
    ->save('edges.jpg');

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

// Imagick: multi-line text with right alignment and background pill
(new PHPThumb\Imagick('photo.jpg'))
    ->resize(800, 0)
    ->text("© PHPThumb\n© 2026", 'bottom-right', [
        'size'       => 14,
        'color'      => '#FFFFFF',
        'align'      => 'right',
        'lineHeight' => 1.4,
        'background' => ['enabled' => true, 'color' => '#000000', 'padding' => 8, 'alpha' => 60],
    ])
    ->save('captioned-multi.jpg', 'JPEG');

// Imagick: rotated TTF text with stroke (built-in fonts can't be stroked reliably)
(new PHPThumb\Imagick('photo.jpg'))
    ->text('SALE', 'center', [
        'font'   => '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        'size'   => 48,
        'color'  => '#FF3300',
        'angle'  => -15,
        'shadow' => ['enabled' => true, 'color' => '#000000', 'offsetX' => 3, 'offsetY' => 3],
        'stroke' => ['enabled' => true, 'color' => '#FFFFFF', 'width' => 2],
    ])
    ->show();

// Imagick: write JPEG XL (Imagick-only format)
(new PHPThumb\Imagick('photo.jpg'))
    ->setQualityPreset('high')
    ->resize(1200, 0)
    ->save('photo.jxl', 'JXL');

// Imagick: JXL with an explicit per-format quality (independent of jpegQuality)
(new PHPThumb\Imagick('photo.jpg', ['jxlQuality' => 78]))
    ->save('photo.jxl', 'JXL');
```

Run any example directly:

```bash
php examples/resize_basic.php
```

To run the Imagick variant, edit the `$backend` line at the top of the example (or script the substitution — see the [[Examples]] page).

---

## Documentation

Full documentation — including detailed guides, tutorials, and plugin recipes — is available on the project wiki:

📚 **[Documentation](https://github.com/PHPThumb/PHPThumb/wiki)**

💬 **[Discussions](https://github.com/PHPThumb/PHPThumb/discussions)** — Got questions, comments, or feedback? This is the place to visit.

---

## Testing

PHPThumb ships with a [PHPUnit test suite](https://github.com/PHPThumb/PHPThumb/wiki/Tests) under `tests/`. Tests are split by backend and feature area.

```bash
composer install
vendor/bin/phpunit
```

Test fixtures live in `tests/resources/`.

### Backend-specific test selection

Run only the GD tests:

```bash
./vendor/bin/phpunit --testsuite=GD
```

Run only the Imagick tests:

```bash
./vendor/bin/phpunit --testsuite=Imagick
```

Run only the backend-agnostic tests (the abstract `PHPThumb` base class — useful on a host with neither extension installed):

```bash
./vendor/bin/phpunit --testsuite=Core
```

Imagick tests skip automatically if `ext-imagick` is not loaded.

Network-dependent tests (e.g. loading remote images from URLs) are gated behind the `RUN_NETWORK_TESTS=1` environment variable:

```bash
RUN_NETWORK_TESTS=1 vendor/bin/phpunit tests/PHPThumb/Tests/ImagickRemoteImageTest.php
```

Format-coverage tests for BMP, HEIC and TIFF skip silently if the corresponding fixture file is absent. The **JPEG XL** tests do not: they generate a JXL fixture at runtime from the bundled JPEG, so the codec paths are exercised on any build with a `libjxl`-enabled ImageMagick — and skip cleanly on a build without one.

### The EXIF orientation fixture

The orientation tests need a JPEG that actually carries an EXIF `Orientation` tag. If it is missing or was generated without the tag, regenerate it:

```bash
php tests/resources/generate_exif_fixture.php
```

The generator writes the EXIF block by hand (a minimal APP1/TIFF structure), so neither `ext-imagick` nor `exiftool` is required. The orientation tests also rebuild the fixture on the fly when it is absent.

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
- Contributors listed on [GitHub](https://github.com/PHPThumb/PHPThumb/graphs/contributors)
<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\GD;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GDTextTest extends TestCase
{
    protected GD $thumb;

    protected function setUp(): void
    {
        $this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
    }

    public function testTextReturnsSelf(): void
    {
        $result = $this->thumb->text('Hello');
        self::assertInstanceOf(GD::class, $result);
    }

    public function testTextEmptyIsNoOp(): void
    {
        $before = imagecolorat($this->thumb->getOldImage(), 100, 100);
        $this->thumb->text('');
        $after = imagecolorat($this->thumb->getOldImage(), 100, 100);
        self::assertSame($before, $after);
    }

    public function testTextBuiltInFontFallbackRenders(): void
    {
        $this->thumb->text('Hello', 'center', [
            'size'  => 5,
            'color' => '#FFFFFF',
            'font'  => null,
        ]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextWithTtfRenders(): void
    {
        $ttf = $this->resolveFontForTest();
        if ($ttf === null) {
            $this->markTestSkipped(
                'No system TTF available — install fonts-dejavu or similar to enable this test'
                );
        }

        $this->thumb->text('Hello', 'center', [
            'font'  => $ttf,
            'size'  => 24,
            'color' => '#FFFFFF',
        ]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    #[DataProvider('positionProvider')]
    public function testTextAcceptsPositions(string $position): void
    {
        $this->thumb->text('X', $position, ['color' => '#FFFFFF']);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public static function positionProvider(): array
    {
        return [
            'top-left'      => ['top-left'],
            'top-right'     => ['top-right'],
            'top'           => ['top'],
            'top-center'    => ['top-center'],
            'bottom-left'   => ['bottom-left'],
            'bottom-right'  => ['bottom-right'],
            'bottom'        => ['bottom'],
            'bottom-center' => ['bottom-center'],
            'left'          => ['left'],
            'right'         => ['right'],
            'center-left'   => ['center-left'],
            'center-right'  => ['center-right'],
            'center'        => ['center'],
            'northwest'     => ['northwest'],
            'northeast'     => ['northeast'],
            'southwest'     => ['southwest'],
            'southeast'     => ['southeast'],
            'north'         => ['north'],
            'south'         => ['south'],
            'west'          => ['west'],
            'east'          => ['east'],
        ];
    }

    public function testTextUnknownPositionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->text('X', 'diagonal-corner');
    }

    public function testTextAcceptsColorArray(): void
    {
        $this->thumb->text('X', 'center', ['color' => [255, 0, 0]]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextAcceptsHexShort(): void
    {
        $this->thumb->text('X', 'center', ['color' => '#f00']);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextAcceptsHexWithoutHash(): void
    {
        $this->thumb->text('X', 'center', ['color' => 'FF0000']);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextInvalidColorThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->thumb->text('X', 'center', ['color' => '#zzzzzz']);
    }

    public function testTextShadow(): void
    {
        $ttf = $this->resolveFontForTest();
        if ($ttf === null) {
            $this->markTestSkipped('No system TTF available');
        }

        $this->thumb->text('X', 'center', [
            'font'   => $ttf,
            'size'   => 24,
            'color'  => '#FFFFFF',
            'shadow' => ['enabled' => true, 'color' => '#000000', 'offsetX' => 2, 'offsetY' => 2],
        ]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextStroke(): void
    {
        $ttf = $this->resolveFontForTest();
        if ($ttf === null) {
            $this->markTestSkipped('No system TTF available');
        }

        $this->thumb->text('X', 'center', [
            'font'   => $ttf,
            'size'   => 24,
            'color'  => '#FFFFFF',
            'stroke' => ['enabled' => true, 'color' => '#000000', 'width' => 2],
        ]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextBackground(): void
    {
        $this->thumb->text('Hello', 'center', [
            'size'       => 5,
            'color'      => '#FFFFFF',
            'background' => ['enabled' => true, 'color' => '#000000', 'padding' => 4, 'alpha' => 75],
        ]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->text('Hello', 'bottom-right', [
            'color'  => '#FFFFFF',
            'shadow' => ['enabled' => true],
        ]);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    public function testTextMultiline(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->text("Line 1\nLine 2", 'center', [
            'size'  => 24,
            'font'  => $this->resolveFontForTest(),
        ]);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    public function testTextIsChainable(): void
    {
        $result = $this->thumb
            ->resize(400, 0)
            ->border(10, '#000000')
            ->text('Caption', 'bottom-right', ['color' => '#FFFFFF']);

        self::assertInstanceOf(GD::class, $result);
    }

    // ============================================================
    // NEW TESTS — feature parity and behavioral coverage
    //
    // Pixel-level tests use a synthetic white-background image so we
    // have a known, flat background to compare against. Photo-based
    // tests are unreliable because natural pixel variation interferes
    // with threshold-based assertions.
    // ============================================================

    public function testTextRendersPixelsAtExpectedLocation(): void
    {
        $thumb = $this->makeBlankThumb();
        $thumb->text('HELLO', 'center', [
            'size'  => 48,
            'color' => '#FF0000',
            'font'  => $this->resolveFontForTest(),
        ]);

        $img = $thumb->getOldImage();
        $found_red = false;

        for ($y = 200; $y <= 300 && !$found_red; $y++) {
            for ($x = 150; $x <= 350 && !$found_red; $x++) {
                $rgba = $this->samplePixelRgba($img, $x, $y);
                if ($rgba['r'] > 200 && $rgba['g'] < 60 && $rgba['b'] < 60) {
                    $found_red = true;
                }
            }
        }

        self::assertTrue($found_red,
            'Expected to find at least one red pixel in the central text region');
    }

    public function testTextMultilineHasNoOverlap(): void
    {
        $thumb = $this->makeBlankThumb();
        $thumb->text("Line 1\nLine 2\nLine 3", 'center', [
            'size'       => 32,
            'color'      => '#000000',
            'lineHeight' => 1.3,
            'font'       => $this->resolveFontForTest(),
        ]);

        $img = $thumb->getOldImage();
        $bg  = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0];

        $max_runs = 0;
        for ($x = 235; $x <= 265; $x++) {
            $runs = 0;
            $in_run = false;
            for ($y = 100; $y < 400; $y++) {
                $pixel = $this->samplePixelRgba($img, $x, $y);
                $is_text = !$this->pixelsClose($pixel, $bg, 30);

                if ($is_text && !$in_run) {
                    $runs++;
                    $in_run = true;
                } elseif (!$is_text && $in_run) {
                    $in_run = false;
                }
            }
            if ($runs > $max_runs) {
                $max_runs = $runs;
            }
        }

        self::assertGreaterThanOrEqual(3, $max_runs,
            "Expected at least 3 text runs across columns, got {$max_runs} — lines may be overlapping");
    }

    public function testTextMultilineAlignRight(): void
    {
        $thumb = $this->makeBlankThumb();
        $thumb->text("Long line one\nShort", 'center', [
            'size'  => 32,
            'color' => '#000000',
            'align' => 'right',
            'font'  => $this->resolveFontForTest(),
        ]);

        $img = $thumb->getOldImage();
        $bg  = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0];

        $line1_left = $this->findLeftmostTextX($img, 200, 250, $bg);
        $line2_left = $this->findLeftmostTextX($img, 270, 320, $bg);

        self::assertGreaterThan($line1_left, $line2_left,
            "Line 2 (shorter) should start further right than line 1 when align=right. " .
            "Got line1_left={$line1_left}, line2_left={$line2_left}");
    }

    public function testTextMultilineAlignLeft(): void
    {
        $thumb = $this->makeBlankThumb();
        $thumb->text("Long line one\nShort", 'center', [
            'size'  => 32,
            'color' => '#000000',
            'align' => 'left',
            'font'  => $this->resolveFontForTest(),
        ]);

        $img = $thumb->getOldImage();
        $bg  = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0];

        $line1_left = $this->findLeftmostTextX($img, 200, 250, $bg);
        $line2_left = $this->findLeftmostTextX($img, 270, 320, $bg);

        self::assertEqualsWithDelta($line1_left, $line2_left, 2,
            "Line 1 and line 2 should start at the same X (within 2px) when align=left. " .
            "Got line1_left={$line1_left}, line2_left={$line2_left}");
    }

    public function testTextMultilineAlignCenter(): void
    {
        $thumb = $this->makeBlankThumb();
        $thumb->text("Long line one\nShort", 'center', [
            'size'  => 32,
            'color' => '#000000',
            'align' => 'center',
            'font'  => $this->resolveFontForTest(),
        ]);

        $img = $thumb->getOldImage();
        $bg  = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0];

        $line1_left  = $this->findLeftmostTextX($img, 200, 250, $bg);
        $line2_left  = $this->findLeftmostTextX($img, 270, 320, $bg);
        $line1_right = $this->findRightmostTextX($img, 200, 250, $bg);
        $line2_right = $this->findRightmostTextX($img, 270, 320, $bg);

        $line1_center = ($line1_left + $line1_right) / 2;
        $line2_center = ($line2_left + $line2_right) / 2;

        self::assertEqualsWithDelta($line1_center, $line2_center, 2.0,
            "Lines should have the same horizontal center (within 2px) when align=center");
    }

    public function testTextRotationProducesDifferentOutput(): void
    {
        $thumb_a = $this->makeBlankThumb();
        $thumb_a->text('HELLO', 'center', [
            'size'  => 48,
            'color' => '#000000',
            'angle' => 15,
            'font'  => $this->resolveFontForTest(),
        ]);

        $thumb_b = $this->makeBlankThumb();
        $thumb_b->text('HELLO', 'center', [
            'size'  => 48,
            'color' => '#000000',
            'angle' => -15,
            'font'  => $this->resolveFontForTest(),
        ]);

        $hash_a = $this->hashPixels($thumb_a->getOldImage(), 100, 400, 100, 400);
        $hash_b = $this->hashPixels($thumb_b->getOldImage(), 100, 400, 100, 400);

        self::assertNotSame($hash_a, $hash_b,
            '+15° and -15° rotations should produce different pixel patterns');
    }

    public function testStrokeAddsPixelsOutsideGlyphBbox(): void
    {
        $thumb_a = $this->makeBlankThumb();
        $thumb_a->text('HELLO', 'center', [
            'size'  => 48,
            'color' => '#000000',
            'font'  => $this->resolveFontForTest(),
        ]);

        $thumb_b = $this->makeBlankThumb();
        $thumb_b->text('HELLO', 'center', [
            'size'   => 48,
            'color'  => '#000000',
            'stroke' => ['enabled' => true, 'color' => '#000000', 'width' => 3],
            'font'   => $this->resolveFontForTest(),
        ]);

        $bg = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0];

        $count_a = $this->countNonBackgroundPixels(
            $thumb_a->getOldImage(), 150, 350, 200, 300, $bg
        );
        $count_b = $this->countNonBackgroundPixels(
            $thumb_b->getOldImage(), 150, 350, 200, 300, $bg
        );

        self::assertGreaterThan((int) ($count_a * 1.3), $count_b,
            "Stroked image should have at least 1.3× more inked pixels than un-stroked. " .
            "Got stroked={$count_b}, unstroked={$count_a}");
    }

    public function testShadowAppearsAtOffsetPosition(): void
    {
        $thumb = $this->makeBlankThumb();
        $thumb->text('HELLO', 'center', [
            'size'   => 48,
            'color'  => '#FFFFFF',
            'shadow' => ['enabled' => true, 'color' => '#000000', 'offsetX' => 10, 'offsetY' => 10],
            'font'   => $this->resolveFontForTest(),
        ]);

        $img = $thumb->getOldImage();

        $found_dark = false;
        for ($dy = 5; $dy <= 30 && !$found_dark; $dy++) {
            for ($dx = 5; $dx <= 30 && !$found_dark; $dx++) {
                $rgba = $this->samplePixelRgba($img, 250 + $dx, 250 + $dy);
                if ($rgba['r'] < 50 && $rgba['g'] < 50 && $rgba['b'] < 50) {
                    $found_dark = true;
                }
            }
        }

        self::assertTrue($found_dark,
            'Expected to find a dark shadow pixel offset from main text center');
    }

    public function testBackgroundPillFillsRegion(): void
    {
        $thumb = $this->makeBlankThumb();
        $thumb->text('HELLO', 'center', [
            'size'       => 48,
            'color'      => '#000000',
            'background' => ['enabled' => true, 'color' => '#808080', 'padding' => 15, 'alpha' => 75],
            'font'       => $this->resolveFontForTest(),
        ]);

        $img = $thumb->getOldImage();

        $samples = [
            [175, 215],
            [325, 215],
            [175, 285],
            [325, 285],
        ];

        $found_pill_pixel = false;
        foreach ($samples as [$x, $y]) {
            $rgba = $this->samplePixelRgba($img, $x, $y);
            if ($rgba['r'] > 100 && $rgba['r'] < 220) {
                $found_pill_pixel = true;
                break;
            }
        }

        self::assertTrue($found_pill_pixel,
            'Expected to find a mid-gray pill pixel in one of the four pill corners');
    }

    public function testLineHeightChangesSpacing(): void
    {
        $thumb_tight = $this->makeBlankThumb();
        $thumb_tight->text("AB\nCD", 'center', [
            'size'       => 48,
            'color'      => '#000000',
            'lineHeight' => 0.5,
            'font'       => $this->resolveFontForTest(),
        ]);

        $thumb_loose = $this->makeBlankThumb();
        $thumb_loose->text("AB\nCD", 'center', [
            'size'       => 48,
            'color'      => '#000000',
            'lineHeight' => 3.0,
            'font'       => $this->resolveFontForTest(),
        ]);

        $bg = ['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0];

        $extent_tight = $this->findTextVerticalExtent($thumb_tight->getOldImage(), $bg);
        $extent_loose = $this->findTextVerticalExtent($thumb_loose->getOldImage(), $bg);

        self::assertGreaterThan($extent_tight, $extent_loose,
            "larger lineHeight should produce a taller text block. " .
            "Got tight={$extent_tight}px, loose={$extent_loose}px");
    }

    public function testAlphaChangesOpacity(): void
    {
        $thumb_solid = $this->makeBlankThumb();
        $thumb_solid->text('X', 'center', [
            'size'  => 96,
            'color' => '#000000',
            'alpha' => 100,
            'font'  => $this->resolveFontForTest(),
        ]);

        $thumb_trans = $this->makeBlankThumb();
        $thumb_trans->text('X', 'center', [
            'size'  => 96,
            'color' => '#000000',
            'alpha' => 20,
            'font'  => $this->resolveFontForTest(),
        ]);

        $solid_darkest = 255;
        $trans_darkest = 255;
        for ($dy = -40; $dy <= 40; $dy += 2) {
            for ($dx = -40; $dx <= 40; $dx += 2) {
                $rgba = $this->samplePixelRgba(
                    $thumb_solid->getOldImage(), 250 + $dx, 250 + $dy
                );
                if ($rgba['r'] < $solid_darkest) $solid_darkest = $rgba['r'];
                $rgba = $this->samplePixelRgba(
                    $thumb_trans->getOldImage(), 250 + $dx, 250 + $dy
                );
                if ($rgba['r'] < $trans_darkest) $trans_darkest = $rgba['r'];
            }
        }

        self::assertLessThan(30, $solid_darkest,
            "alpha=100 should produce near-pure black pixels. Got darkest R={$solid_darkest}");
        self::assertGreaterThan(150, $trans_darkest,
            "alpha=20 should produce light gray pixels (around R=200). Got darkest R={$trans_darkest}");
        self::assertLessThan($trans_darkest, $solid_darkest,
            "alpha=100 darkest should be less than alpha=20 darkest");
    }

    public function testOffsetShiftsTextPosition(): void
    {
        $thumb = $this->makeBlankThumb();
        $thumb->text('X', 'top-left', [
            'size'    => 48,
            'color'   => '#000000',
            'offsetX' => 50,
            'offsetY' => 50,
            'font'    => $this->resolveFontForTest(),
        ]);

        $img = $thumb->getOldImage();

        $topmost_y = null;
        for ($y = 45; $y < 150; $y++) {
            for ($x = 50; $x < 200; $x++) {
                $rgba = $this->samplePixelRgba($img, $x, $y);
                if ($rgba['r'] < 50 && $rgba['g'] < 50 && $rgba['b'] < 50) {
                    $topmost_y = $y;
                    break 2;
                }
            }
        }

        self::assertNotNull($topmost_y, 'Expected to find a text pixel in the offset region');
        self::assertGreaterThan(45, $topmost_y,
            "Text with offsetY=50 should have its topmost pixel below y=45. Got y={$topmost_y}");
    }

    /**
     * Bad font path should emit E_USER_WARNING before falling back to
     * the built-in GD font.
     */
    public function testBadFontPathEmitsWarning(): void
    {
    	$caught = false;
    	$warning_message = '';

    	// Catch ALL levels — the warning could be E_USER_WARNING from
    	// resolveGdFont() or E_USER_WARNING from the imagettftext()
    	// missing check. Both are valid signals.
    	set_error_handler(function ($errno, $errstr) use (&$caught, &$warning_message) {
    		$caught = true;
    		$warning_message = $errstr;
    		return true;
    	});

    		try {
    			$this->makeBlankThumb()->text('X', 'center', [
    				'font'  => '/nonexistent/path/to/font.ttf',
    				'size'  => 24,
    				'color' => '#000000',
    			]);
    		} finally {
    			restore_error_handler();
    		}

    		self::assertTrue($caught,
    			'Expected E_USER_WARNING when font path is invalid. Last message: ' . $warning_message);
    }

    /**
     * Font family name (no slashes, no extension) resolves via
     * fontconfig. Skipped if fontconfig isn't available.
     */
    public function testFontFamilyNameResolution(): void
    {
        $fc = trim((string) @shell_exec('command -v fc-match 2>/dev/null'));
        if ($fc === '') {
            $this->markTestSkipped('fontconfig (fc-match) not available');
        }

        $thumb = $this->makeBlankThumb();
        $thumb->text('X', 'center', [
            'font'  => 'sans-serif',
            'size'  => 24,
            'color' => '#000000',
        ]);
        self::assertSame(500, $thumb->getCurrentDimensions()['width']);
    }

    /**
     * Font = null resolves to fontconfig default (or built-in fallback).
     */
    public function testNullFontDoesNotThrow(): void
    {
        $thumb = $this->makeBlankThumb();
        $thumb->text('X', 'center', [
            'font'  => null,
            'size'  => 24,
            'color' => '#000000',
        ]);
        self::assertSame(500, $thumb->getCurrentDimensions()['width']);
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Returns a fresh GD thumb with a 500x500 solid white canvas.
     */
    private function makeBlankThumb(): GD
    {
        $path = $this->createBlankTestImage();
        return new GD($path);
    }

    /**
     * Creates a 500x500 solid-white PNG in the system temp directory
     * and returns its path. Cached so we don't recreate on every call.
     */
    private function createBlankTestImage(): string
    {
        static $path = null;
        if ($path !== null && is_file($path)) {
            return $path;
        }

        $path = sys_get_temp_dir() . '/phpthumb_blank_test_gd.png';

        $img = imagecreatetruecolor(500, 500);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, 500, 500, $white);
        imagepng($img, $path);

        return $path;
    }

    /**
     * Sample a pixel and return [r, g, b, a] as integers 0-255.
     * GD: uses imagecolorat() + imagecolorsforindex().
     * Coordinates are clamped to image bounds to avoid PHP notices.
     */
    private function samplePixelRgba(\GdImage $img, int $x, int $y): array
    {
        $w = imagesx($img);
        $h = imagesy($img);

        if ($x < 0 || $x >= $w || $y < 0 || $y >= $h) {
            return ['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0];
        }

        $color = imagecolorat($img, $x, $y);
        $rgba  = imagecolorsforindex($img, $color);
        return [
            'r' => (int) $rgba['red'],
            'g' => (int) $rgba['green'],
            'b' => (int) $rgba['blue'],
            'a' => (int) ($rgba['alpha'] ?? 0),
        ];
    }

    /**
     * True if two pixels are within $tolerance on every channel.
     */
    private function pixelsClose(array $a, array $b, int $tolerance = 30): bool
    {
        return abs($a['r'] - $b['r']) <= $tolerance
            && abs($a['g'] - $b['g']) <= $tolerance
            && abs($a['b'] - $b['b']) <= $tolerance;
    }

    /**
     * Find the leftmost X with a non-background pixel in the given
     * Y band.
     */
    private function findLeftmostTextX(\GdImage $img, int $y_min, int $y_max, array $bg): int
    {
        for ($x = 0; $x < 500; $x++) {
            for ($y = $y_min; $y <= $y_max; $y++) {
                $pixel = $this->samplePixelRgba($img, $x, $y);
                if (!$this->pixelsClose($pixel, $bg, 30)) {
                    return $x;
                }
            }
        }
        self::fail("No text pixels found in y range {$y_min}-{$y_max}");
    }

    /**
     * Find the rightmost X with a non-background pixel.
     */
    private function findRightmostTextX(\GdImage $img, int $y_min, int $y_max, array $bg): int
    {
        for ($x = 499; $x >= 0; $x--) {
            for ($y = $y_min; $y <= $y_max; $y++) {
                $pixel = $this->samplePixelRgba($img, $x, $y);
                if (!$this->pixelsClose($pixel, $bg, 30)) {
                    return $x;
                }
            }
        }
        self::fail("No text pixels found in y range {$y_min}-{$y_max}");
    }

    /**
     * Count non-background pixels in the given rectangle.
     */
    private function countNonBackgroundPixels(
        \GdImage $img, int $x_min, int $x_max, int $y_min, int $y_max, array $bg
    ): int {
        $count = 0;
        for ($y = $y_min; $y <= $y_max; $y++) {
            for ($x = $x_min; $x <= $x_max; $x++) {
                $pixel = $this->samplePixelRgba($img, $x, $y);
                if (!$this->pixelsClose($pixel, $bg, 30)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * Find the vertical extent (bottommost_y - topmost_y) of the text block.
     */
    private function findTextVerticalExtent(\GdImage $img, array $bg): int
    {
        $top = null;
        $bot = null;

        for ($y = 0; $y < 500; $y++) {
            for ($x = 0; $x < 500; $x++) {
                $pixel = $this->samplePixelRgba($img, $x, $y);
                if (!$this->pixelsClose($pixel, $bg, 30)) {
                    if ($top === null) $top = $y;
                    $bot = $y;
                    break;
                }
            }
        }

        if ($top === null || $bot === null) {
            self::fail("No text pixels found in image");
        }

        return $bot - $top;
    }

    /**
     * Hash a rectangular region of pixels for quick equality comparison.
     */
    private function hashPixels(\GdImage $img, int $x_min, int $x_max, int $y_min, int $y_max): string
    {
        $bytes = '';
        for ($y = $y_min; $y <= $y_max; $y++) {
            for ($x = $x_min; $x <= $x_max; $x++) {
                $pixel = $this->samplePixelRgba($img, $x, $y);
                $bytes .= chr($pixel['r']) . chr($pixel['g']) . chr($pixel['b']);
            }
        }
        return md5($bytes);
    }

    /**
     * Returns the resolved TTF path or null.
     */
    private function resolveFontForTest(): ?string
    {
        $ttf = $this->findSystemTtf();
        if ($ttf !== null) {
            return $ttf;
        }

        $fc = trim((string) @shell_exec('command -v fc-match 2>/dev/null'));
        if ($fc !== '') {
            $path = trim((string) @shell_exec('fc-match -f "%{file}" 2>/dev/null'));
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return string|null
     */
    private function findSystemTtf(): ?string
    {
        $candidates = [
            '/usr/share/fonts/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
            '/Library/Fonts/Arial.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            'C:\\Windows\\Fonts\\arial.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}

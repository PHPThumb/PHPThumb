<?php
namespace PHPThumb\Tests;

use PHPThumb\GD;
use PHPUnit\Framework\TestCase;

class GDAutoOrientTest extends TestCase
{
    protected GD $thumb;

    protected function setUp(): void
    {
        $this->thumb = new GD(__DIR__ . '/../../resources/test.jpg');
    }

    public function testAutoOrientReturnsSelf(): void
    {
        $result = $this->thumb->autoOrient();
        self::assertInstanceOf(GD::class, $result);
    }

    public function testAutoOrientIsNoOpWithoutExif(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $result = $this->thumb->autoOrient();

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
        self::assertInstanceOf(GD::class, $result);
    }

    public function testAutoOrientIsNoOpForNonJpeg(): void
    {
        $png = new GD(__DIR__ . '/../../resources/test.png');
        $result = $png->autoOrient();
        self::assertInstanceOf(GD::class, $result);
        self::assertSame(500, $png->getCurrentDimensions()['width']);
        self::assertSame(375, $png->getCurrentDimensions()['height']);
    }

    public function testAutoOrientRotatesExifSix(): void
    {
        $fixture = __DIR__ . '/../../resources/exif_orientation.jpg';

        if (!file_exists($fixture)) {
        	$this->markTestSkipped(
        		'exif_orientation.jpg fixture missing — run ' .
        		'tests/resources/generate_exif_fixture.php'
        		);
        }

        if (!function_exists('exif_read_data')) {
        	$this->markTestSkipped('ext-exif is not available');
        }

        $exif = @exif_read_data($fixture);

        // The fallback fixture writer has no EXIF-injection tooling, so the
        // file may exist without an Orientation tag. Skip rather than fail.
        if (!is_array($exif) || !isset($exif['Orientation']) || (int) $exif['Orientation'] !== 6) {
        	$this->markTestSkipped(
        		'fixture lacks EXIF Orientation = 6 — regenerate with ext-imagick ' .
        		'or exiftool'
        		);
        }
        self::assertSame(6, (int)($exif['Orientation'] ?? 0), 'fixture must have Orientation = 6');

        $img = new GD($fixture);
        self::assertSame(400, $img->getCurrentDimensions()['width']);
        self::assertSame(200, $img->getCurrentDimensions()['height']);

        $img->autoOrient();

        self::assertSame(200, $img->getCurrentDimensions()['width']);
        self::assertSame(400, $img->getCurrentDimensions()['height']);
    }

    public function testAutoOrientIsChainable(): void
    {
        $result = $this->thumb
            ->autoOrient()
            ->resize(200, 0)
            ->sharpen(50);

        self::assertInstanceOf(GD::class, $result);
        self::assertSame(200, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testAutoOrientIsIdempotent(): void
    {
        $this->thumb->autoOrient();
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->autoOrient();

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }
}

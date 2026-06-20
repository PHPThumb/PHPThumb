<?php
namespace PHPThumb\Tests;

use PHPThumb\Imagick;
use PHPUnit\Framework\TestCase;

class ImagickAutoOrientTest extends TestCase
{
    protected Imagick $thumb;

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        $this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
    }

    public function testAutoOrientReturnsSelf(): void
    {
        self::assertInstanceOf(Imagick::class, $this->thumb->autoOrient());
    }

    public function testAutoOrientIsNoOpWithoutExif(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $result = $this->thumb->autoOrient();

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
        self::assertInstanceOf(Imagick::class, $result);
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

    	$img = new Imagick($fixture);
    	$orientation = $img->getOldImage()->getImageOrientation();

    	// The fallback fixture writer has no EXIF-injection tooling, so the
    	// file may exist without an Orientation tag set to RIGHTTOP. Skip
    	// rather than fail in that case.
    	if ($orientation !== \Imagick::ORIENTATION_RIGHTTOP) {
    		$this->markTestSkipped(
    			'fixture lacks EXIF Orientation = 6 — regenerate with ext-imagick ' .
    			'or exiftool'
    			);
    	}

    	$img->autoOrient();

    	self::assertSame(
    		\Imagick::ORIENTATION_TOPLEFT,
    		$img->getOldImage()->getImageOrientation()
    		);
    	self::assertSame(200, $img->getCurrentDimensions()['width']);
    	self::assertSame(400, $img->getCurrentDimensions()['height']);
    }

    public function testAutoOrientIsChainable(): void
    {
        $result = $this->thumb
            ->autoOrient()
            ->resize(200, 0)
            ->sharpen(50);

        self::assertInstanceOf(Imagick::class, $result);
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

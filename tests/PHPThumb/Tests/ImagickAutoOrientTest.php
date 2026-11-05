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

    /**
     * Uses a genuinely tagged fixture: the previous version used a JPEG
     * with no EXIF at all, so the assertion was vacuous.
     */
    public function testAutoOrientIsIdempotent(): void
    {
    	$fixture = $this->resolveExifFixture();

    	if ($fixture === null) {
    		$this->markTestSkipped(
    			'No usable EXIF fixture could be produced — ext-gd is required'
    			);
    	}

    	$img = new \PHPThumb\Imagick($fixture);

    	$img->autoOrient();

    	$w = $img->getCurrentDimensions()['width'];
    	$h = $img->getCurrentDimensions()['height'];

    	self::assertSame(200, $w);
    	self::assertSame(400, $h);

    	$img->autoOrient();

    	self::assertSame($w, $img->getCurrentDimensions()['width']);
    	self::assertSame($h, $img->getCurrentDimensions()['height']);
    }

    private function resolveExifFixture(): ?string
    {
    	require_once __DIR__ . '/../../resources/exif_writer.php';

    	$fixture = __DIR__ . '/../../resources/exif_orientation.jpg';

    	if (file_exists($fixture) && \ExifFixtureWriter::orientation($fixture) === 6) {
    		return $fixture;
    	}

    	$path = sys_get_temp_dir()
    	. '/phpthumb_exif_orientation_' . getmypid() . '.jpg';

    	try {
    		\ExifFixtureWriter::write($path, 6);
    	} catch (\Throwable $e) {
    		return null;
    	}

    	return $path;
    }
}

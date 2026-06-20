<?php
namespace PHPThumb\Tests;

use InvalidArgumentException;
use PHPThumb\Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImagickTextTest extends TestCase
{
    protected Imagick $thumb;

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        $this->thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');
    }

    public function testTextReturnsSelf(): void
    {
        self::assertInstanceOf(Imagick::class, $this->thumb->text('Hello'));
    }

    public function testTextEmptyIsNoOp(): void
    {
        $before = $this->thumb->getOldImage()->getImagePixelColor(100, 100)->getColor();
        $this->thumb->text('');
        $after = $this->thumb->getOldImage()->getImagePixelColor(100, 100)->getColor();
        self::assertSame((int) $before['r'], (int) $after['r']);
    }

    public function testTextBuiltInFontRenders(): void
    {
        $this->thumb->text('Hello', 'center', [
            'size'  => 24,
            'color' => '#FFFFFF',
            'font'  => 'Helvetica',  // Imagick built-in
        ]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextWithTtfRenders(): void
    {
        $ttf = $this->findSystemTtf();
        if ($ttf === null) {
            $this->markTestSkipped('No system TTF available');
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

    public function testTextShadow(): void
    {
        $this->thumb->text('Hello', 'center', [
            'size'   => 24,
            'color'  => '#FFFFFF',
            'shadow' => ['enabled' => true, 'color' => '#000000', 'offsetX' => 2, 'offsetY' => 2],
        ]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextStroke(): void
    {
        $ttf = $this->findSystemTtf();
        if ($ttf === null) {
            $this->markTestSkipped('No system TTF available');
        }

        $this->thumb->text('Hello', 'center', [
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
            'size'       => 24,
            'color'      => '#FFFFFF',
            'background' => ['enabled' => true, 'color' => '#000000', 'padding' => 4, 'alpha' => 75],
        ]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextPreservesDimensions(): void
    {
        $w = $this->thumb->getCurrentDimensions()['width'];
        $h = $this->thumb->getCurrentDimensions()['height'];

        $this->thumb->text('Caption', 'bottom-right', [
            'color'  => '#FFFFFF',
            'shadow' => ['enabled' => true],
        ]);

        self::assertSame($w, $this->thumb->getCurrentDimensions()['width']);
        self::assertSame($h, $this->thumb->getCurrentDimensions()['height']);
    }

    public function testTextMultiline(): void
    {
        $this->thumb->text("Line 1\nLine 2", 'center', ['size' => 24]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextIsChainable(): void
    {
        $result = $this->thumb
            ->resize(400, 0)
            ->border(10, '#000000')
            ->text('Caption', 'bottom-right', ['color' => '#FFFFFF']);

        self::assertInstanceOf(Imagick::class, $result);
    }

    /**
     * @return string|null
     */
    private function findSystemTtf(): ?string
    {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            '/Library/Fonts/Arial.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}

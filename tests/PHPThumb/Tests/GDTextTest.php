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
        $ttf = $this->findSystemTtf();
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
        $ttf = $this->findSystemTtf();
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
        $ttf = $this->findSystemTtf();
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
        $this->thumb->text("Line 1\nLine 2", 'center', ['size' => 5]);
        self::assertSame(500, $this->thumb->getCurrentDimensions()['width']);
    }

    public function testTextIsChainable(): void
    {
        $result = $this->thumb
            ->resize(400, 0)
            ->border(10, '#000000')
            ->text('Caption', 'bottom-right', ['color' => '#FFFFFF']);

        self::assertInstanceOf(GD::class, $result);
    }

    /**
     * @return string|null
     */
    private function findSystemTtf(): ?string
    {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/TTF/DejaVuSans.ttf',
            '/Library/Fonts/Arial.ttf',
            '/System/Library/Fonts/Helvetica.ttd',
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

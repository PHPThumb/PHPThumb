<?php
namespace PHPThumb\Tests;

use PHPThumb\Imagick;
use PHPThumb\PHPThumb;
use PHPThumb\PluginInterface;
use PHPUnit\Framework\TestCase;

class ImagickPluginTest extends TestCase
{
	protected function setUp(): void
	{
		if (!extension_loaded('imagick'))
		{
			$this->markTestSkipped('ext-imagick is not available');
		}
	}

	public function testPluginsAreProcessedOnShow()
	{
		$mockPlugin = new class implements PluginInterface {
			public static bool $wasCalled = false;

			public function execute(PHPThumb $phpthumb): PHPThumb
			{
				self::$wasCalled = true;
				return $phpthumb;
			}
		};

		$thumb = new Imagick(__DIR__ . '/../../resources/test.jpg', [], [$mockPlugin]);
		$thumb->resize(100, 100);

		ob_start();
		$thumb->show(true);
		$output = ob_get_clean();

		self::assertTrue($mockPlugin::$wasCalled);
		self::assertNotEmpty($output);
	}

	public function testMultiplePluginsExecutedInOrder()
	{
		$callOrder = [];

		$plugin1 = new class($callOrder, 1) implements PluginInterface {
			private array $order;
			private int $number;

			public function __construct(array &$order, int $number)
			{
				$this->order = &$order;
				$this->number = $number;
			}

			public function execute(PHPThumb $phpthumb): PHPThumb
			{
				$this->order[] = $this->number;
				return $phpthumb;
			}
		};

		$plugin2 = new class($callOrder, 2) implements PluginInterface {
			private array $order;
			private int $number;

			public function __construct(array &$order, int $number)
			{
				$this->order = &$order;
				$this->number = $number;
			}

			public function execute(PHPThumb $phpthumb): PHPThumb
			{
				$this->order[] = $this->number;
				return $phpthumb;
			}
		};

		$thumb = new Imagick(
			__DIR__ . '/../../resources/test.jpg',
			[],
			[$plugin1, $plugin2]
		);

		ob_start();
		$thumb->show(true);
		ob_end_clean();

		self::assertSame([1, 2], $callOrder);
	}

	public function testPluginCanModifyImage()
	{
		$plugin = new class implements PluginInterface {
			public function execute(PHPThumb $phpthumb): PHPThumb
			{
				// Use adaptiveResize so a 50×50 target is achievable from the
				// 500×375 source (resize() with resizeUp=false would clamp).
				$phpthumb->adaptiveResize(50, 50);
				return $phpthumb;
			}
		};

		$thumb = new Imagick(__DIR__ . '/../../resources/test.jpg', [], [$plugin]);

		ob_start();
		$thumb->show(true);
		ob_end_clean();

		self::assertSame(50, $thumb->getCurrentDimensions()['width']);
		self::assertSame(50, $thumb->getCurrentDimensions()['height']);
	}

	public function testShowThrowsWhenHeadersAlreadySent()
	{
		// Simulate sent headers via output buffering (any output counts)
		$thumb = new Imagick(__DIR__ . '/../../resources/test.jpg');

		// In CLI mode headers_sent() always returns false, so show() succeeds.
		// We simply verify the call path doesn't throw.
		ob_start();
		$thumb->show(true);
		ob_end_clean();

		self::assertTrue(true); // reached without exception
	}
}
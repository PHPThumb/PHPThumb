<?php
namespace PHPThumb\Tests;

use PHPThumb\GD;
use PHPThumb\PHPThumb;
use PHPThumb\PluginInterface;
use PHPUnit\Framework\TestCase;

class GDPluginTest extends TestCase
{
	public function testPluginsAreProcessedOnShow(): void
	{
		$mockPlugin = new class implements PluginInterface {
			public static bool $wasCalled = false;

			public function execute(PHPThumb $phpthumb): PHPThumb
			{
				self::$wasCalled = true;
				return $phpthumb;
			}
		};

		$thumb = new GD(__DIR__ . '/../../resources/test.jpg', [], [$mockPlugin]);

		ob_start();
		$thumb->show(true);
		$output = ob_get_clean();

		self::assertTrue($mockPlugin::$wasCalled);
		self::assertNotEmpty($output);
	}

	public function testMultiplePluginsAreExecutedInOrder(): void
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

		$thumb = new GD(
			__DIR__ . '/../../resources/test.jpg',
			[],
			[$plugin1, $plugin2]
			);

		ob_start();
		$thumb->show(true);
		ob_end_clean();

		self::assertSame([1, 2], $callOrder);
	}

	public function testPluginCanModifyTheImage(): void
	{
		$plugin = new class implements PluginInterface {
			public function execute(PHPThumb $phpthumb): PHPThumb
			{
				$phpthumb->adaptiveResize(50, 50);
				return $phpthumb;
			}
		};

		$thumb = new GD(__DIR__ . '/../../resources/test.jpg', [], [$plugin]);

		ob_start();
		$thumb->show(true);
		ob_end_clean();

		self::assertSame(50, $thumb->getCurrentDimensions()['width']);
		self::assertSame(50, $thumb->getCurrentDimensions()['height']);
	}
}
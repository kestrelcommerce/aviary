<?php

declare(strict_types=1);

namespace Kestrel\Aviary\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PostinstallTest extends TestCase
{
	private string $root;

	protected function setUp(): void
	{
		$this->root = sys_get_temp_dir() . '/aviary-postinstall-' . bin2hex(random_bytes(4));

		mkdir($this->root . '/temp', 0777, true);
		mkdir($this->root . '/destination/vendor/composer', 0777, true);
		mkdir($this->root . '/cwd', 0777, true);

		touch($this->root . '/temp/aviary-autoload.php');
		touch($this->root . '/destination/composer.lock');

		file_put_contents(
			$this->root . '/destination/vendor/composer/autoload_classmap.php',
			<<<'PHP'
				<?php
				$vendorDir = dirname(__DIR__);
				return array(
				    'Composer\\InstalledVersions' => $vendorDir . '/composer/InstalledVersions.php',
				    'Other\\Composer\\InstalledVersions' => $vendorDir . '/other/InstalledVersions.php',
				);
				PHP
		);

		file_put_contents(
			$this->root . '/destination/vendor/composer/autoload_static.php',
			<<<'PHP'
				<?php
				class ComposerStaticInit
				{
				    public static $classMap = array (
				        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
				        'Other\\Composer\\InstalledVersions' => __DIR__ . '/..' . '/other/InstalledVersions.php',
				    );
				}
				PHP
		);
	}

	protected function tearDown(): void
	{
		exec('rm -rf ' . escapeshellarg($this->root));
	}

	#[DataProvider('providePrefixes')]
	public function test_prefixes_installed_versions_classmap_key(string $prefix, string $expectedKey): void
	{
		$this->runPostinstall($prefix);

		$classmap = require $this->root . '/cwd/vendor-scoped/composer/autoload_classmap.php';
		$static   = file_get_contents($this->root . '/cwd/vendor-scoped/composer/autoload_static.php');

		self::assertSame([$expectedKey, 'Other\\Composer\\InstalledVersions'], array_keys($classmap));

		// the static file declares a class, so it is checked as source: keys there carry doubled backslashes
		self::assertStringContainsString("'" . addslashes($expectedKey) . "' =>", $static);
		self::assertStringContainsString("'Other\\\\Composer\\\\InstalledVersions' =>", $static);
		self::assertStringNotContainsString("'Composer\\\\InstalledVersions'", $static);
	}

	public static function providePrefixes(): iterable
	{
		yield 'single segment' => ['CheckoutWC', 'CheckoutWC\\Composer\\InstalledVersions'];
		yield 'multiple segments' => ['Kestrel\\Account_Funds\\Scoped', 'Kestrel\\Account_Funds\\Scoped\\Composer\\InstalledVersions'];
	}

	private function runPostinstall(string $prefix): void
	{
		// mirrors the placeholder substitution in Plugin::execute()
		$script = strtr(
			(string) file_get_contents(__DIR__ . '/../scripts/postinstall.php'),
			[
				'%%source%%'        => $this->root . '/source',
				'%%destination%%'   => $this->root . '/destination',
				'%%cwd%%'           => $this->root . '/cwd',
				'%%composer_lock%%' => 'composer-scoped.lock',
				'%%vendor_scoped%%' => $this->root . '/cwd/vendor-scoped',
				'%%temp%%'          => $this->root . '/temp',
				'%%prefix%%'        => $prefix,
			]
		);

		file_put_contents($this->root . '/temp/postinstall.php', $script);

		exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->root . '/temp/postinstall.php') . ' 2>&1', $output, $exitCode);

		self::assertSame(0, $exitCode, implode("\n", $output));
	}
}

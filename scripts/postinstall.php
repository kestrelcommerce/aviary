<?php
function remove( string $src ): void {
	if (!file_exists($src)) {
		return;
	}

	if (is_file($src)) {
		unlink($src);
		return;
	}

	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ($files as $file_info) {
		$action = ( $file_info->isDir() ? 'rmdir' : 'unlink' );
		$action( $file_info->getRealPath() );
	}

	rmdir($src);
}

function path( ...$parts ): string {

	return join( DIRECTORY_SEPARATOR, $parts );
}

// define variables - these placeholders are replaced in Plugin.php with actual values from the config
$source        = '%%source%%';
$destination   = '%%destination%%';
$cwd           = '%%cwd%%';
$composer_lock = '%%composer_lock%%';
$vendor_scoped = '%%vendor_scoped%%';
$temp          = '%%temp%%';
$prefix        = strtolower( preg_replace( "/[[a-zA-Z0-9]+]/", '', '%%prefix%%' ) );
$namespace_prefix = '%%prefix%%';

// fix static files autoloader
$autoload_static_path = path( $destination, 'vendor', 'composer', 'autoload_static.php' );
$autoload_static      = file_get_contents( $autoload_static_path );
$autoload_static      = preg_replace(
	"/'([[:alnum:]]+)'\s*=>\s*([a-zA-Z0-9 .'\"\/\-_]+),/",
	"'" . $prefix . "\\1' => \\2,",
	$autoload_static
);

file_put_contents( $autoload_static_path, $autoload_static );

// prefix the InstalledVersions key dump-autoload adds: it maps Composer's real class to the already loaded scoped file, so another plugin's class_exists() fatals
$installed_versions_key = "'" . str_replace( '\\', '\\\\', $namespace_prefix ) . "\\\\Composer\\\\InstalledVersions'";

foreach ( [ 'autoload_classmap.php', 'autoload_static.php' ] as $autoload_file ) {
	$autoload_path = path( $destination, 'vendor', 'composer', $autoload_file );
	$autoload      = file_get_contents( $autoload_path );
	$autoload      = str_replace( "'Composer\\\\InstalledVersions'", $installed_versions_key, $autoload );

	file_put_contents( $autoload_path, $autoload );
}

// copy composer.lock
remove( path( $cwd, $composer_lock ) );
copy( path( $destination, 'composer.lock' ), path( $cwd, $composer_lock ) );

// copy vendor-prefixed folder
remove( $vendor_scoped );
rename( path( $destination, 'vendor' ), $vendor_scoped );

// copy aviary-autoload.php into vendor-prefixed folder
copy( path( __DIR__, 'aviary-autoload.php' ), path( $vendor_scoped, 'aviary-autoload.php' ) );

// remove temp folder
remove( $temp );

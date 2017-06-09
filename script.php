<?php
/**
 * Update composer.lock for a given repository.
 */

use CLU\Logger;

require_once __DIR__ . '/vendor/autoload.php';

$repo_url = ! empty( $argv[1] ) ? $argv[1] : '';
if ( getenv( 'CLU_GIT_URL' ) ) {
	$repo_url = getenv( 'CLU_GIT_URL' );
}
if ( ! $repo_url ) {
	Logger::error( 'Git URL must be provided as first argument or with CLU_GIT_URL' );
}

// Check that Git, Composer, and Hub are available on the filesystem
$execs = array( 'git', 'composer', 'hub' );
foreach( $execs as $exec ) {
	exec( 'type ' . escapeshellarg( $exec ), $_, $return_code );
	if ( 0 !== $return_code ) {
		Logger::error( "Missing {$exec} on the system." );
	}
}
Logger::info( 'Found required executables on system: ' . implode( ', ', $execs ) );

// Clone the repository to a working directory.
$shorthash = substr( md5( mt_rand() . time() ), 0, 7 );
$target_dir = sys_get_temp_dir() . '/composer-update-' . $shorthash;
$cmd = 'git clone ' . escapeshellarg( $repo_url ) . ' ' . escapeshellarg( $target_dir );
Logger::info( $cmd );
passthru( $cmd, $return_code );
if ( 0 !== $return_code ) {
	Logger::error( 'Git failed to clone repository.' );
}
Logger::info( 'Git clone successful.' );

// Run all future commands from the context of the target directory.
chdir( $target_dir );

// Perform an initial install to sanity check the package.
$cmd = 'composer install --no-dev --no-interaction';
Logger::info( $cmd );
passthru( $cmd, $return_code );
if ( 0 !== $return_code ) {
	Logger::error( 'Composer failed to install dependencies.' );
}

// Run composer update, but capture output for the commit message if needed.
$cmd = 'composer update --no-progress --no-dev --no-interaction';
Logger::info( $cmd );
$proc = proc_open( $cmd, array(
	0 => array( 'pipe', 'r' ),
	1 => array( 'pipe', 'w' ),
	2 => array( 'pipe', 'w' ),
), $pipes );
$status = proc_get_status( $proc );
while( $status['running'] ) {
	$status = proc_get_status( $proc );
}
$stdout = stream_get_contents( $pipes[1] );
$stderr = stream_get_contents( $pipes[2] );
proc_close( $proc );
if ( 0 !== $status['exitcode'] ) {
	Logger::error( 'Composer failed to update dependencies.' );
}

$update_message = trim( $stderr );

// Check whether composer.lock was modifed.
$output = array();
exec( 'git status -s composer.lock', $output, $return_code );
if ( empty( $output ) ) {
	Logger::success( 'No changes detected to composer.lock' );
	exit;
}
Logger::info( 'Detected changes to composer.lock' );

// Checkout a dated branch to make the commit
$date = date( 'Y-m-d' );
$branch_name = 'clu-' . $date . '-' . $shorthash;
$cmd = 'git checkout -b ' . escapeshellarg( $branch_name );
Logger::info( $cmd );
passthru( $cmd, $return_code );
if ( 0 !== $return_code ) {
	Logger::error( 'Failed to check out branch.' );
}

$message = <<<EOT
Update Composer dependencies ({$date})

```
{$update_message}
```
EOT;

$cmd = 'git commit -am ' . escapeshellarg( $message );
Logger::info( $cmd );
passthru( $cmd, $return_code );
if ( 0 !== $return_code ) {
	Logger::error( 'Failed to commit changes.' );
}

$cmd = 'git push origin ' . escapeshellarg( $branch_name );
Logger::info( $cmd );
passthru( $cmd, $return_code );
if ( 0 !== $return_code ) {
	Logger::error( 'Failed to push changes to origin.' );
}

$cmd = 'hub pull-request -m ' . escapeshellarg( $message );
Logger::info( $cmd );
passthru( $cmd, $return_code );
if ( 0 !== $return_code ) {
	Logger::error( 'Failed to create a pull request with hub.' );
}

Logger::success( 'Created pull request with composer.lock changes.' );

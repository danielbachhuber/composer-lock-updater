<?php
/**
 * Update composer.lock for a given repository.
 */

use CLU\Logger;

require_once __DIR__ . '/vendor/autoload.php';

// Check that Git, Composer, and Hub are available on the filesystem
$execs = array( 'git', 'composer', 'hub' );
foreach( $execs as $exec ) {
	exec( 'type ' . escapeshellarg( $exec ), $_, $return_code );
	if ( 0 !== $return_code ) {
		Logger::error( "Missing {$exec} on the system." );
	}
}
Logger::info( 'Found required executables on system: ' . implode( ', ', $execs ) );

// @todo Check that the appropriate env variables are set.
$repo_url = 'git@github.com:pantheon-systems/solr-power.git';

// Clone the repository to a working directory.
$target_dir = sys_get_temp_dir() . '/composer-update-' . md5( mt_rand() . time() );
passthru( 'git clone ' . escapeshellarg( $repo_url ) . ' ' . escapeshellarg( $target_dir ), $return_code );
if ( 0 !== $return_code ) {
	Logger::error( 'Git failed to clone repository.' );
}
Logger::info( 'Git clone successful.' );

// Run all future commands from the context of the target directory.
chdir( $target_dir );

// Perform an initial install to sanity check the package.
passthru( 'composer install --no-dev --no-interaction', $return_code );
if ( 0 !== $return_code ) {
	Logger::error( 'Composer failed to install dependencies.' );
}

// Run composer update, but capture output for the commit message if needed.
$proc = proc_open( 'composer update --no-progress --no-dev --no-interaction', array(
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

// Check whether composer.lock was modifed.
$output = array();
exec( 'git status -s composer.lock', $output, $return_code );
if ( empty( $output ) ) {
	Logger::success( 'No changes detected to composer.lock' );
	exit;
}
Logger::info( 'Detected changes to composer.lock' );

// @todo Create a branch and submit a pull request

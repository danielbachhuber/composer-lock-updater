<?php
/**
 * Update composer.lock for a given repository.
 */

use CLU\Logger;

require_once __DIR__ . '/vendor/autoload.php';

// Check that Git, Composer, and Hub are available on the filesystem
$execs = array( 'git', 'composer', 'hub' );
foreach( $execs as $exec ) {
	exec( 'type ' . $exec, $output, $return_code );
	if ( 0 !== $return_code ) {
		Logger::error( "Missing {$exec} on the system" );
	}
}
Logger::info( 'Found required executables on system: ' . implode( ', ', $execs ) );

// @todo Check that the appropriate env variables are set.

// @todo Check that Git, Composer, and Hub are available on the filesystem


// @todo Clone and run composer update

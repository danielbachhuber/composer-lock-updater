<?php

namespace CLU;

class Checker {

	/**
	 * Check that Git, Composer, and Hub or Lab are available on the filesystem.
	 */
	public static function check_executables( $provider ) {
		$execs = [
			'git',
			'composer',
		];
		if ( 'github' === $provider ) {
			$execs[] = 'hub';
		} elseif ( 'gitlab' === $provider ) {
			$execs[] = 'lab';
		}
		foreach( $execs as $exec ) {
			exec( 'type ' . escapeshellarg( $exec ), $_, $return_code );
			if ( 0 !== $return_code ) {
				Logger::error( "Missing {$exec} on the system." );
			}
		}
		Logger::info( 'Found required executables on system: ' . implode( ', ', $execs ) );
	}

}

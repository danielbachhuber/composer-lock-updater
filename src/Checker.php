<?php

namespace CLU;

class Checker {

	/**
	 * Check that Git, Composer, and Hub or Lab are available on the filesystem.
	 */
	public static function check_executables( \stdClass $provider ) {
		$execs = array_merge([
			'git',
			'composer',
			'tee',
		], $provider->exec);
		foreach( $execs as $exec ) {
			exec( 'type ' . escapeshellarg( $exec ), $_, $return_code );
			if ( 0 !== $return_code ) {
				Logger::error( "Missing {$exec} on the system." );
			}
		}
		Logger::info( 'Found required executables on system: ' . implode( ', ', $execs ) );

		$output = shell_exec( 'set -o' );
		if ( false !== stripos( $output, 'pipefail' ) ) {
			Logger::info( 'Found required pipefail option in shell.' );
		} else {
			Logger::error( "Missing required 'pipefail' option in shell." );
		}
	}

	public static function get_config() {
		$defaults = json_decode(file_get_contents(__DIR__ . '/../clu-config.dist.json'));
		$defaults->providers = get_object_vars($defaults->providers);
		if (file_exists($path = getenv('COMPOSER_HOME') . '/clu-config.json')
			|| file_exists($path = getcwd() . '/clu-config.json')
			){
			$config = json_decode(file_get_contents($path));
			if ($error = json_last_error()) {
				return $defaults;
			}
			if (!empty($config->providers)) {
				$defaults->providers = array_merge($defaults->providers, get_object_vars($config->providers));
			}
		}
		return $defaults;
	}
}

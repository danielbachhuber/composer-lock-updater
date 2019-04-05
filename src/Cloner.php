<?php

namespace CLU;

class Cloner {

	/**
	 * Clone the repository.
	 *
	 * @param string $repo_url
	 * @return string $repo_local_working_copy
	 */
	public function cloneRepo( $repo_url ) {
		// Clone the repository to a working directory.
		$shorthash = substr( md5( mt_rand() . time() ), 0, 7 );
		$repo_local_working_copy = sys_get_temp_dir() . '/composer-update-' . $shorthash;
		$cmd = 'hub clone ' . escapeshellarg( $repo_url ) . ' ' . escapeshellarg( $repo_local_working_copy );

		// If the repo URL has an oauth token.
		if( false !== stripos( $repo_url, 'x-oauth-basic' ) ) {
			// Redact the token from the command passed to Logger.
			Logger::info(
				preg_replace(
					'/[a-zA-Z0-9]+:x-oauth-basic/',
					'[REDACTED]:x-oauth-basic',
					$cmd
				)
			 );
		} else {
			// Otherwise log the command as normal.
			Logger::info( $cmd );
		}

		passthru( $cmd, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Git failed to clone repository.' );
		}
		Logger::info( 'Git clone successful.' );

		return $repo_local_working_copy;
	}
}

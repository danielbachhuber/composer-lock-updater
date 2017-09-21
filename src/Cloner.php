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
		Logger::info( $cmd );
		passthru( $cmd, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Git failed to clone repository.' );
		}
		Logger::info( 'Git clone successful.' );

		return $repo_local_working_copy;
	}
}

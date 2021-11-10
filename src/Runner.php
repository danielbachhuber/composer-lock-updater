<?php

namespace CLU;

class Runner {

	/**
	 * Git repository URL.
	 *
	 * @var string
	 */
	private $repo_url;

	/**
	 * Instantiate the runner.
	 *
	 * @param string $repo_url
	 * @param \stdClass $provider
	 */
	public function __construct( $repo_url, \stdClass $provider ) {
		$this->repo_url = $repo_url;
		$this->provider = $provider;
	}

	public function start( $target_dir, $opts ) {
		// Clone the repository to a working directory.
		$shorthash = substr( md5( mt_rand() . time() ), 0, 7 );

		// Run all future commands from the context of the target directory.
		chdir( $target_dir );
		Logger::info( 'Changed into directory: ' . getcwd() );

		// Check if there are any security advisories for any of the
		// versions of any of our dependencies in use right now.
		$security_message = $this->checkSensiolabsSecurity( 'composer.lock', $is_vulnerable );
		Logger::info( $security_message );

		// Exit early if user requested security updates only, and no dependencies
		// are vulnerable.
		if ( isset($opts['security-only']) && !$is_vulnerable ) {
			Logger::info('Exiting since --security-only was specified, and there are no security updates available.');
			exit(0);
		}

		// Determine whether there is an existing open PR with Composer updates
		$existing_PR_branch = $this->checkExistingPRBranch( 'branch' );

		if ( $existing_PR_branch ) {
			exec( 'git rev-parse --abbrev-ref HEAD', $initial_branch, $return_code );
			if ( 0 !== $return_code ) {
				Logger::error( 'Failed to fetch initial branch.' );
			}
			$initial_branch = $initial_branch[0];
			Logger::info( "Inspecting existing branch: $existing_PR_branch" );
			passthru( 'git fetch' );
			passthru( 'git checkout ' . $existing_PR_branch );
			// Check to see if there are any outdated dependencies.
			$this->runComposerInstall();
			$this->runComposerUpdate();
			if ( ! $this->checkComposerLock() ) {
				exit(0);
			}
			// Close the existing PR and delete its branch.
			$this->closeExistingPRBranch( $existing_PR_branch );
			// Check out the initial branch locally and delete the local PR branch.
			passthru( 'git checkout -f ' . $initial_branch );
			$cmd = 'git branch -D ' . escapeshellarg( $existing_PR_branch );
			Logger::info( $cmd );
			passthru( $cmd, $return_code );
			if ( 0 !== $return_code ) {
				Logger::error( 'Failed to delete local branch.' );
			}
			// Fall through to create the new branch.
		}

		// Perform an initial install to sanity check the package.
		$this->runComposerInstall();

		// Run composer update, but capture output for the commit message if needed.
		$update_message = $this->runComposerUpdate();

		// Check whether composer.lock was modifed.
		if ( ! $this->checkComposerLock() ) {
			exit(0);
		}

		$git_name = getenv( 'CLU_GIT_NAME' ) ? : 'composer-lock-update';
		exec( 'git config user.name ' . escapeshellarg( $git_name ), $_, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Failed to set git config name.' );
		}
		$git_email = getenv( 'CLU_GIT_EMAIL' ) ? : 'composer-lock-update@localhost';
		exec( 'git config user.email ' . escapeshellarg( $git_email ), $_, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Failed to set git config email.' );
		}
		Logger::info( 'Set git config name and email.' );

		$date = date( 'Y-m-d-H-i' );
		// Checkout a dated branch to make the commit
		$branch_name = 'clu-' . $date;
		$cmd = 'git checkout -b ' . escapeshellarg( $branch_name );
		Logger::info( $cmd );
		passthru( $cmd, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Failed to check out branch.' );
		}

		$message = <<<EOT
Update Composer dependencies ({$date})

```
{$update_message}{$security_message}
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

		$cmd = sprintf($this->provider->pr_create, escapeshellarg( $message ));
		Logger::info( $cmd );
		passthru( $cmd, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( sprintf( 'Failed to create a %s.', $this->getRequestType() ) );
		}

		Logger::success( sprintf( 'Created %s with composer.lock changes.', $this->getRequestType() ) );
	}

	/**
	 * Checks to see if there's an existing PR branch.
	 *
	 * @param string $type Type of value to return.
	 * @return mixed
	 */
	private function checkExistingPRBranch( $type ) {
		$cmd = $this->provider->pr_list;
		exec($cmd, $output_lines, $return_code);
		if ( 0 !== $return_code ) {
			Logger::error( sprintf( 'Unable to check for existing %ss', $this->getRequestType() ) );
		}
		foreach ($output_lines as $line) {
			if (preg_match($this->provider->title_pattern, $line, $matches)) {
				if ( 'number' === $type ) {
					return $matches[1];
				} elseif ( 'branch' === $type ) {
					// We will presume the branch name is 'clu-' followed by the date in the issue title.
					return 'clu-' . $matches[2];
				}
			}
		}
		return false;
	}

	/**
	 * Closes an existing PR branch.
	 *
	 * @param string $branch_name Name of the branch.
	 * @return boolean
	 */
	private function closeExistingPRBranch( $branch_name ) {
		$number = $this->checkExistingPRBranch( 'number' );
		if ( ! $number ) {
			Logger::error( sprintf( 'Unable to find existing %s number', $this->getRequestType() ) );
		}
		$cmd = sprintf( $this->provider->pr_close, $number );
		exec($cmd, $output_lines, $return_code);
		if ( 0 !== $return_code ) {
			Logger::error( sprintf( 'Unable to close existing %s #%d: %s', $this->getRequestType(), $number, implode( PHP_EOL, $output_lines ) ) );
		}
		Logger::info( sprintf( 'Closed existing %s #%d', $this->getRequestType(), $number ) );
		$cmd = 'git push origin --delete ' . escapeshellarg( $branch_name );
		Logger::info( $cmd );
		passthru( $cmd, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Failed to delete origin branch.' );
		}
	}

	/**
	 * Check the Sensiolabs security component if availble.
	 *
	 * $security_status will be 0 if the code is believed to be good, and
	 * will be non-zero if vulnerabilities were detected (status == 1), or
	 * if the vulnerability status is unknown (status == 127).
	 */
	protected function checkSensiolabsSecurity($composerLockPath, &$security_status) {
		// If the security-checker app is not installed, return an empty message
		exec( 'which security-checker.phar', $outputOfWhich, $return_code );
		if ( $return_code ) {
			$security_status = 127;
			return '';
		}

		exec( 'security-checker.phar security:check --no-ansi ' . $composerLockPath, $output, $is_vulnerable );
		return "\n\n" . implode("\n", $output);
	}

	/**
	 * Returns 'pull request' or 'merge request', depending on provider.
	 *
	 * @return string
	 */
	private function getRequestType() {
		return isset($this->provider->request_type) ? $this->provider->request_type : 'pull request';
	}

	/**
	 * Checks a `composer.lock` file to see if changes were detected.
	 *
	 * @return boolean
	 */
	private function checkComposerLock() {
		$output = array();
		exec( 'git status -s composer.lock', $output, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Failed to detect changes to composer.lock' );
		}
		if ( empty( $output ) ) {
			Logger::success( 'No changes detected to composer.lock' );
			return false;
		}
		Logger::info( 'Detected changes to composer.lock' );
		return true;
	}

	/**
	 * Runs `composer install`.
	 */
	private function runComposerInstall() {
		$args = getenv( 'CLU_COMPOSER_INSTALL_ARGS' ) ? : '--no-dev --no-interaction';
		$cmd  = 'composer install ' . $args;
		Logger::info( $cmd );
		passthru( $cmd, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Composer failed to install dependencies.' );
		}
	}

	/**
	 * Runs `composer update`.
	 */
	private function runComposerUpdate() {
		$args = getenv( 'CLU_COMPOSER_UPDATE_ARGS' ) ? : '--no-progress --no-dev --no-interaction';
		$cmd  = 'bash -c "set -o pipefail && composer update ' . $args . ' 2>&1 | tee vendor/update.log"';
		Logger::info( $cmd );
		exec( $cmd, $output, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Composer failed to update dependencies.' );
		}
		$output = implode( PHP_EOL, $output );
		Logger::info( $output );
		return $output;
	}

}

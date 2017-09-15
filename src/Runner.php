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
	 */
	public function __construct( $repo_url ) {
		$this->repo_url = $repo_url;
	}

	public function start( $target_dir ) {
		// Clone the repository to a working directory.
		$shorthash = substr( md5( mt_rand() . time() ), 0, 7 );

		// Run all future commands from the context of the target directory.
		chdir( $target_dir );
		Logger::info( 'Changed into directory: ' . getcwd() );

		// Determine whether there is an existing open PR with Composer updates
		$existing_PR_branch = $this->checkExisting();

		if ( $existing_PR_branch ) {
			Logger::info( "Using existing branch: $existing_PR_branch" );
			passthru( 'git fetch' );
			passthru( 'git checkout ' . $existing_PR_branch );
		}

		// Perform an initial install to sanity check the package.
		$cmd = 'composer install --no-dev --no-interaction';
		Logger::info( $cmd );
		passthru( $cmd, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Composer failed to install dependencies.' );
		}

		$cmd = 'composer outdated';
		Logger::info( $cmd );
		passthru( $cmd, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Failed to run composer outdated.' );
		}

		// Run composer update, but capture output for the commit message if needed.
		$cmd = 'composer update --no-progress --no-dev --no-interaction';
		Logger::info( $cmd );
		$cmd = 'cd ' . escapeshellarg( $target_dir ) . '; ' . $cmd;
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
		if ( ! empty( $stdout ) ) {
			Logger::info( $stdout );
		}
		$stderr = stream_get_contents( $pipes[2] );
		if ( ! empty( $stderr ) ) {
			Logger::info( $stderr );
		}
		proc_close( $proc );
		if ( 0 !== $status['exitcode'] ) {
			Logger::error( 'Composer failed to update dependencies.' );
		}

		$update_message = trim( $stderr );

		// Check whether composer.lock was modifed.
		$output = array();
		exec( 'git status -s composer.lock', $output, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Failed to detect changes to composer.lock' );
		}
		if ( empty( $output ) ) {
			Logger::success( 'No changes detected to composer.lock' );
			return;
		}
		Logger::info( 'Detected changes to composer.lock' );

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
		$branch_name = $existing_PR_branch;
		if ( !$existing_PR_branch ) {
			// Checkout a dated branch to make the commit
			$branch_name = 'clu-' . $date;
			$cmd = 'git checkout -b ' . escapeshellarg( $branch_name );
			Logger::info( $cmd );
			passthru( $cmd, $return_code );
			if ( 0 !== $return_code ) {
				Logger::error( 'Failed to check out branch.' );
			}
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

		if ( $existing_PR_branch ) {
			// Add comment to existing PR with $message
			$commitSha = exec( 'git rev-parse HEAD', $output_lines, $return_code);
			$this->addCommitComment( $message, $this->project(), $commitSha );
			Logger::success( 'Updated pull request with composer.lock changes.' );
			return;
		}

		$cmd = 'hub pull-request -m ' . escapeshellarg( $message );
		Logger::info( $cmd );
		passthru( $cmd, $return_code );
		if ( 0 !== $return_code ) {
			Logger::error( 'Failed to create a pull request with hub.' );
		}

		Logger::success( 'Created pull request with composer.lock changes.' );
	}

	private function checkExisting() {
		exec('hub issue', $output_lines, $return_code);
		if ( 0 !== $return_code ) {
			Logger::error( 'Unable to check for existing pull requests with hub.' );
		}
		foreach ($output_lines as $line) {
			if (preg_match('%Update Composer dependencies \(([0-9-]*)\)%', $line, $matches)) {
				// We will presume the branch name is 'clu-' followed by the date in the issue title.
				return 'clu-' . $matches[1];
			}
		}
		return false;
	}

	private function addCommitComment( $message, $project, $commitSha ) {
		// We expect that GITHUB token should always be defined; however, we
		// will silently omit the comment if it is not, since the new commit
		// is already visible on the PR, and the separate comment is therefore
		// not necessary for correct operation.
		$auth = getenv( 'GITHUB_TOKEN' );
		if ( !$auth ) {
			return;
		}

		$uri = "repos/$project/commits/$commitSha/comments";
		$data = [
			'body' => $message,
		];

		$this->curlGitHub($uri, $data, $auth);
	}

	public function curlGitHub( $uri, $postData = [], $auth = '' ) {
		Logger::info( 'Call GitHub API: ' . $uri );
		$ch = $this->createGitHubPostChannel( $uri, $postData, $auth );
		return $this->execCurlRequest( $ch, 'GitHub' );
	}

	protected function createGitHubPostChannel( $uri, $postData = [], $auth = '' ) {
		$url = "https://api.github.com/$uri";
		$ch = $this->createAuthorizationHeaderCurlChannel( $url, $auth );
		$this->setCurlChannelPostData( $ch, $postData );

		return $ch;
	}

	protected function createAuthorizationHeaderCurlChannel( $url, $auth = '' ) {
		$headers = [
			'Content-Type: application/json',
			'User-Agent: pantheon/terminus-build-tools-plugin'
		];

		if (!empty($auth)) {
			$headers[] = "Authorization: token $auth";
		}

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 5 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

		return $ch;
	}

	protected function setCurlChannelPostData( $ch, $postData, $force = false ) {
		if ( !empty($postData) || $force ) {
			$payload = json_encode( $postData );
			curl_setopt( $ch, CURLOPT_POST, 1 );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
		}
	}

	public function execCurlRequest( $ch, $service = 'API request' ) {
		$result = curl_exec($ch);
		if( curl_errno($ch) )
		{
			Logger::error( curl_error($ch) );
		}
		$data = json_decode( $result, true );
		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		$errors = [];
		if ( isset($data['errors']) ) {
			foreach ( $data['errors'] as $error ) {
				$errors[] = $error['message'];
			}
		}
		if ( $httpCode && ($httpCode >= 300) ) {
			$errors[] = "Http status code: $httpCode";
		}

		$message = isset( $data['message'] ) ? "{$data['message']}." : '';

		if ( !empty($message) || !empty($errors) ) {
			  $errors = implode( "\n", $errors );
			Logger::error( "$service error: $message $errors" );
		}

		return $data;
	}

	private function repo() {
		return $this->repo_url;
	}

	private function project() {
		if ( preg_match( '#([^/:]*/.*)$#', $this->repo_url, $matches ) ) {
			return rtrim( $matches[1], '.git' );
		}
	}

}

composer-lock-updater
=====================

Updates composer.lock files for the repositories it monitors.

More specifically, it:

1. Clones a given GitHub repository to a working `/tmp/` directory.
2. Runs `composer update` within the working directory.
3. Submits a pull request if changes are detected to a tracked `composer.lock file.

Run composer-lock-updater in your CI system for bot-powered `composer.lock` pull requests.

[Using](#using) | [Automating](#automating)

## Using

Before you use composer-lock-updater, ensure the `composer`, `git`, and `hub` executables are present on the filesystem. The current user will also need push access to the GitHub repository.

Install composer-lock-updater with:

    composer global require danielbachhuber/composer-lock-updater

Update your `composer.lock` file with:

    clu <git-url>

The script provides sufficiently verbose output for debugging purposes.

## Automating

This wouldn't be very useful if it didn't run automatically for you.

To configure composer-lock-updater to run on Travis master branch builds, add the following to your `.travis.yml` file:

    after_script:
      - |
        ###
        # Only run on one job of a master branch build
        ###
        if [ -z "$CLU_RUN" ] || [ "master" != "$TRAVIS_BRANCH"] ; then
          echo "composer.lock update disabled for this build"
          return
        fi
        ###
        # Install composer-lock-updater
        ###
        export PATH="$HOME/.composer/vendor/bin:$PATH"
        composer global require danielbachhuber/composer-lock-updater
        ###
        # Install hub for creating GitHub pull requests
        ###
        wget -O hub.tgz https://github.com/github/hub/releases/download/v2.2.9/hub-linux-amd64-2.2.9.tgz
        tar -zxvf hub.tgz
        export PATH=$PATH:$PWD/hub-linux-amd64-2.2.9/bin/
        # Turn off command traces while dealing with the git credentials
        set +x
        echo 'Securely storing GITHUB_USER and GITHUB_TOKEN in ~/.netrc'
        echo "\nmachine github.com login $GITHUB_USER password $GITHUB_TOKEN" >> ~/.netrc
        # Restore command traces for the rest of the script
        set -x
        ###
        # Run composer-lock-updater
        ###
        clu $CLU_REPO_URL

Set `CLU_REPO_URL` in your `.travis.yml` to the Git HTTPS URL you'd like to update:

    env:
      - CLU_REPO_URL=https://github.com/danielbachhuber/composer-lock-updater.git

To grant commit access to the Travis build, define `GITHUB_USER` and `GITHUB_TOKEN` private environment variables in the Travis control panel.

Lastly, because of the `CLU_RUN` environment variable, composer-lock-updater is disabled by default. Enable it for one job per build by modifying your environment matrix:

    matrix:
      include:
        - php: 7.1
          env: WP_VERSION=latest PHP_APCU=enabled CLU_RUN=1
        - php: 7.0
          env: WP_VERSION=latest PHP_APCU=enabled
        - php: 5.6
          env: WP_VERSION=latest PHP_APCU=enabled

Because composer-lock-updater is running on the `after_script` step, make sure to verify it's working correctly, because it won't fail your build if misconfigured.

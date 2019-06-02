composer-lock-updater
=====================

Run composer-lock-updater in your CI system for bot-powered `composer.lock` pull requests.

[![Build Status](https://travis-ci.org/danielbachhuber/composer-lock-updater.svg?branch=master)](https://travis-ci.org/danielbachhuber/composer-lock-updater)

When you run `clu`, it:

1. Clones a given GitHub repository to a working `/tmp/` directory.
2. Runs `composer update` within the working directory.
3. Submits a pull request if changes are detected to a tracked `composer.lock` file.

Et voila! Now your dependencies are no longer six months out of date.

[Use with Travis CI](#use-with-travis-ci) | [Run locally](#run-locally)

## Use with Travis CI

This wouldn't be very useful if it didn't run automatically for you.

To configure composer-lock-updater to run on Travis master branch builds, add the following to your `.travis.yml` file:

```bash
    after_script:
      - |
        ###
        # Only run on one job of a master branch build
        ###
        if [ -z "$CLU_RUN" ] || [ "$TRAVIS_BRANCH" != master ] ; then
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
        ###
        # Optional: install Sensio Labs security checker to include security advisories in PR comments
        ###
        mkdir -p $HOME/bin
        wget -O $HOME/bin/security-checker.phar http://get.sensiolabs.org/security-checker.phar
        chmod +x $HOME/bin/security-checker.phar 
        ###
        # Run composer-lock-updater
        ###
        clu $CLU_REPO_URL
```

To grant commit and pull request access to the Travis build, define these private environment variables in the Travis control panel:

    GITHUB_TOKEN=<personal-oauth-token>
    CLU_REPO_URL=https://<personal-oauth-token>:x-oauth-basic@github.com/<org>/<repo>.git

Make sure to replace `<personal-oauth-token>`, `<org>` and `<repo>` with the appropriate values.

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

## Run locally

Before you use composer-lock-updater locally, ensure the `composer`, `git`, and [`hub`](https://github.com/github/hub) executables are present on the filesystem. The current user will need to be authenticated with GitHub (both for push and creating pull requests).

Install composer-lock-updater with:

    composer global require danielbachhuber/composer-lock-updater

Then, update your `composer.lock` file with:

    clu <git-url>

The script provides sufficiently verbose output for debugging purposes.

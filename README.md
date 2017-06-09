composer-lock-updater
=====================

Updates composer.lock files for the repositories it monitors.

More specifically, it:

1. Clones a given GitHub repository to a working `/tmp/` directory.
2. Runs `composer update` within the working directory.
3. Submits a pull request if changes are detected to a tracked `composer.lock file.

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
        # Turn off command traces while dealing with the private key
        set +x
        if [ -n "$CLU_SSH_PRIVATE_KEY_BASE64" ]; then
          echo 'Securely extracting CLU_SSH_PRIVATE_KEY_BASE64 into ~/.ssh/id_rsa'
          echo $CLU_SSH_PRIVATE_KEY_BASE64 | base64 --decode > ~/.ssh/id_rsa
          chmod 600 ~/.ssh/id_rsa
        fi
        # Restore command traces for the rest of the script
        set -x
        ###
        # Run composer-lock-updater
        ###
        clu $CLU_REPO_URL

Set `CLU_REPO_URL` in your `.travis.yml` to the Git URL you'd like to update:

    env:
      - CLU_REPO_URL=git@github.com:danielbachhuber/composer-lock-updater.git

Because of the `CLU_RUN` environment variable, composer-lock-updater is disabled by default. Enable it for one job per build by modifying your environment matrix:

    matrix:
      include:
        - php: 7.1
          env: WP_VERSION=latest PHP_APCU=enabled CLU_RUN=1
        - php: 7.0
          env: WP_VERSION=latest PHP_APCU=enabled
        - php: 5.6
          env: WP_VERSION=latest PHP_APCU=enabled

To grant commit access to the Travis build, generate a `CLU_SSH_PRIVATE_KEY_BASE64` environment variable and save it as a private variable:

    ssh-keygen -t rsa -b 4096 -C "travis@travis-ci.org" -f ~/.ssh/id_rsa_travis -N ''
    cat ~/.ssh/id_rsa_travis | base64 --wrap=0

Then, add the public key as a deploy key with write access:

    cat ~/.ssh/id_rsa_travis.pub

Because composer-lock-updater is running on the `after_script` step, make sure to verify it's working correctly, because it won't fail your build if misconfigured.

composer-lock-updater
=====================

Run composer-lock-updater in your CI system for bot-powered `composer.lock` pull requests.

[![Build Status](https://travis-ci.org/danielbachhuber/composer-lock-updater.svg?branch=master)](https://travis-ci.org/danielbachhuber/composer-lock-updater)

When you run `clu`, it:

1. Clones a given git repository to a working `/tmp/` directory.
2. Runs `composer update` within the working directory.
3. Submits a pull request if changes are detected to a tracked `composer.lock` file.

Et voila! Now your dependencies are no longer six months out of date.

composer-lock-updater is different than [dependabot](https://dependabot.com/) in that it bundles all of your updates into one pull request, instead of creating separate pull requests for each dependency.

[Installing](#installing) | [Using](#using) | [Integrate with Travis CI](#use-with-travis-ci)

## Installing

composer-lock-updater is a PHP library that can be installed with Composer:

    composer global require danielbachhuber/composer-lock-updater

composer-lock-updater depends on `composer` and `git` being available on the system. For use with GitHub, also install the official [`hub`](https://github.com/github/hub) CLI tool. For use with GitLab, you can use the unofficial [`lab`](https://github.com/zaquestion/lab) CLI tool that emulates `hub`.

Both `hub` and `lab` will need to be authenticated with their respective services in order to create the pull/merge requests.

#### Support for other providers
Copy [clu-config.dist.json](clu-config.dist.json) to `$COMPOSER_HOME/clu-config.json` to add support for your git repository provider, or to make adjustments to the pull request commands. For example, to add support for a Bitbucket-Pantheon project using [Terminus Bitbucket Plugin](https://github.com/aaronbauman/terminus-bitbucket-plugin), create the following `clu-config.json`:
```
{
  "providers": {
    "terminus": {
      "provider": "terminus",
      "exec": ["terminus"],
      "pr_create": "terminus pr-create --title=\"Update Composer dependencies\" --description %s",
      "pr_list": "terminus pr-list",
      "pr_close": "terminus pr-close %d -y",
      "title_pattern": "%(\\d+)\\s+Update Composer dependencies\\s+clu\\-([0-9-]*)%"
    }
  }
}
```

## Using

Run composer-lock-updater within an existing GitHub repository with:

    clu

composer-lock-updater defaults to using `git config --get remote.origin.url`. If you'd like to specify a different value, either pass the repository URL as the first positional argument or define a `CLU_GIT_URL` environment variable.

To use composer-lock-updater with a GitLab repository, use:

    clu --provider=gitlab

composer-lock-updater also supports the following environment variables to modify its behavior:

* `CLU_COMPOSER_INSTALL_ARGS`: Arguments passed to `composer install`; defaults to `--no-dev --no-interaction`.
* `CLU_COMPOSER_UPDATE_ARGS`: Arguments passed to `composer update`; defaults to `--no-progress --no-dev --no-interaction`.
* `CLU_GIT_NAME`: Name used for Git commits; defaults to 'composer-lock-update'.
* `CLU_GIT_EMAIL`: Email used for Git commits; defaults to 'composer-lock-update@localhost'.

## Integrate with Travis CI

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
        #
        # You could also replace this with lab to create GitLab merge requests.
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

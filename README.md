composer-lock-updater
=====================

Updates composer.lock files for the repositories it monitors.

More specifically, it:

1. Clones a given GitHub repository to a working `/tmp/` directory.
2. Runs `composer update` within the working directory.
3. Submits a pull request if changes are detected to a tracked `composer.lock file.

## Using

Before you use composer-lock-updater, ensure the `composer`, `git`, and `hub` executables are present on the filesystem. The current user will also need push access to the GitHub repository.

Install composer-lock-updater with:

    composer global require danielbachhuber/composer-lock-updater

Update your `composer.lock` file with:

    clu <git-url>

The script provides sufficiently verbose output for debugging purposes.

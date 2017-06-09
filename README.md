composer-lock-updater
=====================

Updates composer.lock files for the repositories it monitors.

More specifically, it:

1. Clones a given GitHub repository to a working `/tmp/` directory.
2. Runs `composer update` within the working directory.
3. Submits a pull request if changes are detected to a tracked `composer.lock file.

## Using

Before you use composer-lock-updater, you'll need to ensure the `composer`, `git`, and `hub` executables are present on the filesystem. The user running the process will also need commit access to the GitHub repository.

Once you've satisfied these dependencies, using composer-lock-updater is a matter of running:

    php script.php <git-url>

The script provides sufficiently verbose output for debugging purposes.

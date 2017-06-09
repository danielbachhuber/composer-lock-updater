composer-lock-updater
=====================

Updates composer.lock files for the repositories it monitors.

Specifically, when the script is run, it:

1. Clones a given GitHub repository to a working `/tmp/` directory.
2. Runs `composer update` within the working directory.
3. Submits a pull request if changes are detected to a tracked `composer.lock file.

## Using

Run the process with:

    php script.php

Requires `composer`, `git`, and `hub` executables to be present on the filesystem.

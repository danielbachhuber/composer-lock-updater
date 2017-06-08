composer-lock-updater
=====================

Updates composer.lock files for the repositories it monitors.

When the composer.lock file changes, it submits a pull request to the GitHub repo.

## Using

Run the process with:

    php script.php

Requires `composer`, `git`, and `hub` executables to be present on the filesystem.

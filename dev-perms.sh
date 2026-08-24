#!/bin/bash
# DEV ONLY — never needed by end users.
#
# Makes the bind-mounted plugin tree writable by the Docker container's
# www-data (UID 33). Host files are owned by UID 1000, so without this,
# WP admin zip installs/updates fail with "files could not be copied".
#
# Run this before testing an install/update through the WP admin UI.
# Everyday development doesn't need it: the folder is bind-mounted, so
# host file edits are live in the container instantly.

chmod -R a+rwX wp-content/plugins

echo "OK: wp-content/plugins is now writable by the container."

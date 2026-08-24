#!/bin/bash
# DEV ONLY - never needed by end users.
#
# Makes the paths the wp-admin installer/updater touches writable by
# www-data. Unlike v1, the parent plugins/ dir exists only inside the
# container (it is not part of the bind mount), so both chmods run
# in-container; the wordpress image execs as root by default.
#
# Run before testing an install/update through the WP admin UI.
# Everyday development doesn't need it: the plugin folder is
# bind-mounted, so host edits are live in the container instantly.

docker compose exec wordpress \
	chmod -R a+w /var/www/html/wp-content/plugins

docker compose exec wordpress \
	chmod -R a+w /var/www/html/wp-content/plugins/wp-table-builder

echo "OK: plugins dir + plugin dir are now updater-safe."

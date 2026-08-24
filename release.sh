#!/usr/bin/env bash
#
# Build a distributable plugin zip into dist/ (gitignored).
#
# Usage: ./release.sh X.Y.Z
#
# The version must be bumped in three places together before running:
#   1. wp-table-builder.php header "Version:"
#   2. WTB_VERSION constant in the same file
#   3. this script's argument
# See DESIGN.md "Update mechanism".
#
set -euo pipefail

VERSION="${1:-}"
PLUGIN_DIR="wp-table-builder"
DIST_DIR="dist"

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "Usage: $0 X.Y.Z" >&2
	exit 1
fi

if [ ! -f "$PLUGIN_DIR/wp-table-builder.php" ]; then
	echo "Run from the repo root (expected ./$PLUGIN_DIR)." >&2
	exit 1
fi

#
# Hard gate: the updater is a runtime dependency required by the
# bootstrap. A build without it ships a plugin that fataled on its
# own updater in v1 - refuse instead of producing a broken artifact.
#
if [ ! -f "$PLUGIN_DIR/vendor/plugin-update-checker/plugin-update-checker.php" ]
then
	echo "Refusing to build:" >&2
	echo "  $PLUGIN_DIR/vendor/plugin-update-checker/ is missing." >&2
	exit 1
fi

mkdir -p "$DIST_DIR"

ZIP_PATH="$DIST_DIR/wp-table-builder-$VERSION.zip"
rm -f "$ZIP_PATH"

# Zip with the plugin folder as the top-level directory so WordPress
# installs it under wp-content/plugins/wp-table-builder/. Nothing is
# excluded except OS cruft - vendor/ must always travel.
zip -qr "$ZIP_PATH" "$PLUGIN_DIR" \
	-x '*/.DS_Store' \
	-x '*__MACOSX*'

echo "Built $ZIP_PATH"

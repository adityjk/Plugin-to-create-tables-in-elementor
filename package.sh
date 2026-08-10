#!/bin/bash

PLUGIN_SLUG="wp-table-builder"
VERSION="1.1.0"
ZIP_NAME="${PLUGIN_SLUG}-${VERSION}.zip"
TARGET_DIR="dist"

echo "Membuat folder tujuan: ${TARGET_DIR}"
mkdir -p ${TARGET_DIR}

echo "Menyiapkan file ZIP: ${TARGET_DIR}/${ZIP_NAME}"

# Pindah ke direktori wp-content/plugins untuk zip folder yang benar
cd wp-content/plugins

# Membuat arsip ZIP, mengabaikan file tersembunyi seperti .git atau file development
zip -r "../../${TARGET_DIR}/${ZIP_NAME}" "${PLUGIN_SLUG}" -x "*/\.*" -x "*/node_modules/*" -x "*/vendor/*" -x "*/tests/*" -x "*/phpunit.xml"

echo "Selesai! Plugin kamu siap didistribusikan."
echo "File berada di: ${TARGET_DIR}/${ZIP_NAME}"

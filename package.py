import os
import re
import zipfile
import shutil

plugin_slug = "wp-table-builder"
target_dir = "dist"
source_dir = os.path.join("wp-content", "plugins", plugin_slug)

with open(os.path.join(source_dir, f"{plugin_slug}.php"), encoding="utf-8") as f:
    header = f.read()

match = re.search(r"^ \* Version:\s+(.+)$", header, re.MULTILINE)
if not match:
    raise SystemExit(f"Tidak menemukan header 'Version:' di {source_dir}/{plugin_slug}.php")

version = match.group(1).strip()
print(f"Versi terdeteksi dari header plugin: {version}")
zip_name = f"{plugin_slug}-{version}.zip"

print(f"Membuat folder tujuan: {target_dir}")
os.makedirs(target_dir, exist_ok=True)

zip_path = os.path.join(target_dir, zip_name)
print(f"Menyiapkan file ZIP: {zip_path}")

def should_exclude(path):
    excludes = ['.git', 'node_modules', 'vendor', 'tests', 'phpunit.xml']
    for ex in excludes:
        if ex in path:
            return True
    if '/.' in path or '\\.' in path:  # exclude hidden files/folders
        return True
    return False

with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
    for root, dirs, files in os.walk(source_dir):
        # Exclude hidden directories
        dirs[:] = [d for d in dirs if not d.startswith('.')]
        for file in files:
            if file.startswith('.'):
                continue
            file_path = os.path.join(root, file)
            if not should_exclude(file_path):
                # We want the zip to contain 'wp-table-builder/...' directly
                # So we strip 'wp-content/plugins/' from the path
                arcname = os.path.relpath(file_path, os.path.join("wp-content", "plugins"))
                zipf.write(file_path, arcname)

print("Selesai! Plugin kamu siap didistribusikan.")
print(f"File berada di: {zip_path}")

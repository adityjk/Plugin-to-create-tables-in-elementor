# 🔄 Release & Update Guide

Everything you need to do when publishing a new version of **WTB Table Builder**
(slug: `wtb-table-builder`). End users don't need this file — for them,
updating is just *Dashboard → Updates → Update now*.

---

## ⚡ TL;DR Checklist

```
1. Bump version        → wtb-table-builder.php (header `Version:` + `WTB_VERSION`)
2. Build               → ./package.sh
3. Commit + push       → git add -A && git commit && git push
4. Tag                 → git tag vX.Y.Z && git push origin vX.Y.Z   (X.Y.Z = new version)
5. GitHub Release      → Releases → Draft a new release → pick the tag
6. Attach ZIP          → upload dist/wtb-table-builder-X.Y.Z.zip under "Assets"
7. Publish             → click "Publish release"
8. Verify              → test the update on your local WP site
```

---

## 📖 Step by step

### 1. Bump the version (TWO places)

Both live in `wp-content/plugins/wtb-table-builder/wtb-table-builder.php`:

```php
 * Version:     1.2.0          ← plugin header comment
define( 'WTB_VERSION',     '1.2.0' );   ← constant
```

`package.py` reads the header automatically, so the ZIP filename follows.
If these two get out of sync, the DB schema migration check (`wtb_db_version`)
and the update comparison both misbehave.

### 2. Build the ZIP

```bash
./package.sh        # or: python3 package.py
```

Output: `dist/wtb-table-builder-X.Y.Z.zip`. Sanity-check it once in a while:

- top-level folder inside must be exactly `wtb-table-builder/`
- must contain `wtb-table-builder.php` at its root
- must include `includes/class-updater.php`
- must **not** contain `.git`, `node_modules`, `vendor`, or hidden files

### 3. Commit, tag, push

The tag must equal the version (a leading `v` is fine — the updater strips it):

```bash
git add -A
git commit -m "Release X.Y.Z"
git push origin rebuild-clean-structure
git tag vX.Y.Z
git push origin vX.Y.Z
```

### 4. Publish the GitHub Release

Go to **Releases → Draft a new release** (or
`github.com/adityjk/Plugin-to-create-tables-in-elementor/releases/new?tag=vX.Y.Z`):

- **Tag**: select the tag you just pushed — do *not* let GitHub create a fresh one
- **Description**: write real changelog notes — this text appears in the
  *View details* popup inside WordPress
- **Assets**: drag in `dist/wtb-table-builder-X.Y.Z.zip`

> ⚠️ **Never rely on GitHub's automatic "Source code (zip)"** asset. Its top-level
> folder doesn't match `wtb-table-builder/`, so WordPress would fail to install
> it. Only the manually attached ZIP works as an update package.

### 5. Verify the release actually updates

On your local site (see testing setup below), click **Check for updates** and
confirm the new version appears with the correct changelog, then run
**Update now** and make sure tables still render afterwards.

---

## 🧪 Local testing setup (Docker)

Before any install/update test through the WP admin UI:

```bash
./dev-perms.sh
```

This is needed because host files are UID 1000 while the container runs as
`www-data` (UID 33). Without it you'll get *"files could not be copied"* errors.
Everyday development doesn't need it (the bind-mount is live).

Test cycle for a release candidate:

1. Install the **previous** release ZIP via *Plugins → Add New → Upload*
2. Publish the new release (steps above)
3. *Dashboard → Updates* → Check for updates → Update now
4. Confirm: version number updated, Table Builder menu loads, frontend renders

---

## 🛠 How the updater works (context)

`includes/class-updater.php` hooks WordPress' native update system:

- Queries `api.github.com/repos/adityjk/Plugin-to-create-tables-in-elementor/releases/latest`
- Compares the tag against `WTB_VERSION`; offers the release ZIP asset if newer
- Caches for 12 h (15 min after a failed lookup); purged automatically after any
  plugin install/update finishes
- Errors are logged through `WTB_Debug_Logger` when debug mode is enabled

**Slug ownership:** the name `wp-table-builder` belongs to an unrelated popular
plugin on wordpress.org (dotcamp). The updater therefore claims its update slot
exclusively — wordpress.org entries for this basename are stripped on every
update check, so core can never "update" us to their plugin. This is why:

- the plugin folder/slug must stay `wtb-table-builder` (renaming requires
  updating `package.py`, text domain, `.gitignore`, and docs together), and
- you should **never** register this plugin on wordpress.org under a colliding slug.

---

## 🚑 Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| "some files could not be copied" | Container can't write to `wp-content/plugins` | Run `./dev-perms.sh`, retry |
| "Could not remove the old plugin" + empty plugin folder | Plugin folder itself was the bind-mount point (can't be deleted) | Fixed in `docker-compose.yml` (whole `plugins/` dir is mounted). Restore files with `git checkout -- wp-content/plugins/wtb-table-builder` |
| Update downloads from `downloads.wordpress.org` | Old build without the slug-ownership guard, or slug renamed back to `wp-table-builder` | Ensure installed copy includes current `class-updater.php`; keep slug `wtb-table-builder` |
| No update notification despite new release | 12 h cache still fresh, or tag ≠ version, or no ZIP asset attached | Check the release has a `.zip` asset; confirm tag matches version; wait/clear transient `_transient_wtb_github_latest_release` |
| "No valid plugins were found" when uploading | Downloaded GitHub's *Source code (zip)* instead of the release asset | Use the ZIP attached under **Assets** |

---

## 🗑 Re-publishing a broken release

Tags are cheap to replace as long as nobody depends on them yet:

```bash
git tag -d v1.2.0 && git push origin :refs/tags/v1.2.0   # delete remote tag
# fix, rebuild, then re-tag & re-push
```

Then edit the release on GitHub and swap the attached asset. If users already
installed the broken build, prefer bumping to a brand-new version instead.

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

UVM Sublets — a server-rendered PHP app where UVM students post and browse sublet listings. This directory **is the live document root** for `https://sublet.aperkel.w3.uvm.edu` (UVM Silk hosting). Edits take effect on the live site immediately.

## Build / test / run

There is no build step, no test suite, no linter, and no local dev server. There is no `composer.json` or `package.json`; `vendor/` (vlucas/phpdotenv + symfony polyfills) was installed ad hoc and is gitignored.

PHP **is** installed, but not under the name `php` — use the versioned binary (`.mise.toml` pins 8.2):

```bash
/usr/bin/php82 -l some/file.php   # syntax check — run after every PHP edit
```

Since this directory is the live docroot, a fatal parse error takes the site down; always lint before finishing. `node` is *not* installed, so JS changes can't be syntax-checked locally.

`/usr/bin/mysql` is available, and a throwaway script that does `require_once includes/db.php` then runs `SELECT`s is the fastest way to verify query changes against real data. Keep such scripts outside the docroot (anything you drop in here is web-reachable) and keep them read-only.

## Configuration

`.env` is loaded from **one level above the web root** (`/users/a/p/aperkel/.env`), not from the `.env` in this directory — `includes/db.php` calls `Dotenv::createImmutable(__DIR__ . '/../../')`, and `demo/includes/db.php` uses `'/../../../'`. The in-repo `.env` is not the file being read. Keys: `DBNAME`, `DBUSER`, `DBPASS`, `GOOGLE_API`. The DB host `webdb.uvm.edu` is hardcoded in both `db.php` files.

## Authentication — Apache, not PHP

`app/.htaccess` does the authentication with `AuthType CAS` plus a `Require ldap-filter` line (all students, plus a named uid allowlist). PHP never sees a password; `includes/auth.php` just reads `$_SERVER['REMOTE_USER']`. There are no sessions and no login form.

- **To change who can reach the app**: edit the `Require ldap-filter` line in `app/.htaccess`.
- **To change who is admin**: `is_admin()` in `includes/auth.php` is a hardcoded `=== 'aperkel'` comparison.

## Three front-ends, one document root

| Path | Purpose | Auth |
|---|---|---|
| `landing.php` | Public marketing/roadmap page; `DirectoryIndex` at the root. Fully self-contained — inline `<style>`, does not use `includes/header.php`. | none |
| `app/` | The real application. | CAS |
| `demo/` | Read-only mirror of `app/` with sample data. | `AuthType None` |

`demo/` **duplicates** `index.php`, `map.php`, `post.php`, and the `includes/` files rather than sharing them. It shares only `css/style.css`, `js/app.js`, and `public/images/`. So:

- A markup change to `app/index.php` or `app/map.php` usually needs the parallel edit in `demo/`.
- `demo/includes/db.php` defines `DEMO_MODE` and `$SUBLET_TABLE = 'sublets_demo'` / `$IMAGES_TABLE = 'sublet_images_demo'`; demo queries interpolate those variables into SQL.
- `demo/includes/auth.php` returns `'DemoUser'`, `is_admin()` is always false, `require_admin()` always 403s.
- `demo/api/*.php` are no-op stubs returning `{"success":true,"demo":true}`; only `geocode.php` proxies to the real endpoint. `demo/post.php` shows a success message without writing anything.

## Security invariants

Auth is ambient (Apache/CAS via browser credentials), which shapes three rules:

- **`require_same_origin()`** (`includes/auth.php`) guards every state-changing request. There is no PHP session, so it validates `Sec-Fetch-Site`, falling back to `Origin`/`Referer`. **Any new POST endpoint needs it** — without it, any site on the internet can make a signed-in user's browser perform admin actions. It is a no-op on GET, which is also why destructive actions must never be reachable by GET.
- **Uploads are typed by their bytes, never their filename.** `safe_image_extension()` (`includes/thumbnail.php`) returns the extension to save under, or `null` to reject. `public/images/` is served by Apache, so trusting a client-supplied extension is a remote-code-execution path. `public/.htaccess` denies script extensions as a second layer.
- **`escapeHtml()` in `app.js` must escape quotes**, because its output is interpolated into `data-copy="..."` and `src="..."` attributes. The `textContent`→`innerHTML` idiom does *not* escape quotes and is unsafe here.

Deleting an image goes through **`delete_image_files()`**, which also removes the `_thumb.webp` sibling. Unlinking the path directly leaks thumbnails.

## Path duality: URL vs filesystem

The DB stores image paths as URL-relative strings (`./public/images/x.jpg`). `includes/db.php` defines `ROOT_DIR` and `resolve_path()` to convert those into absolute filesystem paths — **use `resolve_path()` for any unlink/file_exists on a DB-stored path**.

Pages inside `app/` set `$basePath = '../'` *before* requiring `../includes/header.php`, and `app/.htaccess` rewrites `public/`, `css/`, and `js/` requests up to the parent directory. A new top-level asset directory needs its own `RewriteRule` there.

## Client-side: one file, page-dispatched

`js/app.js` is a single `DOMContentLoaded` block that branches on `document.body.dataset.page` (set by `header.php` from `basename($_SERVER['PHP_SELF'])`) and reads `dataset.user` / `dataset.admin`. Server→client data is passed through globals emitted inline by each page: `window.SUBLET_CONFIG`, `window.MAP_SUBLETS`, `window.POST_CONFIG`, `window.DEMO_MODE`. Assets are cache-busted with `?v=<?= filemtime(...) ?>`.

Adding a page means adding both an `init<Page>()` branch in `app.js` and the matching `$currentPage` checks in `header.php` (which is what conditionally loads Leaflet and noUiSlider).

## API layer (`app/api/`)

Form-encoded POST in, JSON out — not REST. Endpoints dispatch on `$_POST['action']`; deletes are tunneled as `POST` with `_method=DELETE` (`images.php`). Admin endpoints call `require_admin()` immediately after setting the JSON header. `fetch()` calls in `app.js` use relative `api/...` paths so they resolve correctly under both `/app/` and `/demo/`.

| File | Notes |
|---|---|
| `posts.php` | admin-only delete of a post or of all posts by a user |
| `semesters.php` | admin-only add/toggle/delete; refuses to delete a semester that has posts |
| `images.php` | list by `sublet_id`; delete allowed for admin **or** post owner; promotes the next image to thumbnail if the thumbnail was deleted |
| `announcement.php` | GET public, POST admin-only |
| `email.php` | admin-only bulk `mail()` to `{username}@uvm.edu` |
| `contact_log.php` | POST logs a contact click (any user); GET is admin-only and paginated |
| `geocode.php` | proxy to Nominatim (no key needed) |

## Data model

There is no schema/migration file in the tree; the shape below is what the queries imply.

- **`sublets`** — effectively **one row per user**. `post.php` treats `username` as the key: it looks up the user's post to decide create-vs-edit, and updates with `WHERE username = ?`. Also holds `image_url`/`thumbnail_url`, `price`, `address`, `lat`/`lon`, `semester`, `posted_at`, contact fields, `utility_*`, and `amenity_*` flags.
- **`sublet_images`** — `sublet_id`, `image_url`, `sort_order`; `sort_order = 0` is the thumbnail.
- **`semesters`** — `code`, `name`, `active`, `sort_order`. `code` joins to `sublets.semester`; queries `COALESCE(sem.name, s.semester)` so unmapped codes still render.
- **`contact_logs`** — `post_id`, `poster_username`, `contacted_by`, `contact_type`, `created_at`.
- **`sublets_demo`** / **`sublet_images_demo`** — demo copies.

## Listing visibility (semester deactivation)

Deactivating a semester in the admin portal hides all of its listings from the public site. The rule lives in one place — `includes/visibility.php`, required by both `db.php` files — as two constants used to build queries:

```php
VISIBLE_SEMESTER_JOIN    // LEFT JOIN semesters sem ON s.semester = sem.code
VISIBLE_SEMESTER_WHERE   // (sem.code IS NULL OR sem.active = 1)
```

The `sem.code IS NULL` half is load-bearing, not defensive padding: listings whose semester code has no row in `semesters` must stay visible. The demo data relies on this — its codes (`summer2026`, `fall2026`) are unmapped, so a naive `sem.active = 1` would blank the entire demo site.

Applied in `includes/header.php` (dropdown + both slider bounds), `app/index.php`, `app/map.php`, and all four demo equivalents. **Any new query that lists sublets to the public needs it too.** `app/admin.php` deliberately does *not* filter — it shows every listing and flags the hidden ones via a `NOT (VISIBLE_SEMESTER_WHERE) as is_hidden` column.

Deactivation is reversible and deletes nothing. `app/post.php` keeps a deactivated semester selectable for the user who is already in it (otherwise the `<select>` would silently reassign their listing to the first option on save) and shows them an explanatory notice.

The rule also governs **who gets a broadcast email**: `$emailableUsers` in `app/admin.php` and the `type=all` query in `app/api/email.php` both filter by it, so nobody whose listing is hidden is swept into a mass mail. Those two must change together or the count in the UI stops matching what is actually sent. Picking a specific semester is exempt — that is an explicit choice, deactivated or not.

## Cleaning up public/images

The database does **not** fully describe this directory. `favicon.svg` is referenced only from `includes/header.php`, so a DB-only orphan scan will delete it. Any cleanup must union the DB references (`sublets`, `sublets_demo`, `sublet_images`, `sublet_images_demo` — both `image_url` *and* `thumbnail_url`) with a grep of the source tree, and protect the `_thumb.webp` sibling of everything it keeps.

Orphans accumulate mainly because uploads are keyed `{username}_{n}.{ext}`: re-posting with a different extension writes a new file instead of overwriting the old one.

## Things that are duplicated and drift easily

- **Campus coordinates** `44.477435, -73.195323` are hardcoded in the distance SQL in `includes/header.php`, `app/index.php`, `app/map.php`, and the demo equivalents, and again as PHP haversine in `app/post.php` (which rejects locations >50 miles). Changing them means changing all of them.
- **The listing filter query** (price / semester / distance) is copy-pasted across `app/index.php`, `app/map.php`, `demo/index.php`, `demo/map.php`.
- **The listing modal markup** is duplicated in `app/index.php` and `app/map.php` (and both demo copies) and is driven by the shared `openModal()` in `app.js`.

## Images

Uploads land in `public/images/`, named `{username}_{n}.{ext}` on create and `{username}_{time}_{order}.{ext}` when added during an edit. `includes/thumbnail.php` shells out to ImageMagick `convert` (or `sips` on macOS) to convert HEIC/HEIF to JPEG and auto-orient, then uses GD to write a `_thumb.webp` sibling. Note that only the first image of a *new* post gets a thumbnail generated; images added on edit do not.

## Announcements

The site-wide banner is a flat file, `data/announcement.json` (`active`, `message`, `style`, `updated_at`) — not a DB row. `includes/header.php` reads it on every page render; `app/api/announcement.php` writes it. The message is escaped, then `nl2br`'d and auto-linked.

## Email

All mail goes through PHP's `mail()`. Post create/update/delete each notify `aperkel@uvm.edu`; bulk admin mail goes to `{username}@uvm.edu` with an HTML template inlined in `app/api/email.php` (UVM green `#154734` / gold `#FFD100`), plus a copy to the admin.

## Styling

Single stylesheet `css/style.css`, built on CSS custom properties for the UVM palette (`--green`, `--gold`, `--slate`, `--sky`, `--orange`, `--fog`) plus shadow/radius/spacing tokens. Light mode only — there is no `prefers-color-scheme` handling. Font Awesome is loaded from a CDN kit; Leaflet and noUiSlider are CDN-loaded only on the pages that need them. `landing.php` does not use this stylesheet — it carries its own inline copy of the design tokens.

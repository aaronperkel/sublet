# UVM Sublets

A server-rendered PHP app where UVM students post and browse sublet listings near
campus. Students create a listing with photos, price, address, semester and
description; everyone else browses them in a grid or on an interactive map,
filtered by price, semester and distance from campus.

Live at **[go.uvm.edu/sublet](https://go.uvm.edu/sublet)** · public demo at
**[/demo/](https://sublet.aperkel.w3.uvm.edu/demo/)** (no login required).

`go.uvm.edu/sublet` is the link to share. It redirects to the app's real host,
`sublet.aperkel.w3.uvm.edu`, which is still what deep links such as `/demo/` and
`/s/<id>-<token>` have to be built on — the short link is one redirect to the
root, not a path prefix.

## Repository layout

| Path | What it is |
|---|---|
| `landing.php` | Public marketing / roadmap page. `DirectoryIndex` at the root, fully self-contained. |
| `app/` | The real application. Behind CAS authentication. |
| `demo/` | Read-only mirror of `app/` running on sample data, open to anyone. |
| `includes/` | Shared PHP: DB connection, auth, header, thumbnailing, visibility rules. |
| `assets/` | Project-owned static imagery — favicon and the demo sample listings. |
| `public/images/` | Runtime upload target for listing photos. Not tracked (see below). |
| `css/`, `js/` | One stylesheet and one page-dispatched JS file. |

`demo/` deliberately duplicates `index.php`, `map.php`, `post.php` and its own
`includes/` rather than sharing them, so a markup change in `app/` usually needs
the parallel edit in `demo/`. It shares only `css/`, `js/` and `assets/`.

## Running it

There is no build step, no bundler and no test suite. It is plain PHP 8.2 served
by Apache — drop the tree in a document root and point it at a database.

```bash
git clone https://github.com/aaronperkel/sublet.git
composer install          # vlucas/phpdotenv
```

Create a `.env` **one directory above the web root** (not inside it):

```ini
DBNAME=your_database
DBUSER=your_user
DBPASS=your_password
GOOGLE_API=your_places_key
```

The database host is currently hardcoded to `webdb.uvm.edu` in
`includes/db.php`. Syntax-check any change before it goes live, since the
deployed copy *is* the document root:

```bash
php -l includes/db.php
```

## Authentication

Authentication is handled by **Apache, not PHP**. `app/.htaccess` declares
`AuthType CAS` plus a `Require ldap-filter` line limiting access to UVM students
and a named allowlist. PHP never sees a password — `includes/auth.php` just
reads `$_SERVER['REMOTE_USER']`. There are no sessions and no login form.

That allowlist is managed from the **Access** tab of the admin portal, which is
how people who no longer carry a student affiliation — alumni, someone on a gap
year — keep access. The `allowed_users` table is the source of truth and the
`Require` line is generated from it, so editing the line by hand is overwritten
by the next change made in the portal. Because that file also governs the portal
itself, each write is backed up, swapped atomically, and verified over HTTP,
rolling back if the site stops responding correctly; `/recover/` restores a
backup if it ever comes to that.

Because credentials are ambient, every state-changing request is guarded by
`require_same_origin()`, and uploads are typed by their bytes rather than their
filename. Both live in `includes/`; new POST endpoints need the former.

## Data model

- **`sublets`** — effectively one row per user; holds price, address, lat/lon,
  semester, contact fields, and utility/amenity flags.
- **`sublet_images`** — additional photos per listing; `sort_order = 0` is the
  thumbnail.
- **`semesters`** — deactivating one hides all of its listings from the public
  site without deleting anything. The rule lives in `includes/visibility.php`
  and any new public listing query needs it.
- **`contact_logs`** — records when a browser contacts a poster.
- **`sublets_demo`** / **`sublet_images_demo`** — sample data behind `/demo/`.

## A note on `public/images/`

Listing photos are uploaded by real students and are named after their UVM
netid, so `public/images/` is **git-ignored** and this repository contains none
of them. A fresh clone will render listings without imagery until uploads
accumulate; the demo's sample SVGs live in `assets/` and are tracked, so `/demo/`
looks correct immediately.

## License

MIT — see [LICENSE](LICENSE).

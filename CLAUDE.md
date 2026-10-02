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

`.env` is loaded from **one level above the web root** (`/users/a/p/aperkel/.env`) — `includes/db.php` calls `Dotenv::createImmutable(__DIR__ . '/../../')`. There is no `.env` in this directory, and the root `.htaccess` would refuse to serve one. Keys: `DBNAME`, `DBUSER`, `DBPASS`, `GOOGLE_API`. The DB host `webdb.uvm.edu` is hardcoded in `includes/db.php`.

PHP settings for the web server live in `.user.ini` (upload limits, `date.timezone`). The CLI never reads it, so `/usr/bin/php82 -r` runs in UTC; use `php82 -c .user.ini -r '…'` to see what the web server sees. Changes take up to `user_ini.cache_ttl` (300s) to apply.

**Time zones.** PHP's `date()` is America/New_York via `.user.ini`, matching the server clock and MySQL (`time_zone = SYSTEM`, Eastern). Every DB timestamp (`posted_at`, `created_at`, `added_at`) is filled by `DEFAULT CURRENT_TIMESTAMP`; PHP never writes one. Before October 2026 PHP ran in UTC, so `updated_at` values in old copies of `data/announcement.json` and older "Last written" lines are UTC. The `.htaccess` backup filenames are deliberately still UTC (`gmdate()`), because they are sorted by name and local time repeats an hour every November.

## Authentication — Apache, not PHP

`app/.htaccess` does the authentication with `AuthType CAS` plus a `Require ldap-filter` line (all students, plus a named uid allowlist). PHP never sees a password; `includes/auth.php` just reads `$_SERVER['REMOTE_USER']`. There are no sessions and no login form.

- **To change who can reach the app**: use the **Access** tab in `app/admin.php`. It is the supported path — see "Access allowlist" below. The `Require ldap-filter` line is generated, so hand-editing it is overwritten by the next change made there.
- **To change who is admin**: `ADMIN_UID` in `includes/auth.php`, which `is_admin()` compares `REMOTE_USER` against.

## Front-ends, one document root

| Path | Purpose | Auth |
|---|---|---|
| `landing.php` | Public marketing/roadmap page; `DirectoryIndex` at the root. Fully self-contained — inline `<style>`, does not use `includes/header.php`. | none |
| `app/` | The real application. | CAS |
| `recover/` | Break-glass restore of `app/.htaccess`. Self-contained like `landing.php`; requires only `auth.php` + `htaccess_allowlist.php`, never `db.php`. | CAS, `Require user aperkel` |
| `s.php` | Public interstitial a share link lands on, reached as `/s/<id>-<token>`. Self-contained like `landing.php`. See "Sharing a listing". | none |
| `share-card.php` | Generates the preview image the share link unfurls into. | none |

There used to be a read-only `demo/` mirror of `app/` on sample data. It was retired in October 2026 because it duplicated `index.php`, `map.php`, `post.php` and `includes/` while sharing `css/style.css` and `js/app.js`, so every markup change had to be made twice. `/demo` now 302s to the front page (a 302 rather than a 301 so the path can be reused). Its `sublets_demo` / `sublet_images_demo` tables have been dropped.

## The public link is go.uvm.edu/sublet

`go.uvm.edu/sublet` is the link to publish. Anywhere the site is presented to
people — story graphics, link previews, the email footer, the README — it is the
URL that should appear, not `sublet.aperkel.w3.uvm.edu`. It is short enough to
retype off a phone screen, and it survives the app moving off a personal w3
hostname.

**It is one redirect, not a path prefix.** `go.uvm.edu/sublet` 302s to the real
origin's root; `go.uvm.edu/sublet/s/<slug>` 302s to `go.uvm.edu`'s own home
page. So deep links have to keep being built on the real host:

| Constant (`includes/share.php`) | Use |
|---|---|
| `SHARE_ORIGIN` | anything with a path — `/s/<slug>`, `/app/`, `og:image` |
| `SHARE_SHORT_URL` | a link that only has to reach the front door |
| `SHARE_DISPLAY_URL` | the bare `go.uvm.edu/sublet` painted into artwork |

Consequently `landing.php` sets `og:url` to the short link (it is what an unfurl
prints under the title) but keeps `og:image` on the real host, and `s.php` keeps
the per-listing `og:url` on the real host because the short link cannot express
it. `ALLOWLIST_SELF_TEST_URL` also stays on the real host — it asserts a 302 to
`idp.uvm.edu`, which only the real origin produces.

Three of the four graphics in `assets/social/` were drawn with the short link
already; `story-share-your-listing.png` was repainted to match. There is no
generator script for them — they come from a design tool, so a text change means
editing the PNG.

## Sharing a listing

Students share listings to Instagram stories, Snapchat and group chats. A link
into `/app/` cannot do that: the preview crawlers behind iMessage, Instagram and
Discord arrive with no CAS session, get the 302 to `idp.uvm.edu`, and never see
any HTML, so the link unfurls into nothing. The shared URL is therefore a public
one that carries the meta tags and then hands off to `/app/` behind SSO.

```
/s/187-d2e9710cf1   s.php           interstitial: preview card + "Sign in with your UVM NetID"
/share-card.php     share-card.php  the 1200x630 preview and the 1080x1920 story graphic
```

**What is public.** The preview card *is* the public surface — crawlers fetch it
unauthenticated and Meta and X cache it, which is what a preview is. That is
price, semester, bed/bath/roommate count and distance from campus, and nothing
else. Address, description, poster name, NetID, contact email and phone are
never loaded into `s.php`'s markup. `includes/share.php`'s `share_card_lines()`
defines that boundary in one place, because `s.php` bakes those strings into
meta tags and `share-card.php` paints them into the image — a preview whose
picture and title disagree is worse than either alone.

Distance is deliberately used instead of a neighbourhood: it is the fact a
reader wants, and unlike an address it does not narrow a listing to a house.

**The token is derived, not stored.** `share_token()` is
`substr(hash_hmac('sha256', 'sublet:' . $id, share_secret()), 0, 10)`. There is
no column for it: the app's DB user has no DDL grant, and schema changes are
pasted into phpMyAdmin by hand. Without a token, `/s/1`, `/s/2`, `/s/3` would be
a public index of every listing, since `sublets.id` is a plain autoincrement
already sitting in the page DOM. `parse_share_slug()` is the single gate —
shape and signature together, anchored with `\z` for the reason `valid_uid()`
is.

`share_secret()` reads `SHARE_SECRET` from `.env`, falling back to `DBPASS`.
**Rotating whichever key is in use invalidates every link already sent**, so it
is set once and left alone.

**Ordering trap.** `share.php` reads the secret out of `$_ENV`, which is only
populated once `db.php` has run Dotenv. Both `s.php` and `share-card.php`
therefore require `db.php` *before* verifying the slug — doing it the other way
round throws for want of a key on every request.

**Neither file may 500.** A crawler that gets an error caches the absence of a
preview, and the listing then unfurls into nothing for everyone. `share-card.php`
falls back to `assets/social/link-preview.png` (or `story-find-a-sublet.png`) on
any failure; `s.php` renders one shared "isn't available" page for a bad token,
an unknown id, a hidden listing *and* a database outage, so it cannot be used as
an oracle for which ids exist.

Both formats put the photo in a **near-square panel** — 552x630 on the og card,
1080x1150 on the story — rather than bleeding it across the frame. Listings are
photographed on phones, and the uploads on disk span 0.46 to 1.5 in aspect
ratio; cover-cropping a 1125x2436 screenshot to a full-bleed 1.9:1 scaled it
four times over and kept a sliver out of the middle. A panel near 0.9:1 sits in
the middle of that range, so every upload loses only its edges. It also puts the
type on flat colour, which is why there is no scrim any more.

Cards are drawn with GD in Bricolage Grotesque (static TTF cuts in `assets/fonts/`, falling back to Open Sans), cached to `public/share/` (gitignored)
under a fingerprint of the photo, its mtime and the text, and swept per listing
on rewrite. Bump `SHARE_CARD_VERSION` after a layout change. A source photo over
`SHARE_SOURCE_MAX_PIXELS` is pre-shrunk by ImageMagick rather than loaded — GD
holds 4 bytes a pixel and the largest upload on disk would exceed `memory_limit`
by itself. (Originals are now capped at 3000px on upload, so that path matters
less than it did, but older share cards were drawn before the cap.)

**In the app**, `includes/share_sheet.php` is the sheet markup, included by
`app/index.php`, `app/map.php` and `app/post.php` — a partial, not a fourth
copy-paste. Tiles are built in `initShare()` rather than in the partial because
which ones apply is a property of the browser: "Share to…" needs
`navigator.share`, and the Instagram and Snapchat tiles fetch the story graphic
and hand it to the OS share sheet as a *file*, which is what makes those apps
appear as targets at all (neither has a web endpoint that posts to a story).
Desktop downloads the image instead.

Listings carry their link as `data-share-url` on each card in `index.php` and as
`share_url` in `MAP_SUBLETS` — built server-side so the HMAC secret never
reaches the client. `app/index.php` accepts `?id=<n>`, which is how a share link
returns from CAS; it **drops the other filters** for that request, so a stale
price or semester in the URL cannot hide the card the link was sent to open.
Visibility still applies.

## Access allowlist (who can reach `/app/`)

The **Access** tab in `app/admin.php` edits who gets in, so that granting an
alum or a gap-year student access no longer means SSHing in to edit Apache
config. `allowed_users` is the source of truth; the `Require ldap-filter` line in
`app/.htaccess` is regenerated from it after every change. All of the generation
and file-swapping lives in `includes/htaccess_allowlist.php`.

The rule cannot be factored out of `app/.htaccess`. The vhost grants
`AllowOverride Options AuthConfig FileInfo Indexes Limit`, which is what makes
`Require ldap-filter` legal there, but Apache never permits `Include` in an
`.htaccess` context — so the whole line has to be written into the file.

Only the text between `# BEGIN MANAGED ACCESS` and `# END MANAGED ACCESS` is
ever rewritten; `DirectoryIndex`, `AuthType CAS` and the four `RewriteRule`s
survive by not being touched. **If those markers go missing, the write refuses**
rather than guessing which line is the managed one.

Four filter shapes come out of `build_require_line()`, all deliberately
*un*parenthesised at the top level because `mod_authnz_ldap` wraps the value in
its own parens — an already-parenthesised filter becomes a doubled `((...))` and
fails:

```
neither   eduPersonAffiliation=Student
allow     |(eduPersonAffiliation=Student)(uid=a)(uid=b)
block     &(eduPersonAffiliation=Student)(!(uid=x))
both      &(|(eduPersonAffiliation=Student)(uid=a))(!(uid=x))
```

Blocking beats allowing, since the negations are ANDed across the whole
expression. `ADMIN_UID` is forced into allow and out of block **inside the
generator**, not as a UI check, so locking the admin out is impossible whatever
the table says.

`valid_uid()` (`/^[a-z][a-z0-9]{0,15}\z/`) is the security boundary. It ends in
`\z`, not `$`, on purpose: `$` also matches before a trailing newline, so a `$`
version would accept `"aperkel\nRequire all granted"` and inject directives into
a file Apache executes. Invalid input is rejected, never repaired.

### Why the write path is defensive

`app/.htaccess` governs `app/admin.php`. A bad rule 500s all of `/app/`,
including the portal that would fix it. So `write_htaccess_block()` does:
validate → back up to `/users/a/p/aperkel/sublet-htaccess-backups/` (outside the
docroot, keeps 10) → write `.htaccess.tmp-<pid>` and `rename()` → fetch
`/app/` over HTTP and require a **302 to `idp.uvm.edu`** → restore the backup on
anything else, including a curl error. Requiring exactly that redirect rather
than merely "not a 500" also catches a filter that parses but matches nobody,
which denies everyone with a 403.

`/recover/` is the last resort, and it is why the module must never `require`
`db.php` — recovery has to work when the database does not.

**`app/.htaccess` must stay tracked in git.** Gitignoring it would leave a fresh
clone with no `AuthType CAS` at all. The consequence is that every access change
shows as a working-tree modification, and a `git checkout` can revert the file —
the tab detects that drift and **Rebuild from database** resyncs it.

`allowed_users` is created by hand: the app's DB user (`aperkel_writer`) has
SELECT/INSERT/UPDATE/DELETE but **no CREATE grant** on any database. The tab
detects the missing table via `table_exists()` (in `db.php`, added because
`table_columns()` throws on a missing table) and renders the DDL plus seed
`INSERT`s built from the live file — seeding matters, since an empty table would
regenerate the file down to `ADMIN_UID` alone and silently revoke everyone else.

## Security invariants

Auth is ambient (Apache/CAS via browser credentials), which shapes four rules:

- **`require_same_origin()`** (`includes/auth.php`) guards every state-changing request. There is no PHP session, so it validates `Sec-Fetch-Site`, falling back to `Origin`/`Referer`. **Any new POST endpoint needs it** — without it, any site on the internet can make a signed-in user's browser perform admin actions. It is a no-op on GET, which is also why destructive actions must never be reachable by GET.
- **Uploads are typed by their bytes, never their filename.** `safe_image_extension()` (`includes/thumbnail.php`) returns the extension to save under, or `null` to reject. `public/images/` is served by Apache, so trusting a client-supplied extension is a remote-code-execution path. `public/.htaccess` denies script extensions as a second layer.
- **Upload names are random, and that is the access control.** `public/images/` is served without auth (the landing page's photo strip needs it), so `new_upload_name()` — 128 random bits — is what stops anyone from fetching a listing's photos by guessing. Uploads used to be `{netid}_0.jpg`. Never derive a filename from the username, the listing id or anything else guessable, and never add a page that lists the directory.
- **Uploads keep no metadata but ICC.** Phone photos carry GPS. Anything that writes an image must go through `normalize_original()` / `make_display_image()` / `make_thumbnail()` (all use `IMAGE_KEEP_ONLY_ICC`), never a bare `convert` or `-strip` — `-strip` also drops the Display P3 profile and washes iPhone photos out.
- **Every image URL comes from `image_src()` or `display_src()`.** `public/.htaccess` caches uploads for a year as `immutable`, and files are rewritten in place under the same name; the `?v=<mtime>` those helpers append is the only thing that makes the new bytes show up. The same holds for CSS/JS/woff2 via the root `.htaccess` — every `<link>`/`<script>` needs `?v=filemtime`.
- **The docroot is also the git checkout, so the root `.htaccess` refuses what is only meant for disk**: any dot path segment except `.well-known/`, `includes/`, `vendor/`, `data/`, `*.md` and `composer.*` — all 403 via `RedirectMatch`, which (unlike `Require`) still applies under `app/`. A new top-level directory that should not be fetched needs adding there; one that should be fetched must not match those patterns.
- **The public share surface is `share_card_lines()` and nothing else.** `s.php` and `share-card.php` sit outside `/app/` and are read by anyone, crawlers included. Adding a field there publishes it — see "Sharing a listing".
- **`escapeHtml()` in `app.js` must escape quotes**, because its output is interpolated into `data-copy="..."` and `src="..."` attributes. The `textContent`→`innerHTML` idiom does *not* escape quotes and is unsafe here.

Deleting an image goes through **`delete_image_files()`**, which also removes the `_thumb.webp` and `_display.webp` siblings. Unlinking the path directly leaks them.

## Path duality: URL vs filesystem

The DB stores image paths as URL-relative strings (`./public/images/x.jpg`). `includes/db.php` defines `ROOT_DIR` and `resolve_path()` to convert those into absolute filesystem paths — **use `resolve_path()` for any unlink/file_exists on a DB-stored path**.

Pages inside `app/` set `$basePath = '../'` *before* requiring `../includes/header.php`, and `app/.htaccess` rewrites `public/`, `css/`, and `js/` requests up to the parent directory. A new top-level asset directory needs its own `RewriteRule` there.

## Client-side: one file, page-dispatched

`js/app.js` is a single `DOMContentLoaded` block that branches on `document.body.dataset.page` (set by `header.php` from `basename($_SERVER['PHP_SELF'])`) and reads `dataset.user` / `dataset.admin`. Server→client data is passed through globals emitted inline by each page: `window.SUBLET_CONFIG`, `window.MAP_SUBLETS`, `window.POST_CONFIG`. Assets are cache-busted with `?v=<?= filemtime(...) ?>`, and that is load-bearing: the root `.htaccess` serves CSS/JS/woff2 as `immutable` for a year, so a reference without it would be stuck on whatever version a browser first fetched.

Adding a page means adding both an `init<Page>()` branch in `app.js` and the matching `$currentPage` checks in `header.php` (which is what conditionally loads Leaflet and noUiSlider).

**Browse filters live.** On `app/index.php` a filter change fetches the same page with the new query, swaps `#listingsGrid`'s contents and the board heading, and `replaceState`s the URL (`liveUpdate()`; stale requests are aborted, and any failure falls back to a normal page load, which also sends a lapsed CAS session through sign-in). Because the grid's contents are replaced, **anything a card responds to must be delegated on `#listingsGrid`** (click, keydown, image `error` in the capture phase); a per-card listener silently stops working after the first filter change. Map still submits its form, since its listings live in `MAP_SUBLETS` and the pins. Untouched sliders leave `min_price`/`max_price`/`max_distance` empty, so they are not filters; `build_listing_filters()` returns `count` for the phone's Filters badge. Links marked `data-carry-filters` (nav Browse/Map, the phone's List/Map switch) are rewritten to carry the current filters.

**The listing view uses history.** `openModal()` pushes a `{listing: id}` state so a phone's Back closes it; `closeModal()` steps back over that entry and the `popstate` handler calls `hideModal()`. Close through `closeModal()`, never by hiding the overlay directly, or the extra entry is left behind.

Everything lives in one closure, so **module state read during init must be declared above the dispatch block**. Function declarations hoist; `var` assignments do not. `SHARE_TILES` declared next to `initShare()` was still `undefined` when the dispatch called it, and the resulting throw landed after `shareEls` was assigned but before any listener was attached — the sheet opened, showed no tiles, and could not be closed or copied from, and the abort took the `?id=` deep link with it.

`copyToClipboard()` / `flashCopied()` are shared by the contact panel and the share sheet. They exist because `navigator.clipboard` is undefined on insecure origins and rejects when the document is not focused — hence the `execCommand` fallback and the visible failure state.

## API layer (`app/api/`)

Form-encoded POST in, JSON out — not REST. Endpoints dispatch on `$_POST['action']`; deletes are tunneled as `POST` with `_method=DELETE` (`images.php`). Admin endpoints call `require_admin()` immediately after setting the JSON header. `fetch()` calls in `app.js` use relative `api/...` paths, resolved against `/app/`.

| File | Notes |
|---|---|
| `posts.php` | admin-only delete of a post or of all posts by a user |
| `semesters.php` | admin-only add/toggle/delete; refuses to delete a semester that has posts |
| `images.php` | list by `sublet_id`; delete allowed for admin **or** post owner; promotes the next image to thumbnail if the thumbnail was deleted |
| `announcement.php` | GET public, POST admin-only |
| `email.php` | admin-only bulk `mail()` to `{username}@uvm.edu` |
| `contact_log.php` | POST logs a contact click (any user); GET is admin-only and paginated |
| `geocode.php` | proxy to Nominatim (no key needed) |
| `allowlist.php` | admin-only `add`/`remove`/`rebuild` of approved & blocked netids; rewrites `app/.htaccess` and **undoes its own DB change if that write fails**, so the table and the file never disagree |

## Data model

There is no schema/migration file in the tree; the shape below is what the queries imply.

- **`sublets`** — effectively **one row per user**. `post.php` treats `username` as the key: it looks up the user's post to decide create-vs-edit, and updates with `WHERE username = ?`. Also holds `image_url`/`thumbnail_url`, `price`, `address`, `lat`/`lon`, `semester`, `posted_at`, contact fields, `utility_*`, and `amenity_*` flags.
- **`sublet_images`** — `sublet_id`, `image_url`, `sort_order`; `sort_order = 0` is the thumbnail.
- **`semesters`** — `code`, `name`, `active`, `sort_order`. `code` joins to `sublets.semester`; queries `COALESCE(sem.name, s.semester)` so unmapped codes still render.
- **`contact_logs`** — `post_id`, `poster_username`, `contacted_by`, `contact_type`, `created_at`.
- **`allowed_users`** — `uid`, `kind` (`allow`/`block`), `note`, `added_by`, `added_at`. `UNIQUE` on `uid` alone, not `(uid, kind)`: a netid is on one list or the other, never both. Source of truth for the generated `Require` line — see "Access allowlist". The `note` column is the reason someone has access ("gap year, back Fall 2026") and stays in the database; it never reaches `app/.htaccess`, which is committed to a public repo.

## Listing visibility (semester deactivation)

Deactivating a semester in the admin portal hides all of its listings from the public site. The rule lives in one place — `includes/visibility.php`, required by both `db.php` files — as two constants used to build queries:

```php
VISIBLE_SEMESTER_JOIN    // LEFT JOIN semesters sem ON s.semester = sem.code
VISIBLE_SEMESTER_WHERE   // (sem.code IS NULL OR sem.active = 1)
```

The `sem.code IS NULL` half is load-bearing, not defensive padding: listings whose semester code has no row in `semesters` must stay visible. Hiding a listing takes an explicit deactivation; a code that is merely missing from the table (a legacy code, or one an admin has not added yet) must fail open, not silently blank those listings. A naive `sem.active = 1` would do exactly that.

Applied in `includes/header.php` (dropdown + both slider bounds), in `build_listing_filters()` (`includes/listing_query.php`, shared by `app/index.php` and `app/map.php`), and in `landing.php`'s photo strip and counts. **Any new query that lists sublets to the public needs it too.** `app/admin.php` deliberately does *not* filter — it shows every listing and flags the hidden ones via a `NOT (VISIBLE_SEMESTER_WHERE) as is_hidden` column.

Deactivation is reversible and deletes nothing. `app/post.php` keeps a deactivated semester selectable for the user who is already in it (otherwise the `<select>` would silently reassign their listing to the first option on save) and shows them an explanatory notice.

The rule also governs **who gets a broadcast email**: `$emailableUsers` in `app/admin.php` and the `type=all` query in `app/api/email.php` both filter by it, so nobody whose listing is hidden is swept into a mass mail. Those two must change together or the count in the UI stops matching what is actually sent. Picking a specific semester is exempt — that is an explicit choice, deactivated or not.

## Cleaning up public/images

Every file here is an upload or one of its generated siblings, all named `<32 hex>.<ext>`, `<32 hex>_thumb.webp` or `<32 hex>_display.webp`. A file is an orphan when neither its own name nor (for a sibling) its original's name appears in `sublet_images.image_url`, `sublets.image_url` or `sublets.thumbnail_url`. Any cleanup must check all three columns and protect the `_thumb.webp` and `_display.webp` siblings of everything it keeps. (The favicon used to live here and was the classic casualty of a DB-only scan; it is now `assets/favicon.svg`.)

As of October 2026 there are none. Since names are never reused, orphans can only come from a request that dies between writing a file and inserting its row, or from code that unlinks a path without `delete_image_files()`.

## Things that are duplicated and drift easily

- **Campus coordinates** `44.477435, -73.195323` are `CAMPUS_LAT` / `CAMPUS_LON` in `includes/listing_query.php` (used by the distance SQL and `app/post.php`'s haversine, which rejects locations >50 miles), and are written out again as `CAMPUS` in `js/app.js` and as the default map centre in `app/post.php`'s `POST_CONFIG`. Changing them means changing all three.
- **The listing modal** and **the filter form** used to be pasted into both `app/index.php` and `app/map.php`; they are now partials, `includes/listing_modal.php` and `includes/filter_bar.php`. Change them there.
- **The Instagram handle** lives as `SOCIAL_INSTAGRAM_URL` / `SOCIAL_INSTAGRAM_HANDLE` in `includes/share.php`, used by `includes/footer.php` and `s.php`, and is hardcoded again in `landing.php` and `app/api/email.php` — each of which is deliberately dependency-free and already hardcodes the short link for the same reason. Three places.

## Images

Uploads land in `public/images/` under `new_upload_name()`: 32 random hex characters plus the extension, with siblings named after the original (see "Security invariants" for why). Each one goes through `includes/thumbnail.php`:

| File | Made by | What it is | Shown by |
|---|---|---|---|
| `x.jpg` (original) | `ensure_browser_safe()` → `normalize_original()` | HEIC→JPEG, upright, ≤3000px long edge, ICC only. Rewritten in place via a dotfile temp + `rename()`. | nothing in the UI any more |
| `x_display.webp` | `make_display_image()` (ImageMagick) | ≤1600px, q80, ICC only, ~90–170 KB | modal gallery, map modal, admin image grid, post edit page — via `display_src()`, which falls back to the original |
| `x_thumb.webp` | `make_thumbnail()` (ImageMagick) | 600px wide, q80, ICC only | listing cards, map popups, landing strip — via `sublets.thumbnail_url` |

Every image gets a display copy. Only the card image gets a thumbnail: the first image of a new post, or the image promoted by `images.php` when the card image is deleted. All three writers go through `convert_into_place()` (temp dotfile + `rename()`), so a request never reads half an image. Thumbnails were GD until October 2026, which dropped the ICC profile.

Two one-off scripts outside the docroot brought the existing files to this state in October 2026, and both are idempotent if they ever need re-running: `~/sublet-scripts/backfill_images.php` (normalize, display copies, thumbnails) and `~/sublet-scripts/migrate_random_names.php` (renames, with `rename-journal.json` mapping every old name to its new one). The pre-backfill originals, under their old `{netid}_…` names, are in `~/sublet-image-backups/`; the journal is how to find a given file there.

## Announcements

The site-wide banner is a flat file, `data/announcement.json` (`active`, `message`, `style`, `updated_at`) — not a DB row. `includes/header.php` reads it on every page render; `app/api/announcement.php` writes it. The message is escaped, then `nl2br`'d and auto-linked.

## Email

All mail goes through PHP's `mail()`. Post create/update/delete each notify `aperkel@uvm.edu`; bulk admin mail goes to `{username}@uvm.edu` with an HTML template inlined in `app/api/email.php` (UVM green `#154734` / gold `#FFD100`), plus a copy to the admin.

That address is plumbing, not a support channel. **The only contact route the site advertises is a DM to `@uvmsublets` on Instagram** — in both footers, on `landing.php`, on `s.php` and in the broadcast email template. The old `me@aaronperkel.com` mailto is gone from every user-facing surface; do not reintroduce an email address as the way to report a problem.

## Styling

Single stylesheet `css/style.css`, built on custom properties on `:root`: the UVM palette (`--green`, `--gold`, `--slate`, `--sky`, `--orange`, `--fog`), four state-tint sets (`--included-*`, `--tenant-*`, `--error-*`, `--notice-*`, each tint/ink/line), `--danger`, `--focus`, overlay and shadow tokens, and a seven-step type scale (`--text-xs` 0.75rem to `--text-3xl` 2.25rem). No hex value appears below `:root`, every font size is a `--text-*` token, and only weights 400 and 700 are used — `DESIGN.md` is the written system and Impeccable's detector (`impeccable detect`) checks against it. Form fields stay at 1rem, because iOS zooms the page on any field smaller than 16px. Light mode only — there is no `prefers-color-scheme` handling.

Type is **Bricolage Grotesque**, self-hosted: one variable WOFF2 (`assets/fonts/bricolage-grotesque-v9-latin.woff2`, Latin, weights 400–700 plus optical size) with a metric-matched Arial fallback (`'Bricolage Fallback'`, size-adjust 105%) so the swap does not shift layout. The file name carries the upstream version on purpose: woff2 is cached as `immutable` and a CSS `url()` cannot take `?v=`, so a changed font ships under a new name. `includes/header.php`, `landing.php` and `s.php` preload it; `landing.php` and `s.php` carry their own `@font-face` because they are self-contained. Licence: `assets/fonts/OFL.txt`. Font Awesome is loaded from a CDN kit with `defer` (it is a CSS-method kit, so nothing needs it before parse); Leaflet 1.9.4 and noUiSlider 15.6.1 come from cdnjs at exact versions with SRI `integrity` hashes, only on the pages that need them — bumping a version means updating its hash in `includes/header.php`. `landing.php` does not use this stylesheet — it carries its own inline copy of the design tokens.

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
an unknown id, a hidden or paused listing *and* a database outage, so it cannot
be used as an oracle for which ids exist. The one exception is a **taken**
listing behind a genuine token: that gets "This sublet has been taken", with the
generic preview image and nothing from the listing, not even its card lines.
Only a valid token reaches that branch, so it reveals nothing to someone
guessing ids.

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
Visibility still applies, and when it hides the listing, Browse says "The
listing you followed isn't up any more" above the cards (inside
`#listingsGrid`, so the first filter change clears it) without saying why.

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

**Browse filters live.** On `app/index.php` a filter change fetches the same page with the new query, swaps `#listingsGrid`'s contents and the board heading, and `replaceState`s the URL (`liveUpdate()`; stale requests are aborted, and any failure falls back to a normal page load, which also sends a lapsed CAS session through sign-in). Because the grid's contents are replaced, **anything a card responds to must be delegated on `#listingsGrid`** (click, keydown, image `error` in the capture phase); a per-card listener silently stops working after the first filter change. Map still submits its form, since its listings live in `MAP_SUBLETS` and the pins. Untouched sliders leave `min_price`/`max_price`/`max_distance` empty, so they are not filters; `build_listing_filters()` returns `count` for the phone's Filters badge. The **Roommate preference** filter (`open_to`, `LISTING_OPEN_TO_FILTERS` in `listing_fields.php`) is one choice of who the searcher is, "Open to men / women / nonbinary folks", and shows listings with no stated preference plus those whose preference includes that group. It was asked for in October 2026, when 18 of 38 listings stated a preference; it hides listings from the searcher only, so a poster's preference stays a preference. Links marked `data-carry-filters` (nav Browse/Map, the phone's List/Map switch) are rewritten to carry the current filters.

**The listing view uses history.** `openModal()` pushes a `{listing: id}` state so a phone's Back closes it; `closeModal()` steps back over that entry and the `popstate` handler calls `hideModal()`. Close through `closeModal()`, never by hiding the overlay directly, or the extra entry is left behind.

**The post form.** The address field is an ARIA combobox (`initAddressAutocomplete()`), and lat/lon come from either picking a suggestion or tapping the map (`initPostMap()`), which is the fallback when Nominatim cannot find an address. A picked address stays valid only while its text is unchanged; a hand-placed pin stays valid whatever the text says, since the pin is the location and the text is the student's label. On phones the map is moved into `#postMapSlot` under the address field and one-finger dragging is off, so the page scrolls past it. Photos accumulate across picks: `initImageUpload()` keeps the chosen `File`s and writes them back into the input through a `DataTransfer`, and caps them at `max_file_uploads` (PHP drops the rest silently). The submit button shows what is happening and ignores further presses, but is never `disabled`: a disabled submitter is left out of the form data, and Delete is recognised by its `name="action" value="delete"`. The status panel above the form (Pause, Resume, Mark as taken, Put it back up) is a row of separate one-button forms posting `action=status`, which redirect back with a 303; the listing form's own handler skips any `action=status` request, since it would otherwise save a listing from a form with no fields.

Everything lives in one closure, so **module state read during init must be declared above the dispatch block**. Function declarations hoist; `var` assignments do not. `SHARE_TILES` declared next to `initShare()` was still `undefined` when the dispatch called it, and the resulting throw landed after `shareEls` was assigned but before any listener was attached — the sheet opened, showed no tiles, and could not be closed or copied from, and the abort took the `?id=` deep link with it.

The listing view's state (`modalImages`, `currentPostId`, `currentSource`, `lastFocused`, `photoCache`, `galleryRequest`) is also declared above the dispatch, because `openSharedListing()` opens a listing during init. While it was declared further down, the deep-linked view lost its `currentPostId` and the admin's Delete button did nothing.

**The listing view's photos come with the listing.** Each Browse card carries `data-photos` and each `MAP_SUBLETS` row a `photos` array: `[{display, thumb}, …]` in gallery order, built by `listing_photos()` (`includes/thumbnail.php`, one query per page) with `?v=` on every URL and `thumb` null where no `_thumb.webp` exists. `openModal()` therefore knows the count and the URLs without a request, and `renderGallery()` draws the arrows, dots and "1 / N" in the same frame the view opens. Until October 2026 the view opened on the card image alone and fetched `api/images.php`, so the controls waited that round trip (1–2 s on a phone). `images.php` is now only the fallback for a listing that arrives without a list, and `galleryRequest` stops a late answer from filling the next listing's gallery. The photo is held transparent (`.is-loading`) until it loads and then fades in over `#modalImageUnder`, its thumbnail, so a previous photo never shows under the new counter; `#modalImageUnder` is excluded from the broken-image handler, since a missing stand-in must not cover the real photo. `preloadPhoto()` fetches each display image once: both neighbours of the current photo, a card's first photo on `pointerdown`/`touchstart` (delegated on `#listingsGrid`, about 100 ms before the click), and a pin's first photo when its popup opens.

`copyToClipboard()` / `flashCopied()` are shared by the contact panel and the share sheet. They exist because `navigator.clipboard` is undefined on insecure origins and rejects when the document is not focused — hence the `execCommand` fallback and the visible failure state.

## API layer (`app/api/`)

Form-encoded POST in, JSON out — not REST. Endpoints dispatch on `$_POST['action']`; deletes are tunneled as `POST` with `_method=DELETE` (`images.php`). Admin endpoints call `require_admin()` immediately after setting the JSON header. `fetch()` calls in `app.js` use relative `api/...` paths, resolved against `/app/`.

| File | Notes |
|---|---|
| `posts.php` | admin-only delete of a post or of all posts by a user, and `set_status` (open / paused / taken; the Posts tab's select) |
| `semesters.php` | admin-only add/toggle/delete; refuses to delete a semester any listing runs for (`sublet_semesters`), and to reactivate one that is archived |
| `archive.php` | admin-only. GET `action=preview` is the dry run; POST `action=archive` (`code`, `confirm` = the code typed back) archives a hidden semester; POST `action=delete_tarball`. See "Semester archive" |
| `images.php` | GET `sublet_id` lists a listing's photos (the listing view's fallback, ordered like `listing_photos()`; returns `display_url` and `thumb_url`). Admin **or** post owner: delete (`_method=DELETE`), `set_cover`, `move`. Admin only: GET `action=inventory` and `action=orphans` (the Images tab), POST `bulk_delete`, `delete_orphans`, `make_thumbs`. Every change renumbers and re-covers the listing (see "Images") |
| `announcement.php` | GET public, POST admin-only |
| `email.php` | admin-only bulk `mail()` to `{username}@uvm.edu` |
| `events.php` | the activity beacon: POST from `track()` in app.js, `require_same_origin()`, always 204 (429 when rate-limited); see "Activity log" |
| `geocode.php` | proxy to Nominatim (no key needed) |
| `allowlist.php` | admin-only `add`/`remove`/`rebuild` of approved & blocked netids; rewrites `app/.htaccess` and **undoes its own DB change if that write fails**, so the table and the file never disagree |

## Data model

There is no schema/migration file in the tree; the shape below is what the queries imply.

- **`sublets`** — effectively **one row per user**. `post.php` treats `username` as the key: it looks up the user's post to decide create-vs-edit, and updates with `WHERE username = ?`. Also holds `image_url`/`thumbnail_url`, `price`, `address`, `lat`/`lon`, `semester` (the listing's *first* semester; all of them are in `sublet_semesters`), `posted_at`, contact fields, `utility_*`, and `amenity_*` flags, plus `status` (`open`/`paused`/`taken`, default `open`, indexed) and `status_changed_at` (NULL until the first change), added by hand in October 2026. See "Listing visibility".
- **`sublet_images`** — `sublet_id`, `image_url`, `sort_order`. The first image by `sort_order` is the card image (`sublets.image_url`). Since October 2026 every change (`images.php`) renumbers a listing's photos 0..n-1 through `renumber_listing_photos()`, which also moves the card image to the first one; before that, deleting the cover promoted the next photo without renumbering, and listings untouched since may still have no 0. Test for "first", not for 0. Rows cascade-delete with their listing (`ON DELETE CASCADE`); the files do not.
- **`sublet_semesters`** — `sublet_id`, `semester_code`, `PRIMARY KEY` on both, `ON DELETE CASCADE` from `sublets`. One row per semester a listing runs for; several only when they are back to back. Created by hand in October 2026 with `CREATE TABLE … SELECT id, semester FROM sublets`, so its columns copy `sublets`' exact types and collation (a mismatch makes MySQL refuse the joins). No foreign key to `semesters`, since unmapped codes must keep working. See "Semesters per listing".
- **`semesters`** — `code`, `name`, `active`, `sort_order`, `archived_at` (NULL until archived). `code` joins to `sublets.semester`; queries `COALESCE(sem.name, s.semester)` so unmapped codes still render.
- **`semester_archives`** — one row per archived semester (`UNIQUE` on `semester_code`): `semester_name`, `archived_at`, `archived_by`, `listings`, `taken`, `price_median`/`price_min`/`price_max`, `views`, `contacts`, `shares`, `share_arrivals`, `bytes`, `tarball`, and optionally `photos`. Totals only, never who. See "Semester archive".
- **`listing_events`** — the activity log: `listing_id`, `poster_username`, `actor_key`, `semester`, `type`, `source`, `target`, `dedupe_key` (UNIQUE), `created_at`. No foreign key to `sublets`, on purpose. See "Activity log".
- **`contact_logs`** — gone. It logged only Email/Call taps, by NetID; in October 2026 its rows were copied into `listing_events` by `~/sublet-scripts/migrate_contact_logs.php`, and the table was dropped after the first semester archive. Nothing refers to it.
- **`allowed_users`** — `uid`, `kind` (`allow`/`block`), `note`, `added_by`, `added_at`. `UNIQUE` on `uid` alone, not `(uid, kind)`: a netid is on one list or the other, never both. Source of truth for the generated `Require` line — see "Access allowlist". The `note` column is the reason someone has access ("gap year, back Fall 2026") and stays in the database; it never reaches `app/.htaccess`, which is committed to a public repo.

## Activity log

`includes/events.php`, `app/api/events.php`, and `track()` in `app.js`. Each event is one beacon (`navigator.sendBeacon`, falling back to `fetch` with `keepalive`), and the server decides what counts:

- **No NetIDs are stored.** The actor is `actor_key()`: the first 16 hex characters of `hash_hmac('sha256', 'actor:' . $netid, share_secret())`. The `actor:` prefix is domain separation from `share_token()`'s `sublet:` input under the same secret. It is pseudonymous, not anonymous (whoever holds the secret can test a given NetID), and every reader counts distinct keys, so no page says who did what.
- **Excluded:** everything the admin does, and a poster's own views and contacts on their own listing. A poster's own shares (`share_open`, `share_target`) do count, because sharing your own listing is how listings travel.
- **Deduplicated** per person, listing and day through `dedupe_key`: `listing_open`, `map_pin_open`, `share_open` and `share_arrival`. Contacts and share targets count every time.
- **Rate-limited** to `EVENTS_PER_MINUTE` stored events per person.
- **The poster and semester come from the listing**, never the request. The semester is stored on each row so that archiving a semester can delete its events even after the listing itself is gone.
- **Whitelists:** types are `EVENT_TYPES` (with `CONTACT_EVENT_TYPES` and `SHARE_EVENT_TYPES` as groupings), sources are `EVENT_SOURCES` and share targets are `SHARE_TARGETS`. The columns are VARCHAR, so adding one needs no DDL.
- **Arrivals:** `s.php` logs nothing, because crawlers read it. Its sign-in link adds `via=share`, which `index.php` passes on as `SUBLET_CONFIG.openSource`, so `openSharedListing()` logs `share_arrival` as `share-link` rather than `deeplink`.

The readers are `listing_activity()` (people who viewed, got in touch, or did both, which is conversion; plus share taps and arrivals), `share_target_counts()`, `view_source_counts()` and `daily_activity()`. They feed the admin **Activity** tab (whose ranges reload the page as `?range=open|30d|all#activity`; open listings show with zeros, paused and taken ones only once counted, tagged) and the poster's line on `post.php`. The poster's line counts from `max(LISTING_EVENTS_SINCE, posted_at)`, since nothing but contacts was recorded before the log began. The footer discloses that views and contact taps are counted.

## Listing visibility (semester and status)

A listing is on the board when its semester is visible **and** its poster has not taken it down. Deactivating a semester in the admin portal hides all of its listings; a poster can pause their own listing or mark it taken. The rule lives in one place — `includes/visibility.php`, required by `db.php` — as constants used to build queries:

```php
VISIBLE_SEMESTER_JOIN    // LEFT JOIN semesters sem ON s.semester = sem.code   (the first semester's name)
VISIBLE_SEMESTER_WHERE   // EXISTS one of the listing's sublet_semesters with (no semesters row OR active = 1)
PUBLIC_LISTING_WHERE     // (VISIBLE_SEMESTER_WHERE AND s.status = 'open')
```

A listing that runs for several semesters is visible while **any** of them is: a Summer and Fall listing stays up for Fall once Summer is deactivated, and goes only when both are.

**Every query that shows listings to students uses `PUBLIC_LISTING_WHERE`.** `VISIBLE_SEMESTER_WHERE` on its own is for places that ask about semesters alone: the admin's "Hidden" flag and the Images tab. `s.php` is the one public reader that looks past status, and only to tell a taken listing apart (see "Sharing a listing").

**Status** (`LISTING_STATUSES`, set only through `set_listing_status()`, which lets MySQL fill `status_changed_at`):

| | Browse, Map, landing, slider bounds | Share link (`s.php`) | Who sets it |
|---|---|---|---|
| `open` | shown | the normal interstitial | default; Resume / Put it back up |
| `paused` | hidden | the one "isn't available" page | the poster on `post.php`, or the admin's Posts tab |
| `taken` | hidden | "This sublet has been taken" | the same |

Saving the listing form never changes the status; the success banner says when the listing is still paused or taken. Nothing is deleted, and the poster still sees and edits their listing. Archiving counts `taken` listings into `semester_archives.taken`.

Two decisions made with the status, October 2026: a **broadcast "all"** email goes only to posters whose listing is on the board (paused and taken posters are left out; picking a semester still reaches everyone in it), and the **Activity tab** keeps paused and taken listings, labelled, with the counts from while they were up, because a taken listing is where the conversions are.

The `sem.code IS NULL` half is load-bearing, not defensive padding: listings whose semester code has no row in `semesters` must stay visible. Hiding a listing takes an explicit deactivation; a code that is merely missing from the table (a legacy code, or one an admin has not added yet) must fail open, not silently blank those listings. A naive `sem.active = 1` would do exactly that.

`PUBLIC_LISTING_WHERE` is applied in `includes/header.php` (dropdown + both slider bounds), in `build_listing_filters()` (`includes/listing_query.php`, shared by `app/index.php` and `app/map.php`, and so the photo lists and `MAP_SUBLETS`), in `landing.php`'s photo strip and counts, and in `share-card.php`. **Any new query that lists sublets to the public needs it too.** `app/admin.php` deliberately does *not* filter — it shows every listing, flags the hidden ones via a `NOT (VISIBLE_SEMESTER_WHERE) as is_hidden` column, and gives each a status select in the Posts tab.

Deactivation is reversible and deletes nothing; archiving, the step after it, is what deletes (see "Semester archive"). `app/post.php` keeps a deactivated semester the listing already has in its row of semester pills, ticked and marked "(closed)", so saving never silently drops it, and shows a notice when every one of the listing's semesters is closed.

## Semesters per listing

A listing runs for one semester or several **back to back** (`includes/semesters.php`). Back to back is read from the semester's *name*, not `sort_order`: Spring, Summer, Fall, then the next Spring (`semester_key()`), so Fall 2026 + Spring 2027 is fine and Spring + Fall without Summer is a gap. A gap means posting again once the first sublet is over. A name that is not "<Term> <year>" can only stand alone. One price covers all of them.

- **Storage:** `sublet_semesters`, written only by `set_listing_semesters()`, which also points `sublets.semester` at the first. Everything that needs one semester (the tag on each activity event, the archive's bookkeeping) keeps reading `sublets.semester`, and the code before the table still reads a correct value after a rollback.
- **Posting:** `post.php` shows a pill per open semester, in calendar order, plus any closed one the listing has. `initSemesterPicker()` greys out (`aria-disabled`) whatever would leave a gap; the server checks the same rule, that every code was one it offered, and that at least one is ticked.
- **Labels:** `listing_semesters()` fetches a page's semesters in one query and `with_semester_labels()` turns them into `semester_name`: "Summer & Fall 2027", "Fall 2027 & Spring 2028", "Spring–Fall 2027". Public pages label the open semesters only; the admin sees all. The Browse badge, map popups, the listing view, the share sheet, `s.php` and the share card all use it, so the share card's picture and title agree.
- **Filtering:** the semester filter means "available in", so a Summer and Fall listing is found under either. The dropdown lists the open semesters of listings on the board (`board_semesters()`).
- **Admin:** a semester's post count, its email audience and the delete guard all count every listing that runs for it. The Images tab files storage under the first semester and filters by any.

The rule also governs **who gets a broadcast email**: `$emailableUsers` in `app/admin.php` and the `type=all` query in `app/api/email.php` both filter by `PUBLIC_LISTING_WHERE`, so nobody whose listing is hidden, paused or taken is swept into a mass mail. Those two must change together or the count in the UI stops matching what is actually sent. Picking a specific semester is exempt — that is an explicit choice, deactivated or not.

## Semester archive

A semester goes **active → hidden → archived**. Hiding (`active = 0`) is the reversible step above. Archiving is the last one and removes the semester's listings for good. It lives in `includes/archive.php`, is reached through `app/api/archive.php`, and the admin's **Semesters** tab drives it.

1. **Dry run.** `archive_plan()` lists exactly what would go: listings (with their status; the taken ones are counted into the archive row). A listing that also runs for a semester that is not archived **stays** (`archive_split()`): it is listed separately ("Staying on the site"), loses only this semester, keeps its photos, and has its activity re-tagged to its next semester rather than deleted. The rest of the plan covers photo rows, every file on disk (originals with their `_thumb`/`_display` copies, found from all three image columns), bytes, events and cached share cards. It refuses an active semester, an archived one, a code with no row, a file shared with a listing that stays, and a schema that lacks a column it writes. Read-only.
2. **Confirmation.** The semester code, typed back. Nothing else is accepted.
3. **Backup.** A tarball to `~/sublet-image-backups/semester-<code>-<UTC>.tar.gz` (mode 0600, outside the docroot). It holds the files under their own names plus `manifest.json`, which maps listing ids to file names and carries no personal details. It is read back with `tar -tvzf` and must match the plan exactly, names and sizes, or nothing else happens.
4. **Database.** `archive_commit()` runs one transaction. It re-reads the semester, both groups of listings and the photo paths `FOR UPDATE` and refuses if anything changed since the plan; otherwise it inserts the `semester_archives` row (totals of the listings that go), takes the semester off the staying listings (their `sublet_semesters` row, `sublets.semester` and event tags move on), deletes the semester's remaining `listing_events` and the listings that go (photo and semester rows cascade) and sets `semesters.archived_at`. On any failure it rolls back and deletes the tarball, since nothing was removed.
5. **Files.** Only after the commit: `delete_image_files()` on every stored path, and the listings' share cards from `public/share/`. A file that will not delete becomes an orphan for the Images tab, never a row pointing at nothing.

The request is POST-only, admin-only and `require_same_origin()`-checked, ignores a closed tab (`ignore_user_abort`) and holds a lock file so a double click cannot run two archives. The Semesters tab lists past archives and the tarballs; only names matching `ARCHIVE_TARBALL_PATTERN` are listed or deletable, so the pre-backfill backup in the same folder cannot be removed from the web.

`semesters.archived_at` and `semester_archives` were created by hand. `archive_schema_report()` compares them column by column with the planned DDL (`ARCHIVE_TABLE_COLUMNS`), and the tab shows the result. `photos` was in the planned DDL but is optional: the row is written without it when the column is absent.

## Cleaning up public/images

Every file here is an upload or one of its generated siblings, all named `<32 hex>.<ext>`, `<32 hex>_thumb.webp` or `<32 hex>_display.webp`. A file is an orphan when neither its own name nor (for a sibling) its original's name appears in `sublet_images.image_url`, `sublets.image_url` or `sublets.thumbnail_url`. Any cleanup must check all three columns and protect the `_thumb.webp` and `_display.webp` siblings of everything it keeps. (The favicon used to live here and was the classic casualty of a DB-only scan; it is now `assets/favicon.svg`.)

The admin **Images** tab's "Orphans & missing" view does this (`image_orphans()` in `includes/image_admin.php`). It also keeps any file the source code names as `public/images/<name>`, skips dotfiles (writes in progress), and lists rows whose file is missing. Deleting takes the number of files typed back, and `delete_orphans()` re-runs the sweep and deletes only names that are still orphans.

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
| `x_display.webp` | `make_display_image()` (ImageMagick) | ≤1600px, q80, ICC only, ~90–170 KB | modal gallery, map modal, post edit page — via `display_src()`, which falls back to the original |
| `x_thumb.webp` | `make_thumbnail()` (ImageMagick) | 600px wide, q80, ICC only | listing cards, map popups, landing strip — via `sublets.thumbnail_url`; under the gallery photo while it loads — via `listing_photos()`; the admin Images tab |

Every image gets a display copy and, since October 2026, a thumbnail: `post.php` makes both for every upload, and `sync_listing_cover()` makes one on demand for a photo that becomes the cover. Older photos that only had a display copy get theirs from the Images tab's **Make thumbnails** button (`make_missing_thumbnails()`, a batch per request). Until then the tab, and the gallery's placeholder, fall back to the display copy. All three writers go through `convert_into_place()` (temp dotfile + `rename()`), so a request never reads half an image. Thumbnails were GD until October 2026, which dropped the ICC profile.

Two one-off scripts outside the docroot brought the existing files to this state in October 2026, and both are idempotent if they ever need re-running: `~/sublet-scripts/backfill_images.php` (normalize, display copies, thumbnails) and `~/sublet-scripts/migrate_random_names.php` (renames, with `rename-journal.json` mapping every old name to its new one). The pre-backfill originals, under their old `{netid}_…` names, are in `~/sublet-image-backups/`; the journal is how to find a given file there.

## Announcements

The site-wide banner is a flat file, `data/announcement.json` (`active`, `message`, `style`, `updated_at`) — not a DB row. `includes/header.php` reads it on every page render; `app/api/announcement.php` writes it. The message is escaped, then `nl2br`'d and auto-linked.

## Email

All mail goes through PHP's `mail()`. Bulk admin mail goes to `{username}@uvm.edu` with an HTML template inlined in `app/api/email.php` (UVM green `#154734` / gold `#FFD100`), plus a copy to the admin.

**Admin notices** (`includes/notify.php`) go to `aperkel@uvm.edu` when a poster creates, edits, pauses, resumes, marks taken or deletes a listing, and when the admin deletes one from the Posts tab. Until October 2026 each was one line ("updated their sublet post"); now each carries the listing. A new listing lists every field; an edit lists only the fields that changed, before and after, and names them in the subject ("Listing edited (price, semesters, description and 1 more)"); a status change or delete says what the listing was. A save that changes nothing sends nothing. The notices are built from `listing_snapshot()` arrays, so a new field on the post form needs a row in `notice_fields()` or edits to it go unreported. They are HTML, with the subject `mb_encode_mimeheader`'d and the body quoted-printable, for the same relay reason as the broadcast.

`includes/notify_samples.php` sends four sample notices on invented listings (no database): `/usr/bin/php82 includes/notify_samples.php`, or `--preview <dir>` to write them as HTML instead. It refuses to run outside the CLI, and `includes/` is not served anyway.

That address is plumbing, not a support channel. **The only contact route the site advertises is a DM to `@uvmsublets` on Instagram** — in both footers, on `landing.php`, on `s.php` and in the broadcast email template. The old `me@aaronperkel.com` mailto is gone from every user-facing surface; do not reintroduce an email address as the way to report a problem.

## Styling

Single stylesheet `css/style.css`, built on custom properties on `:root`: the UVM palette (`--green`, `--gold`, `--slate`, `--sky`, `--orange`, `--fog`), four state-tint sets (`--included-*`, `--tenant-*`, `--error-*`, `--notice-*`, each tint/ink/line), `--danger`, `--focus`, overlay and shadow tokens, and a seven-step type scale (`--text-xs` 0.75rem to `--text-3xl` 2.25rem). No hex value appears below `:root`, every font size is a `--text-*` token, and only weights 400 and 700 are used — `DESIGN.md` is the written system and Impeccable's detector (`impeccable detect`) checks against it. Form fields stay at 1rem, because iOS zooms the page on any field smaller than 16px. Light mode only — there is no `prefers-color-scheme` handling.

Type is **Bricolage Grotesque**, self-hosted: one variable WOFF2 (`assets/fonts/bricolage-grotesque-v9-latin.woff2`, Latin, weights 400–700 plus optical size) with a metric-matched Arial fallback (`'Bricolage Fallback'`, size-adjust 105%) so the swap does not shift layout. The file name carries the upstream version on purpose: woff2 is cached as `immutable` and a CSS `url()` cannot take `?v=`, so a changed font ships under a new name. `includes/header.php`, `landing.php` and `s.php` preload it; `landing.php` and `s.php` carry their own `@font-face` because they are self-contained. Licence: `assets/fonts/OFL.txt`. Font Awesome is loaded from a CDN kit with `defer` (it is a CSS-method kit, so nothing needs it before parse); Leaflet 1.9.4 and noUiSlider 15.6.1 come from cdnjs at exact versions with SRI `integrity` hashes, only on the pages that need them — bumping a version means updating its hash in `includes/header.php`. `landing.php` does not use this stylesheet — it carries its own inline copy of the design tokens.

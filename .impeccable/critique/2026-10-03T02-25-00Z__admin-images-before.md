---
target: admin image manager (Posts tab → "Manage Images" dialog), before Phase 5
total_score: 13
max_score: 40
na_heuristics: 
p0_count: 1
p1_count: 4
target_identity: "file:app/admin.php#imageModal"
timestamp: 2026-10-03T02-25-00Z
slug: admin-images-before
---
Method: detector + manual review. The deterministic half is `impeccable detect` 4.1.0 from npm over app/admin.php and css/style.css (the plugin's 4.5.0 is on Silk, not in the cloud session this ran in). The review half follows the same ten heuristics by hand, from the source (app/admin.php:266-331, initPostManagement() in js/app.js, .admin-image* in css/style.css) and from screenshots of the dialog at 1280px and 390px on local fixture data (12 listings, 42 photos). It is not the plugin's sub-agent critique.

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|---|---|---|
| 1 | Visibility of System Status | 1 | No listing name, no photo count, no cover marker, no sizes; the row's count does not change after a delete |
| 2 | Match System / Real World | 2 | "Manage Images" names nothing; the "Images" stat counts DB rows, not the 340 files / 220 MB on disk |
| 3 | User Control and Freedom | 1 | No set-cover, no reorder, no undo; one listing at a time; no Escape |
| 4 | Consistency and Standards | 2 | Borrows the listing view's .modal-container, so on a phone it becomes a full-screen white sheet holding 60px tiles; inline styles |
| 5 | Error Prevention | 1 | On touch, the invisible full-tile Delete button is what the first tap hits, so tapping a photo to look at it asks to delete it |
| 6 | Recognition Rather Than Recall | 1 | 60px squares are too small to judge a photo, which is the reason to open the dialog |
| 7 | Flexibility and Efficiency | 1 | No bulk delete, no filter by semester or hidden, no way to find orphans or missing files |
| 8 | Aesthetic and Minimalist Design | 2 | Visually quiet, but a whole tile turning red on hover is louder than the action warrants |
| 9 | Error Recovery | 1 | Errors go to alert(); a failed listing delete in the same tab is silent |
| 10 | Help and Documentation | 1 | Nothing says the last photo cannot be deleted until the alert does |
| **Total** | | **13/40** | **Poor** |

## Design Specificity Verdict

Deterministic scan: 7 advisories, all pre-existing and all in app/admin.php outside the dialog (five off-ramp `font-size: 0.8rem` inline styles at lines 189, 246, 673, 682, 724; `#0f1a16` twice at 639 and 761). The dialog's own markup has `style="max-width: 500px;"`, `style="padding: 1.5rem;"` and `style="margin-bottom: 1rem;"`, which the detector does not flag because they are on-scale values, but they are still inline styles the token system cannot reach.

LLM assessment: it is a stub, not a design. It answers "what photos does row N have?" and nothing an admin actually comes to it for: which listings are eating storage, which photos are broken, which files nothing references, which photo is the cover.

## Overall Impression

There is no image manager, only a delete button per photo hidden behind a per-row popup. Phase 5's archive also needs to know what a semester's photos weigh, and nothing here can say.

## What's Working

- Delete goes through images.php, which refuses a listing's last photo and promotes the next one to cover, with a thumbnail.
- It loads the display copies, not originals that once ran to 20 MB.

## Priority Issues

[P0] Tap-to-delete on touch. `.delete-image-btn` is `position: absolute; inset: 0; opacity: 0`, revealed on `:hover`. A touch screen has no hover: the first tap lands on the invisible button and fires the delete confirm. Fix: an always-visible, labelled delete control that is not the photo itself, and a selection checkbox for bulk work.

[P1] No overview. One listing at a time, no grouping by semester, no storage totals, no orphan or missing-file view. The "Images" stat counts sublet_images rows. Fix: a dedicated Images tab: every listing's photos grouped, cover first, with per-semester totals of files and bytes on disk, and filters for semester, hidden and orphans.

[P1] Wrong image size for the job. 60px squares drawn from 1600px display copies: about 1.5 MB for a 13-photo listing, and too small to judge. Fix: 600px `_thumb.webp` for every photo, shown at a size that can be judged (about 160px), with dimensions, size and format under each.

[P1] No cover or order control. The cover is whichever photo sorts first, and deleting it promotes the next without renumbering (3 live listings have no sort_order 0). Fix: Cover badge, "Make cover", move left/right, and sort_order renumbered 0..n on every change.

[P1] Not a dialog. No role, aria-modal, label, Escape or focus handling; the close button is an unnamed "×"; images are alt="Image"; inline styles; the row's photo count goes stale after a delete. Fix: build it as a tab panel rather than a dialog, with named controls and live counts.

## Persona Red Flags

Aaron (admin, on his phone between classes): opens a listing's photos to check one and deletes it with the first tap; cannot tell which listing he is looking at; cannot see what a semester weighs before archiving it.

Sam (screen reader/keyboard): an unnamed "×", seven "Image" images and seven unnamed buttons; focus stays on the page behind; Escape does nothing.

## Minor Observations

- `confirm()`/`alert()` for every action.
- The dialog does not lock focus or restore it to the row's button on close.
- `.admin-image` is a fixed 60px with no responsive step.

## Questions to Consider

- If the only reason to open it is to judge or remove a photo, what is the smallest size at which that judgement can be made?
- Which question comes first when the admin opens it: "what is broken?", "what is big?" or "what is this listing's cover?"

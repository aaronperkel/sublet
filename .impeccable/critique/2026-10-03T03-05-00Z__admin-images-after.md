---
target: admin Images tab and Archive a semester card, after Phase 5
total_score: 32
max_score: 40
na_heuristics: 
p0_count: 0
p1_count: 0
target_identity: "file:app/admin.php#tab-images,#archiveCard"
timestamp: 2026-10-03T03-05-00Z
slug: admin-images-after
---
Method: as in admin-images-before: `impeccable detect` 4.1.0 from npm over app/admin.php, css/style.css, includes/listing_modal.php, app/index.php, app/map.php and app/post.php, plus a manual review against the same ten heuristics from source and screenshots at 1280px and 390px on local fixture data (Chromium, Font Awesome blocked, so icon-only controls render blank in the screenshots). Not the plugin's sub-agent critique.

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|---|---|---|
| 1 | Visibility of System Status | 4 | Totals per semester and for the folder, sizes and dimensions per photo, Cover label, counts update after every change; thumbnail backfill shows progress |
| 2 | Match System / Real World | 3 | "Photos" (rows) and "On disk" (bytes) in the header; "Orphans & missing" is jargon, explained in one sentence under it |
| 3 | User Control and Freedom | 3 | Set cover, reorder, bulk select with Clear; still no undo for a delete |
| 4 | Consistency and Standards | 3 | Reuses the segmented range control, admin tables, alerts and tokens; arrow buttons are icon-only like the gallery's |
| 5 | Error Prevention | 4 | Delete is a visible, labelled, two-press control off the photo; last photo protected singly and in bulk; typed count for orphans, typed code for archives; server re-checks |
| 6 | Recognition Rather Than Recall | 3 | 160px tiles from thumbnails; photos with no thumbnail yet fall back to the display copy until "Make thumbnails" runs |
| 7 | Flexibility and Efficiency | 3 | Semester and hidden filters, bulk delete, one listing from the Posts tab; no keyboard reordering beyond the buttons |
| 8 | Aesthetic and Minimalist Design | 3 | Calm, but a seven-photo listing is a long block on desktop; group headers carry everything needed |
| 9 | Error Recovery | 3 | Errors appear in the group they happened in (role=alert), no alert(); an archive failure says nothing was changed |
| 10 | Help and Documentation | 3 | One-line explanations above the archive, the orphan list and the photos (the first photo is the card and the share preview) |
| **Total** | | **32/40** | **Good** |

## Design Specificity Verdict

Deterministic scan: 6 findings, all advisories and all pre-existing inline styles elsewhere in app/admin.php (four `font-size: 0.8rem`, `#0f1a16` twice). The seventh from the before scan, the semester row's inline 0.8rem, is gone with the rewrite of that row. No findings in any new markup or CSS: every new color is a token, every size on the scale, every radius 6/8/12px. The modal's second `<img>` (the stand-in thumbnail) is covered by the existing listing_modal.php broken-image ignore.

LLM assessment: a working admin surface rather than a stub. It answers the questions the old dialog could not: what is big, what is broken, what is the cover, what would archiving this semester remove.

## Overall Impression

The tab now makes destructive work deliberate without making routine work slow. What remains is polish: no undo, and icon-only arrows.

## What's Working

- No control sits on top of a photo except the selection box, so a tap on a phone can no longer delete.
- Two-press delete, typed count for orphans, typed code for archives: three levels of friction for three levels of risk.
- The archive's dry run shows the exact files, bytes and listings, and the server refuses if anything changed between that list and the commit.

## Priority Issues

[Fixed in this pass, was P1] Moving or covering a photo changed the Browse card with no warning. The tab now says, under its summary, that each listing's first photo is its card on Browse and Map and its share preview.

[P2] No undo after deleting a photo. The files are gone at once. Fix: none planned; the two-press control is the guard. Archived photos are recoverable from the tarball.

[P2] Icon-only arrow buttons. They carry aria-labels ("Move photo 2 of 5 earlier") but no visible text; on a 36px button that is the norm here (the gallery's arrows are the same).

## Persona Red Flags

Aaron (admin, on his phone): photos are 120px rows beside their controls; the bulk bar stays at the bottom; nothing deletes on the first tap.

Sam (screen reader/keyboard): every control is a named button; the selection box is a labelled checkbox; status lines are role=status, errors role=alert; the archive panel takes focus when it finishes. The tab is only built on first open, so a screen reader hears "Loading…" first.

## Minor Observations

- Lazy-loaded tiles show the Fog ground until scrolled to; fine, but a long scroll on a slow connection shows grey boxes.
- The orphan list shows originals at 64px straight from the original file.

## Questions to Consider

- Should posters get "Make cover" and reorder on post.php? The API already allows the owner.

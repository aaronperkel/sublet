# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

UVM students on both sides of a sublet, weighted equally:

- **Posters**: a student leaving their place for a semester who needs someone to take over the lease. They post one listing (photos, price, address, semester, bedrooms/bathrooms/roommates, utilities, amenities, contact details), then share it.
- **Seekers**: a student who needs a place for a specific semester near campus. They filter and compare listings, look at photos and the map, and contact the poster.

Access is UVM students via NetID single sign-on, plus a named allowlist for people who have lost the student affiliation but still belong (alumni, gap-year students), managed in the admin portal. One admin (the site's builder) runs moderation, semesters, announcements and broadcast email.

## Product Purpose

One free, searchable place for UVM sublets, so students stop hunting through Facebook groups, Instagram story posts, r/UVM and group-chat word of mouth. Success is a match: a seeker finds a fit and reaches the poster, and a poster's place gets taken over. The site records contact clicks (email or phone) as its signal that a match conversation started; the conversation itself happens off-site.

## Positioning

- **Everything in one place, structured**: every listing carries the same comparable facts (price, semester, distance from campus, size, amenities), filterable and sortable, instead of free-text posts scattered across feeds.
- **Scam-free because it is UVM-only**: every poster and every browser has signed in with a UVM NetID. Marketplace/Craigslist-style listings cannot offer that.
- **Reaches beyond your own circle, then travels back into it**: a listing can be shared into group chats and Instagram/Snapchat stories as a public preview link that unfurls with a card, without exposing private details.
- **Free and student-run**: no fees, no ads, no landlord or broker in the middle.

## Operating Context

- People arrive from `go.uvm.edu/sublet` (the link published everywhere), the `@uvmsublets` Instagram account, and share links (`/s/<id>-<token>`) sent in group chats and stories. Everything except the landing page and share previews sits behind UVM CAS sign-in.
- Listings are organized by semester (Fall, Spring, Summer). The admin activates or deactivates semesters, and a deactivated semester's listings disappear from the site without being deleted.
- Contact happens off-site: the site prepares an email draft or shows a phone number for calling or texting, and logs that the contact was initiated.
- Sharing is built for phones: the share sheet hands a story graphic to the OS share sheet so Instagram and Snapchat appear as targets.
- The only support channel the site advertises is a DM to `@uvmsublets` on Instagram.

## Capabilities and Constraints

Capabilities (shipped):

- Browse grid with filters (price range, semester, distance from campus, required amenities, price negotiable) and sorting (newest, oldest, price, closest).
- Map view of the same filtered listings.
- Listing detail with a photo gallery, place and roommate details, utilities and amenities, and email/phone contact.
- Post and edit a listing (one per student), with photos, address autocomplete and a map pin, rejecting locations more than 50 miles from campus.
- Public share links and generated preview/story images.
- Admin portal: posts, semesters, site-wide announcement banner, broadcast email, contact log, access allowlist.

Constraints:

- PHP on UVM Silk shared hosting. The repository is the live document root, so edits are live immediately. No build step, no Node, no test suite. MySQL schema changes are applied by hand in phpMyAdmin.
- Authentication is Apache CAS. PHP has no sessions or login form.
- Privacy boundary: address, description, poster name, NetID, contact email and phone are visible only to signed-in users. The public share surface is limited to price, semester, bed/bath/roommate count and distance from campus.
- Uploaded photos are stored under random names with GPS and other metadata stripped.
- One listing per student. A listing belongs to exactly one semester.

Open (not decided):

- A possible later move off Silk (e.g., Vercel with magic-link sign-in) and a possible rename to "Catamount Sublets". The user's own framing: "not a now problem".
- Roadmap items listed publicly on the landing page but not built: sublets over multiple semesters, bedroom/bathroom filters, roommate-preference filters, custom date ranges, on-site chat, saved searches and favorites, roommate matching.

## Brand Commitments

- Name: **UVM Sublets**. Public link: `go.uvm.edu/sublet`. Instagram: `@uvmsublets`. Footer credit: "Built for UVM students by Aaron Perkel."
- **Independent student project, not affiliated with or endorsed by UVM.** It uses UVM sign-in and draws on UVM's colors, but must not look like an official university service or use official UVM logos or wordmarks.
- Students are addressed as fellow students ("connect with fellow Catamounts"), in plain, friendly language.

## Evidence on Hand

Real data, as of 2026-10-02:

- 45 listings in the database, 34 currently visible (Fall 2026, Spring 2027, Summer 2027 active), and 150 listing photos.
- 107 contact actions logged since 2026-03-22 (90 email, 17 phone).
- Social graphics: `assets/social/` (link preview, story and square graphics drawn with the short link).

There are no testimonials, reviews, user counts, press or partnerships. Do not fabricate any.

## Product Principles

1. **Trust comes from verification, and privacy protects it.** Only verified UVM students get in, and nothing private leaves the sign-in wall. A public surface shows only what a preview card needs.
2. **Both sides are the product.** An improvement for seekers must not make posting harder, and the reverse.
3. **Structure is the reason to switch.** Comparable, filterable facts (price, semester, distance) are what scattered posts cannot offer. Keep them consistent and scannable.
4. **Listings travel.** A listing should be easy to send back into the group chats and stories where students already are, without leaking private details.
5. **Free, independent, student-run.** No fees, no ads, no middlemen. Never present as an official UVM service.

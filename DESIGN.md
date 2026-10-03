---
name: UVM Sublets
description: Sublet listings for UVM students, pinned to one board.
colors:
  catamount-green: "#154734"
  catamount-green-hover: "#1a5c44"
  gold: "#FFD100"
  gold-hover: "#e6bc00"
  slate-ink: "#00313C"
  focus: "#2f86c9"
  focus-sky: "#489FDF"
  alert-orange: "#DC582A"
  danger: "#c44e22"
  danger-hover: "#b5401a"
  fog: "#F7F7F7"
  card-white: "#FFFFFF"
  text-secondary: "#4a5e63"
  text-muted: "#5f7378"
  border: "#dce1e3"
  border-light: "#eef1f2"
  included-tint: "#e8f5e9"
  included-ink: "#1a6b4a"
  included-line: "#c8e6c9"
  tenant-tint: "#fff8e1"
  tenant-ink: "#7a6100"
  tenant-line: "#ffecb3"
  error-tint: "#fce8e3"
  error-ink: "#b5401a"
  error-line: "#f5c6ba"
  notice-tint: "#eaf3fb"
  notice-ink: "#0b4f7d"
  notice-line: "#c6dff3"
typography:
  display:
    fontFamily: "'Bricolage Grotesque', 'Bricolage Fallback', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"
    fontSize: "3rem"
    fontWeight: 700
    lineHeight: 1.1
  headline:
    fontFamily: "'Bricolage Grotesque', 'Bricolage Fallback', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 700
    lineHeight: 1.15
    letterSpacing: "-0.01em"
  title:
    fontFamily: "'Bricolage Grotesque', 'Bricolage Fallback', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 700
    lineHeight: 1.2
  subtitle:
    fontFamily: "'Bricolage Grotesque', 'Bricolage Fallback', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 700
    lineHeight: 1.3
  body:
    fontFamily: "'Bricolage Grotesque', 'Bricolage Fallback', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  body-small:
    fontFamily: "'Bricolage Grotesque', 'Bricolage Fallback', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "'Bricolage Grotesque', 'Bricolage Fallback', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    letterSpacing: "0.05em"
  icon-large:
    fontFamily: "'Bricolage Grotesque', 'Bricolage Fallback', -apple-system, 'Segoe UI', Roboto, Arial, sans-serif"
    fontSize: "2.25rem"
rounded:
  xs: "6px"
  sm: "8px"
  md: "12px"
  full: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  2xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.catamount-green}"
    textColor: "{colors.card-white}"
    typography: "{typography.body-small}"
    rounded: "{rounded.xs}"
    padding: "0.55rem 1.25rem"
  button-primary-hover:
    backgroundColor: "{colors.catamount-green-hover}"
    textColor: "{colors.card-white}"
  button-secondary:
    backgroundColor: "{colors.fog}"
    textColor: "{colors.text-secondary}"
    rounded: "{rounded.xs}"
    padding: "0.55rem 1.25rem"
  button-secondary-hover:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.slate-ink}"
  button-gold:
    backgroundColor: "{colors.gold}"
    textColor: "{colors.catamount-green}"
    rounded: "{rounded.xs}"
    padding: "0.55rem 1.25rem"
  button-gold-hover:
    backgroundColor: "{colors.gold-hover}"
    textColor: "{colors.catamount-green}"
  button-danger:
    backgroundColor: "{colors.danger}"
    textColor: "{colors.card-white}"
    rounded: "{rounded.xs}"
    padding: "0.55rem 1.25rem"
  button-danger-hover:
    backgroundColor: "{colors.danger-hover}"
    textColor: "{colors.card-white}"
  chip-filter:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.text-secondary}"
    typography: "{typography.body-small}"
    rounded: "{rounded.full}"
    padding: "0.35rem 0.75rem"
  chip-filter-selected:
    backgroundColor: "{colors.catamount-green}"
    textColor: "{colors.card-white}"
    rounded: "{rounded.full}"
  tag-amenity:
    backgroundColor: "{colors.fog}"
    textColor: "{colors.text-secondary}"
    typography: "{typography.label}"
    rounded: "{rounded.xs}"
    padding: "0.15rem 0.45rem"
  tag-included:
    backgroundColor: "{colors.included-tint}"
    textColor: "{colors.included-ink}"
    rounded: "{rounded.xs}"
  tag-preference:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.text-secondary}"
    rounded: "{rounded.xs}"
  price-tab:
    backgroundColor: "{colors.gold}"
    textColor: "{colors.catamount-green}"
    typography: "{typography.title}"
    rounded: "{rounded.xs}"
    padding: "0.4rem 0.75rem 0.15rem 1.45rem"
  badge-semester:
    backgroundColor: "{colors.catamount-green}"
    textColor: "{colors.card-white}"
    typography: "{typography.label}"
    rounded: "{rounded.xs}"
    padding: "0.2rem 0.5rem"
  card-listing:
    backgroundColor: "{colors.card-white}"
    rounded: "{rounded.md}"
  input-field:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.slate-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.xs}"
    padding: "0.65rem 0.875rem"
  nav-bar:
    backgroundColor: "{colors.catamount-green}"
    textColor: "{colors.card-white}"
    height: "64px"
---

# Design System: UVM Sublets

## Overview

**Creative North Star: "The Campus Notice Board"**

UVM Sublets is the bulletin board outside every dorm, kept tidy. Each listing is an index card pinned to the same board: a photo, a price, a few facts about the place, all in the same positions on every card so a student can scan a wall of them in seconds. The board is green felt (Catamount Green), the pins are Gold, and the cards are white paper that lifts a little when you reach for one. Order is what makes the board useful; the handwriting on it is what makes it feel like it came from a fellow student rather than an office.

The confirmed mood is **friendly and student-made, lively and social**. It should feel like something students run for each other: warm, direct, and worth sharing into a group chat or a story. It is not an institutional portal and it is not a real-estate marketplace. Density is moderate: generous enough for photos to carry each card, tight enough that a phone screen shows a listing's price and facts without opening it.

The notice board is drawn with **light touches**, not a costume: the price on each card is a gold tab standing up over the photo's edge, and that is the motif. No tilted cards, no cork texture, no handwriting fonts. Everything is set in **Bricolage Grotesque**, a grotesque with ink traps and a little flyer energy, self-hosted and used at two weights.

**Key Characteristics:**
- Deep green structure, bright gold pins, white index cards on a pale fog ground.
- Every listing card shows the same facts in the same places: photo, gold price tab, address, one facts line, at most three tags.
- A soft, slate-tinted lift: cards rest on a faint shadow and rise slightly when a real pointer hovers.
- Tactile, pinned controls: solid buttons, pill-shaped filter chips that snap to green when selected.
- One typeface, two weights, seven sizes. Light mode only. Text and shadows are slate, never black.

## Colors

A two-accent palette taken from UVM's colors: Catamount Green carries structure and action, Gold marks what is pinned, and everything else is slate-tinted neutrals and four state tints. Every color is a custom property on `:root` in `css/style.css`; nothing below it is a raw hex value.

### Primary
- **Catamount Green** (#154734): the felt of the board. Navigation bar, primary buttons, prices, selected filter chips, page headings, links, the footer, and the focus glow on form fields (as a 10% tint). Hover deepens to Catamount Green Hover (#1a5c44).

### Secondary
- **Gold** (#FFD100): the pushpin. Each card's price tab, the logo tile, the active nav link, the Edit button, and text selection. On a card it means the price and nothing else. It sits on green or under green text (7.25:1), never as text on white (1.46:1). Hover is Gold Hover (#e6bc00).

### Tertiary
- **Focus** (#2f86c9): keyboard focus rings (3px, offset 2px) on light surfaces, 3.91:1 on white. On the green nav and footer the ring is Gold instead, because no blue clears 3:1 against both green and white. Focus Sky (#489FDF) remains for link hover only.
- **Alert Orange** (#DC582A) and **Danger** (#c44e22): orange is the family; Danger is the shade destructive buttons are filled with, deep enough that white text passes (4.71:1), deepening to Danger Hover (#b5401a).

### Neutral
- **Slate Ink** (#00313C): body text, and the tint behind every shadow and scrim. Never pure black.
- **Text Secondary** (#4a5e63): facts lines, labels, unselected chip text.
- **Text Muted** (#5f7378): meta lines, filter labels, field hints and empty-state copy. 4.99:1 on white and 4.66:1 on Fog, so it passes body-text contrast (it was #7a8e93 at 3.43:1 until October 2026).
- **Fog** (#F7F7F7): page background and the secondary button fill.
- **Card White** (#FFFFFF): cards, price tabs, panels, dialogs and inputs.
- **Border** (#dce1e3) and **Border Light** (#eef1f2): field strokes and card outlines.

### State tints
Four sets, each a tint, an ink and a line, each drawn from the palette:
- **Included** (green; tint #e8f5e9, ink #1a6b4a): amenities and utilities that come with the place, success alerts and banners, and a taken listing's status panel.
- **Tenant pays** (gold; tint #fff8e1, ink #7a6100): utilities the subletter pays, in the listing view, and warning alerts and banners. Not on cards: there, gold is the price, so paid parking and paid laundry use the plain tag.
- **Error** (orange; tint #fce8e3, ink #b5401a): error alerts.
- **Notice** (sky; tint #eaf3fb, ink #0b4f7d): info alerts, the default announcement banner, and a paused listing's status panel.

A roommate preference is not a state: its tag is white with a hairline border and a green icon, so it reads as a note, not a requirement.

### Named Rules
**The Two-Pin Rule.** Green is the board and Gold is the pin. Green carries structure and every primary action. Gold marks only what is pinned: the price tab, the active page, the poster's own Edit. On a card, gold means the price and nothing else. Gold never fills a large area and is never text on a light ground.

**The Slate Ink Rule.** Text, scrims and shadows are tints of Slate Ink (#00313C); nothing on the site uses pure black.

**The Token Rule.** A color is used through its custom property. A new color is added to `:root` with a comment saying what it is for, or it is not added.

## Typography

**Display Font:** Bricolage Grotesque (with a metric-matched Arial fallback, then the system stack)
**Body Font:** Bricolage Grotesque
**Label/Mono Font:** Bricolage Grotesque; labels are set apart by case and tracking, not family

**Character:** a grotesque with ink traps and a little flyer energy. It is friendly at small sizes and gets more characterful as it grows, because its optical-size axis tightens the display cuts. Self-hosted as one variable WOFF2 (Latin, ~75 KB, weights 400-700, optical sizing) at `assets/fonts/`; `share-card.php` uses static TTF cuts of the same family.

### Hierarchy
Seven sizes, as custom properties (`--text-xs` to `--text-3xl`), plus Display for the landing hero. Nothing is smaller than 0.75rem, and form fields are always 1rem so iOS does not zoom when one is tapped.
- **Display** (700, 3rem, 1.1): the landing page's hero heading only (2.25rem on phones). The app itself tops out at Headline.
- **Headline** (700, 1.75rem, 1.15): page titles ("Create a Listing") and the price in the listing view.
- **Title** (700, 1.375rem, 1.2): the board heading ("34 sublets"), the price on a card's tab, the nav wordmark.
- **Subtitle** (700, 1.125rem, 1.3): form section headings, dialog titles, empty-state headlines.
- **Body** (400, 1rem, 1.5): descriptions, card addresses, alerts, every form field.
- **Body Small** (400 or 700, 0.875rem): facts lines, buttons, chips, form labels, hints, meta.
- **Label** (700, 0.75rem, 0.05em, uppercase): filter-group labels and badges; tags use the size without the case.
- **Icon sizes**: 1.75rem (the upload zone, the map's empty state, a broken image) and 2.25rem (the Browse empty state, as Icon Large). Icons never set text sizes.

### Named Rules
**The Two Weights Rule.** 400 and 700, nothing between. Hierarchy comes from size and color first.

**The Seven Sizes Rule.** Every font size is one of the seven tokens. A size that is not on the scale is a bug, not a variation.

**The Uppercase Label Rule.** Small uppercase text with 0.05em tracking is reserved for labels that name a control or a category. Content is never set in uppercase.

## Layout

A centered column at most 1280px wide (`--max-width`) with 1.5rem gutters, under a sticky 64px green nav. Browse stacks a white filter panel, then a sort bar whose left side is the page's heading ("32 Spring 2027 sublets", or "34 sublets" with "For Spring 2027 unless marked" when the listings span semesters), then a listing grid of auto-fill columns at least 280px wide with 1.25rem gaps: one column on phones, up to four at full width. At 768px and below the columns are at least 290px, so a two-column tablet layout never squeezes the facts line, and the filter panel gives way to a sticky filter bar (see Components) so a phone opens on listings, not on controls. The listing opens in a centered dialog up to 720px wide over a slate scrim. The post form is a single white panel with grouped sections.

Spacing follows a loose 4px rhythm (0.25, 0.5, 0.75, 1, 1.25, 1.5 and 2rem). Breakpoints: 768px (nav collapses to a toggle; filters become the sticky bar and a bottom sheet), 600px (the listing view and the share sheet become full-height and bottom sheets), 480px (the tightest phone adjustments). Hover effects are gated on `(hover: hover)`, and `prefers-reduced-motion` turns animation off site-wide.

## Elevation & Depth

Surfaces are layered paper on a fog ground. Cards and panels rest on a faint slate-tinted shadow, a card lifts 2px to a deeper shadow when a real pointer hovers it, and dialogs float on the deepest shadow over a blurred slate scrim. Depth always comes from slate-tinted shadow, never from a gray or black drop shadow.

### Shadow Vocabulary
- **Resting** (`box-shadow: 0 1px 3px rgba(0, 49, 60, 0.06)`): listing cards, form panels, semester badges.
- **Panel** (`box-shadow: 0 2px 8px rgba(0, 49, 60, 0.08)`): the filter panel.
- **Lifted** (`box-shadow: 0 4px 16px rgba(0, 49, 60, 0.10)`): a hovered card and the nav bar.
- **Floating** (`box-shadow: 0 8px 32px rgba(0, 49, 60, 0.14)`): dialogs, the share sheet and map popups.
- **Tab** (`box-shadow: 0 -3px 8px rgba(0, 49, 60, 0.12)`): a card's price tab, casting up onto the photo it overlaps.
- **Field focus** (`box-shadow: 0 0 0 3px rgba(21, 71, 52, 0.1)`): a green glow around a focused input or select.

### Named Rules
**The Pushpin Lift Rule.** A card rests on the Resting shadow and lifts 2px to Lifted, with its photo easing to 103%, only under `(hover: hover)`. On touch, a tapped card must not stay lifted.

**The Scrim Rule.** Anything that floats sits over Slate Ink at 60% with a 4px backdrop blur, so the board stays visible behind it.

## Shapes

Index cards with gently rounded corners. Cards, panels, dialogs and the share sheet use a 12px radius. Small inset or floating surfaces (alerts, map popups, the nav's logo tile, share tiles) use 8px. Buttons, inputs, tags and badges use a tighter 6px; the price tab rounds only its top two corners (6px) so it reads as part of the card it stands up from. Filter chips are full pills, the only pill shape on the site, and gallery arrows, dots and share icons are circles. Card photos are cropped to a 3:2 box with `object-fit: cover`. Outlines are hairlines: 1px Border Light on cards and 1px Border on fields.

### Named Rules
**The Index Card Rule.** Containers are 12px, small inset surfaces 8px, controls 6px; filter chips are pills and nothing else is. A new component picks one of these, never a new radius.

## Components

### Buttons
Solid and pressable, like a well-made index-card tab.
- **Shape:** gently rounded (6px), 700 weight at 0.875rem, 0.55rem × 1.25rem padding, icon and label with a 0.4rem gap.
- **Primary:** Catamount Green with white text. Hover deepens to Catamount Green Hover.
- **Secondary:** a Fog fill, Text Secondary label and 1px Border. Hover brightens to white with a slate label and a darker border.
- **Gold:** a Gold fill with a Catamount Green label, used only for Edit.
- **Danger:** a Danger fill with a white label (4.71:1), deepening to Danger Hover.
- **Small:** 0.35rem × 0.75rem padding at 0.875rem, used for the listing view's action row.
- **Focus:** a 3px Focus outline offset 2px, on keyboard focus only (`:focus-visible`); Gold on the green nav and footer.

### Chips
- **Style:** the amenity filters ("Must have") are white pills with a 1px Border, a Text Secondary label at 0.875rem and a leading icon.
- **State:** a selected chip snaps to a solid Catamount Green fill with white text. Focus uses the Focus ring. Hover is gated on real pointers.

### Tags
Small facts at the bottom of a card and inside the listing view.
- **Style:** 6px corners, 0.75rem/700, a 1px border, Fog by default.
- **Variants:** Included (green tint), plain (Fog; paid parking, paid laundry), Preference (white, hairline border, green icon, "Prefers …"), and a borderless "+N more". No gold tag: on a card gold is the price.

### Cards / Containers
- **Corner Style:** 12px.
- **Background:** Card White with a 1px Border Light outline.
- **Shadow Strategy:** Resting, lifting to Lifted on pointer hover (see Elevation & Depth).
- **Border:** hairline only.
- **Internal Padding:** 0 × 1rem below the photo, with the price tab pulled up over it. Form panels use 2rem, the filter panel 1.25rem × 1.5rem.

### Inputs / Fields
- **Style:** Card White, a 1px Border, 6px corners, 0.65rem × 0.875rem padding, 1rem text. Selects carry a custom caret in Text Secondary.
- **Focus:** the border turns Catamount Green with the green Field focus glow; there is no outline ring.
- **Error / Disabled:** errors are shown as an Error alert above the form, not on the field.
- **Busy:** a submit button that has been pressed swaps its icon for a spinner and its label for what is happening ("Uploading 3 photos…"), and further presses are ignored. It is not `disabled`, which would drop it from the form data.

### Navigation
- **Style:** a sticky 64px Catamount Green bar with the Gold logo tile (an inline SVG house, the same mark as the favicon) and the white "UVM Sublets" wordmark at the left. Links are white at 85% opacity (0.875rem), get a faint white wash on hover, and the active link turns Gold on a faint gold wash. The signed-in student's name sits at the right behind a hairline divider.
- **Mobile:** below 768px the links collapse behind a toggle that reports `aria-expanded`.

### Listing Card (signature)
The pinned index card, in this order:
1. A 3:2 photo. A green semester badge with white text (top right, uppercase label) appears only on a card whose semester differs from the one most listings on screen share.
2. The **price tab**: a Gold tab with rounded top corners standing up over the photo's bottom edge. Everything on it is Catamount Green (7.25:1): the rent (Title, 700, tabular figures), "/mo", and "or best offer" as a label when negotiable.
3. The shortened address on one line (Body).
4. One facts line in Text Secondary: size and roommates, then distance from campus ("3 bd · 1 ba · 1 roommate · 0.5 mi"; the card's accessible name spells out "miles from campus"). It fits one line at both 390px and 1280px; a second line carries estimated utilities when given.
5. One row of tags, never two: a roommate preference first if there is one, then the amenities rarest among the listings on screen, then "+N more". The server sends at most three; app.js hides from the end until the row fits and adds what it hid to "+N more", and a preference too long on its own ends in an ellipsis. The row sits on the card's bottom edge, so cards in a grid row end level.

The poster's name is not on the card; it is in the listing view. The whole card is a keyboard-focusable button that opens the listing view.

### Filter Bar and Sheet (phones)
At 768px and below the filter panel is replaced by a sticky bar under the nav: a white pill "Filters" button with a green count badge for the active filters, and a List / Map segmented switch (the active segment filled green) that keeps the filters when switching. "Filters" opens the same form as a bottom sheet (12px top corners, Floating shadow, over the slate scrim) with a sticky header (title, close) and a sticky footer button. On Browse the listings update underneath as filters change and the button reads "Show 12 sublets"; on Map it applies them. Chips grow to 40px tap targets inside the sheet. Untouched sliders do not count as filters.

### Listing View (signature)
A centered dialog (720px, 12px corners, Floating shadow) with the photo gallery on top and the details below: the price in Catamount Green with "/mo", a facts line under it (distance from campus · semester · size) and the poster line ("Posted by Maya · 3 days ago", the listing's age, not its last edit), an action row (Email, Call, Share, Edit, admin Delete), the address with a maps link ("Open in Maps" to Apple Maps on iPhone, iPad and Mac, otherwise "Open in Google Maps"), the semester, place and roommate facts, description, utilities and amenities. Utilities the subletter pays are neutral, not gold. The gallery swipes on touch, shows a "2 / 7" counter, and its dots are 24px targets around 8px marks. The arrows, counter and dots are there the moment the view opens; a photo still loading sits on its thumbnail (or the slate ground) and fades in over 0.2s when it arrives, so the view never shows one photo under another's counter. Contact opens a panel that slides over the details: an editable email draft that greets the poster by name (or "Hi there", never a NetID) and names the listing, price and semester, with Copy buttons and a note that everyone here signs in with a UVM NetID; or the phone number with Call and Text.

At 600px and below the view is a full-screen sheet: a sticky top bar (close and share, 44px round buttons on Fog), a square photo area, and a sticky bottom bar with the price on the left and Email / Call on the right, so the way out and the way to make contact never scroll away. The phone's Back button closes it.

### Post Form
One white panel. The address field is a combobox: suggestions as a listbox the arrow keys move through, and "Searching…", "No match…" and lookup-failed messages in the same dropdown. Under it a lock-icon note says who sees the address. The map preview opens on campus with no pin until there is a place; picking a suggestion or tapping the map places the pin, and on a desktop the pin drags. On a desktop the map is sticky beside the form; at 768px and below it sits under the address field, where one finger scrolls the page past it and two fingers pan. Photos add up across picks; each has a 28px remove button that is always visible (a 44px target), and the first carries a Gold "Cover" label.

### Listing Status (post form)
One panel under the edit form's heading, below the poster's numbers: an icon and a bold state ("On the board", "Paused since Oct 3", "Taken since Oct 3"), one Secondary line saying what it means on Browse and for the share link, and small buttons for where it can go next (Mark as taken and Pause; Resume and Mark as taken; Put it back up). It is Fog while the listing is up, Notice while paused and Included once taken. While it is up both buttons are Secondary, so Update Listing stays the page's one primary; otherwise the way back up is Primary. Nothing asks for confirmation, since every step can be undone from the same panel. Each change lands back on the page with a success banner. A taken listing's share link shows the interstitial's white card with "This sublet has been taken" and a Browse sublets button, and no preview image. A followed link to a listing that is no longer up opens Browse with one Notice line above the cards. In the admin Posts tab the status is a select that saves on change and says "Saved" (or the error, putting the select back) under it; elsewhere in admin, paused and taken show as plain tags or meta words.

### Share Sheet (signature)
A grid of round icon tiles (Share to…, Instagram story, Snapchat, Copy link) above a link field with a Copy button. It becomes a bottom sheet under 600px.

### Footer
Green, with the credit, the Instagram contact, and two standing lines: "An independent student project, not affiliated with the University of Vermont." (also on the landing page and the share interstitial) and "Listing views and contact taps are counted. Posters and the admin see totals, never who."

### Activity (admin) and the poster's numbers
Counts only, never names. The admin Activity tab has a segmented range switch (the active segment filled green), stat cards for the totals, a daily bar chart with views in Catamount Green and contacts in Focus blue side by side (each bar clears 3:1 on the white card, and its axis labels are HTML so they do not scale with the SVG), two horizontal-bar breakdowns (where views start, where shares go), and a per-listing table with right-aligned tabular figures. On the edit form, the poster sees one line in a Fog panel under the heading ("Since Oct 2: 48 people viewed it · 6 got in touch · shared 3 times") and the same disclosure.

### Archive a semester (admin)
A card under the semester list. The dry run opens in a Fog panel inside it: fact tiles (white, 6px, uppercase Label over a bold figure) for listings, photos, bytes, events and share cards, one sentence saying what the archive keeps and where the photos are saved, and a table of the listings that would go. The only way through is the semester code typed into a 1rem field; the Danger button beside it stays disabled (45% opacity) until the code matches exactly, and its label says what it does ("Archive Summer 2026"). A schema check sits above it in Included ink when the tables match the plan, as an Error or Warning alert with a column-by-column table when they do not. Deleting a backup tarball is two presses: the first turns the button Danger and says "Delete this backup", and it disarms after four seconds.

### Images (admin)
Every listing's photos, grouped under the listing's address, with per-semester storage above. Photos are judged here, so tiles are at least 160px wide, drawn from the 600px thumbnails, 4:3 with `object-fit: cover`, and the first carries a Catamount Green "Cover" label. Nothing sits on the photo but the cover label and a 44px selection target in the corner; the controls are below it, on two rows that line up across a grid: move earlier, move later and "Make cover", then Delete on its own. Delete is two presses ("Delete?"), and is disabled on a listing's only photo. Selected tiles take a Danger outline, and a white bar with the count, Clear and "Delete selected" stays at the bottom of the screen while any are selected. The orphan view lists each file with a small preview, its kind and size, and deletes only after the number of files is typed.

## Do's and Don'ts

### Do:
- **Do** use Catamount Green (#154734) for every primary action and the selected state, and Gold (#FFD100) only as a pin: the price tab, the active page, Edit.
- **Do** keep every listing card's facts in the same positions (price tab, address, facts line, tags), so the board scans.
- **Do** use the seven `--text-*` sizes and the two weights, and keep form fields at 1rem.
- **Do** tint text, scrims and shadows with Slate Ink (#00313C), and use the shadow vocabulary as it stands.
- **Do** give every interactive element the 3px `:focus-visible` ring with a 2px offset: Focus (#2f86c9) on light surfaces, Gold on green.
- **Do** gate hover lift, photo zoom and button hover colours on `(hover: hover)`, and keep the site-wide `prefers-reduced-motion` override.
- **Do** keep a card's tags to one row and touch targets at least 44px on phones (40px for chips inside the filter sheet).
- **Do** pick 12px for containers, 8px for small inset surfaces, 6px for controls, and pills only for filter chips.

### Don't:
- **Don't** use official UVM logos or wordmarks, or style a page so it reads as an official university service. UVM Sublets is an independent student project (PRODUCT.md), and the footer says so.
- **Don't** put Gold text on white or Fog (1.46:1), and don't fill large areas with Gold.
- **Don't** use pure black for text or shadows.
- **Don't** add a hard-coded hex value outside `:root`.
- **Don't** set content text below 0.75rem, or lighten Text Muted (#5f7378) again; it is already the lightest gray that passes 4.5:1 on Fog.
- **Don't** put a fact on every card that nearly every listing shares; that is what the rarity order and "+N more" are for.
- **Don't** draw the notice board literally: no tilt, no cork, no handwriting font. The pinned price tab is the motif.
- **Don't** leave hover-only effects ungated; a tapped card on a phone must not stay lifted.

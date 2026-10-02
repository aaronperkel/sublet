---
name: UVM Sublets
description: Sublet listings for UVM students, pinned to one board.
colors:
  catamount-green: "#154734"
  catamount-green-hover: "#1a5c44"
  gold: "#FFD100"
  gold-hover: "#e6bc00"
  slate-ink: "#00313C"
  focus-sky: "#489FDF"
  alert-orange: "#DC582A"
  fog: "#F7F7F7"
  card-white: "#FFFFFF"
  text-secondary: "#4a5e63"
  text-muted: "#7a8e93"
  border: "#dce1e3"
  border-light: "#eef1f2"
  included-tint: "#e8f5e9"
  included-ink: "#1a6b4a"
  tenant-tint: "#fff8e1"
  tenant-ink: "#7a6100"
  preference-tint: "#f3e8fd"
  preference-ink: "#6b21a8"
  error-tint: "#fce8e3"
  error-ink: "#b5401a"
typography:
  brand:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 700
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 700
    lineHeight: 1.3
  title:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.375rem"
    fontWeight: 600
    lineHeight: 1.35
  subtitle:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 600
    lineHeight: 1.4
  price:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
  body:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.6
  body-small:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "0.85rem"
    fontWeight: 400
    lineHeight: 1.6
  label:
    fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 600
    letterSpacing: "0.05em"
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
    backgroundColor: "{colors.alert-orange}"
    textColor: "{colors.card-white}"
    rounded: "{rounded.xs}"
    padding: "0.55rem 1.25rem"
  chip-filter:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.text-secondary}"
    rounded: "{rounded.full}"
    padding: "0.35rem 0.75rem"
  chip-filter-selected:
    backgroundColor: "{colors.catamount-green}"
    textColor: "{colors.card-white}"
    rounded: "{rounded.full}"
  tag-amenity:
    backgroundColor: "{colors.fog}"
    textColor: "{colors.text-secondary}"
    rounded: "{rounded.xs}"
    padding: "0.15rem 0.45rem"
  tag-included:
    backgroundColor: "{colors.included-tint}"
    textColor: "{colors.included-ink}"
    rounded: "{rounded.xs}"
  tag-tenant-pays:
    backgroundColor: "{colors.tenant-tint}"
    textColor: "{colors.tenant-ink}"
    rounded: "{rounded.xs}"
  badge-price:
    backgroundColor: "{colors.catamount-green}"
    textColor: "{colors.card-white}"
    rounded: "{rounded.xs}"
    padding: "0.25rem 0.6rem"
  badge-semester:
    backgroundColor: "{colors.gold}"
    textColor: "{colors.catamount-green}"
    rounded: "{rounded.xs}"
    padding: "0.2rem 0.5rem"
  card-listing:
    backgroundColor: "{colors.card-white}"
    rounded: "{rounded.md}"
  input-field:
    backgroundColor: "{colors.card-white}"
    textColor: "{colors.slate-ink}"
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

UVM Sublets is the bulletin board outside every dorm, kept tidy. Each listing is an index card pinned to the same board: a photo, a price, a semester, a few facts about the place, all in the same positions on every card so a student can scan a wall of them in seconds. The board is green felt (Catamount Green), the pins are Gold, and the cards are white paper that lifts a little when you reach for one. Order is what makes the board useful; the handwriting on it is what makes it feel like it came from a fellow student rather than an office.

The confirmed mood is **friendly and student-made, lively and social**. It should feel like something students run for each other: warm, direct, and worth sharing into a group chat or a story. It is not an institutional portal and it is not a real-estate marketplace. Density is moderate: generous enough for photos to carry each card, tight enough that a phone screen shows the price, semester and size of a listing without opening it.

What exists today is a coherent but plain version of this board. It uses the system font stack, a single stylesheet (`css/style.css`) of custom properties on `:root`, and a Font Awesome icon set. The palette and shape language are consistent; the type scale, the state tints and some inline styles have drifted (see Typography and Colors).

**Key Characteristics:**
- Deep green structure, bright gold pins, white index cards on a pale fog ground.
- Every listing card shows the same facts in the same places: photo, price badge, semester pin, address, size, distance, a few amenity tags.
- A soft, slate-tinted lift: cards rest on a faint shadow and rise slightly when a real pointer hovers.
- Tactile, pinned controls: solid buttons, pill-shaped filter chips that snap to green when selected.
- Light mode only. Text and shadows are slate, never black.

## Colors

A two-accent palette taken from UVM's colors: Catamount Green carries structure and action, Gold marks what is pinned, and everything else is slate-tinted neutrals.

### Primary
- **Catamount Green** (#154734): the felt of the board. Navigation bar, primary buttons, listing prices (the price badge on each card and the large price in the listing view), selected filter chips, page headings, links, the footer, and the focus glow on form fields (as a 10% tint). Hover deepens toward Catamount Green Hover (#1a5c44).

### Secondary
- **Gold** (#FFD100): the pushpin. The logo tile in the nav, the active nav link, the semester badge pinned to each card's photo, and the Edit button. It sits on green or under green text (7.25:1), never as text on white (1.46:1). Hover is Gold Hover (#e6bc00).

### Tertiary
- **Focus Sky** (#489FDF): keyboard focus rings (3px, offset 2px) and link hover. It is the only blue in the token set.
- **Alert Orange** (#DC582A): destructive actions (Delete) and danger states.

### Neutral
- **Slate Ink** (#00313C): body text, and the tint behind every shadow and the dialog scrim. Never pure black.
- **Text Secondary** (#4a5e63): labels, secondary copy, unselected chip text.
- **Text Muted** (#7a8e93): meta lines and empty-state copy. At 3.43:1 on white it is below body-text contrast; keep it to non-essential text.
- **Fog** (#F7F7F7): page background and the secondary button fill.
- **Card White** (#FFFFFF): cards, panels, dialogs and inputs.
- **Border** (#dce1e3) and **Border Light** (#eef1f2): field strokes and card outlines.

**State tints.** These are hard-coded in rules rather than declared on `:root`, a known drift:
- Included (green tint #e8f5e9 / ink #1a6b4a): amenities and utilities that come with the place, and success alerts.
- Tenant pays (gold tint #fff8e1 / ink #7a6100): paid parking or paid laundry.
- Preference (purple tint #f3e8fd / ink #6b21a8): "Looking for" roommate tags. This is the only purple on the site.
- Error (orange tint #fce8e3 / ink #b5401a): error alerts.
- The announcement banner's info style uses an outside blue set (#e3f2fd / #1565c0) that belongs to none of the above.

### Named Rules
**The Two-Pin Rule.** Green is the board and Gold is the pin. Green carries structure and every primary action. Gold marks only what is pinned: the active page, the semester and the poster's own Edit. Gold never fills a large area and is never text on a light ground.

**The Slate Ink Rule.** Text, scrims and shadows are tints of Slate Ink (#00313C); nothing on the site uses pure black.

## Typography

**Display Font:** none; the system UI stack (-apple-system, Segoe UI, Roboto, Helvetica Neue, Arial)
**Body Font:** the same system UI stack
**Label/Mono Font:** the same stack; labels distinguish themselves by case and tracking, not family

**Character:** native and unbranded. The system stack reads cleanly on every phone, but it gives the board no handwriting of its own; the brand currently lives in color and shape, not type.

### Hierarchy
- **Brand** (700, 1.25rem, -0.02em tracking): the "UVM Sublets" wordmark in the nav.
- **Headline** (700, 1.75rem, 1.3): page titles ("Create a Listing"), in Catamount Green.
- **Title** (600, 1.375rem, 1.35): section headings.
- **Subtitle** (600, 1.125rem, 1.4): form section headings and dialog titles.
- **Price** (700, 1.5rem): the price at the top of the listing view, in Catamount Green.
- **Body** (400, 1rem, 1.6): descriptions and paragraphs.
- **Body Small** (400–600, 0.85rem): card addresses (0.9rem, 500), form labels (600) and meta lines. This is the most-used size on the site.
- **Label** (600, 0.75rem, 0.05em, uppercase): filter-group labels ("Price Range", "Semester") and the semester badge.

### Named Rules
**The Uppercase Label Rule.** Small uppercase text with 0.05em tracking is reserved for labels that name a control or a category. Content is never set in uppercase.

**The Scale Drift Note.** The stylesheet currently uses about 27 distinct font sizes, from 0.6rem to 3rem; the hierarchy above is the intended core. Sizes below 0.75rem (0.6–0.72rem, mostly on tags and badges) are drift and too small for a phone.

## Layout

A centered column at most 1280px wide (`--max-width`) with 1.5rem gutters, under a sticky 64px green nav. Browse stacks a white filter panel (sliders, the semester select, amenity chips), then a sort bar, then a listing grid of auto-fill columns at least 280px wide with 1.25rem gaps. That gives one column on phones and up to four at full width. The listing opens in a centered dialog up to 720px wide over a slate scrim. The post form is a single white panel with grouped sections.

Spacing follows a loose 4px rhythm (0.25, 0.5, 0.75, 1, 1.25, 1.5 and 2rem); 0.5rem is the most common gap. Breakpoints: 768px (nav collapses to a toggle; panels and the dialog go full-width), 600px (the share sheet becomes a bottom sheet), 480px (the tightest phone adjustments). Hover effects are gated on `(hover: hover)`, and `prefers-reduced-motion` turns animation off site-wide.

## Elevation & Depth

Confirmed: keep the soft lift. Surfaces are layered paper on a fog ground. Cards and panels rest on a faint slate-tinted shadow, a card lifts 2px to a deeper shadow when a real pointer hovers it, and dialogs float on the deepest shadow over a blurred slate scrim. Depth always comes from slate-tinted shadow, never from a gray or black drop shadow.

### Shadow Vocabulary
- **Resting** (`box-shadow: 0 1px 3px rgba(0, 49, 60, 0.06)`): listing cards, form panels, badges at rest.
- **Panel** (`box-shadow: 0 2px 8px rgba(0, 49, 60, 0.08)`): the filter panel.
- **Lifted** (`box-shadow: 0 4px 16px rgba(0, 49, 60, 0.10)`): a hovered card and the nav bar.
- **Floating** (`box-shadow: 0 8px 32px rgba(0, 49, 60, 0.14)`): dialogs, the share sheet and map popups.
- **Field focus** (`box-shadow: 0 0 0 3px rgba(21, 71, 52, 0.1)`): a green glow around a focused input or select.

### Named Rules
**The Pushpin Lift Rule.** A card rests on the Resting shadow and lifts 2px to Lifted, with its photo easing to 103%, only under `(hover: hover)`. On touch, a tapped card must not stay lifted.

**The Scrim Rule.** Anything that floats sits over Slate Ink at 60% with a 4px backdrop blur, so the board stays visible behind it.

## Shapes

Index cards with gently rounded corners. Cards, panels, dialogs and the share sheet use a 12px radius. Small inset or floating surfaces (alerts, map popups, the nav's logo tile, share tiles) use 8px. Buttons, inputs, tags and the price and semester badges use a tighter 6px. Filter chips are full pills, the only pill shape on the site, and gallery arrows, dots and the share icons are circles. Card photos are cropped to a 3:2 box with `object-fit: cover`. Outlines are hairlines: 1px Border Light on cards and 1px Border on fields.

### Named Rules
**The Index Card Rule.** Containers are 12px, small inset surfaces 8px, controls 6px; filter chips are pills and nothing else is. A new component picks one of these, never a new radius.

## Components

### Buttons
Solid and pressable, like a well-made index-card tab.
- **Shape:** gently rounded (6px), 600 weight at 0.875rem, 0.55rem × 1.25rem padding, icon and label with a 0.4rem gap.
- **Primary:** Catamount Green with white text. Hover deepens to Catamount Green Hover.
- **Secondary:** a Fog fill, Text Secondary label and 1px Border. Hover brightens to white with a slate label and a darker border.
- **Gold:** a Gold fill with a Catamount Green label, used only for Edit.
- **Danger:** an Alert Orange fill with a white label (3.84:1, below body-text contrast at this size).
- **Small:** 0.35rem × 0.75rem padding at 0.8rem, used for the listing view's action row.
- **Focus:** a 3px Focus Sky outline offset 2px, on keyboard focus only (`:focus-visible`).

### Chips
- **Style:** the amenity filters ("Must have") are white pills with a 1px Border, a Text Secondary label at 0.8rem/500 and a leading icon.
- **State:** a selected chip snaps to a solid Catamount Green fill with white text. Focus uses the Focus Sky ring. Hover is gated on real pointers.

### Tags
Small facts pinned to the bottom of a card and inside the listing view.
- **Style:** 6px corners, 0.7rem/600, a 1px border, Fog by default.
- **Variants:** Included (green tint), Tenant pays (gold tint), Preference (purple tint), and plain (estimated utility cost).

### Cards / Containers
- **Corner Style:** 12px.
- **Background:** Card White with a 1px Border Light outline.
- **Shadow Strategy:** Resting, lifting to Lifted on pointer hover (see Elevation & Depth).
- **Border:** hairline only.
- **Internal Padding:** 0.875rem × 1rem below the photo. Form panels use 2rem, the filter panel 1.25rem × 1.5rem.

### Inputs / Fields
- **Style:** Card White, a 1px Border, 6px corners, 0.65rem × 0.875rem padding. Selects carry a custom caret in Text Secondary.
- **Focus:** the border turns Catamount Green with the green Field focus glow; there is no outline ring.
- **Error / Disabled:** errors are shown as an alert panel (orange tint) above the form, not on the field.

### Navigation
- **Style:** a sticky 64px Catamount Green bar with the Gold logo tile and the white "UVM Sublets" wordmark at the left. Links are white at 85% opacity (0.9rem/500, 6px corners), get a faint white wash on hover, and the active link turns Gold on a faint gold wash. The signed-in student's name sits at the right behind a hairline divider.
- **Mobile:** below 768px the links collapse behind a toggle.

### Listing Card (signature)
The pinned index card. A 3:2 photo carries a Catamount Green price badge (bottom left, with "or best offer" when negotiable) and a Gold semester badge (top right, uppercase label). Under it: the shortened address on one line, the size summary, "Posted by" with the distance from campus, and a row of amenity tags. The whole card is a keyboard-focusable button that opens the listing view.

### Listing View (signature)
A centered dialog (720px, 12px corners, Floating shadow) with the photo gallery on top (circular arrows, dot pager) and the details below: the large green price, an action row (Email, Call, Share, Edit, admin Delete), address and semester fields with icons, place and roommate facts, description, utilities and amenities. Contact opens a panel that slides over the details, with a prepared email draft or the phone number and Copy buttons.

### Share Sheet (signature)
A grid of round icon tiles (Share to…, Instagram story, Snapchat, Copy link) above a link field with a Copy button. It becomes a bottom sheet under 600px.

## Do's and Don'ts

### Do:
- **Do** use Catamount Green (#154734) for every primary action, the price and the selected state, and Gold (#FFD100) only as a pin: the active page, the semester badge, Edit.
- **Do** keep every listing card's facts in the same positions (price bottom-left on the photo, semester top-right, then address, size, distance, tags), so the board scans.
- **Do** tint text, scrims and shadows with Slate Ink (#00313C); use the four-step shadow vocabulary as it stands.
- **Do** give every interactive element the 3px Focus Sky (#489FDF) `:focus-visible` ring with a 2px offset.
- **Do** gate hover lift and photo zoom on `(hover: hover)`, and keep the site-wide `prefers-reduced-motion` override.
- **Do** pick 12px for containers, 8px for small inset surfaces, 6px for controls, and pills only for filter chips.

### Don't:
- **Don't** use official UVM logos or wordmarks, or style a page so it reads as an official university service. UVM Sublets is an independent student project (PRODUCT.md).
- **Don't** put Gold text on white or Fog (1.46:1), and don't fill large areas with Gold.
- **Don't** use pure black for text or shadows.
- **Don't** add new hard-coded hex values outside `:root`; the state tints listed under Colors are the existing exceptions, not a pattern to extend.
- **Don't** set content text below 0.75rem or use Text Muted (#7a8e93) for anything a student needs to read to decide.
- **Don't** leave hover-only effects ungated; a tapped card on a phone must not stay lifted.

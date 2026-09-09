---
paths:
  - 'resources/js/**'
---

# Js

## Build UI from the Creeper design system, not from scratch
The design system is the landing page's language, tokenised. See it running at `/design` (registered in routes/web.php outside production) before building a screen.

Three rules: nothing is round (the radius scale is zeroed in `resources/css/app.css`; only `rounded-full` survives, for dots and avatars); shadows are hard offsets, never blurs (`shadow-xs`/`sm`/`md` are 1–3px offsets, `shadow-stamp` is the 6px framed-panel offset); anything a machine printed is mono (labels, counts, prices, statuses) and anything a person wrote is sans.

House components live in `resources/js/components/ds` and are imported from `@/components/ds`: Page, SectionHeading, Display, Eyebrow, Panel/PanelBar, Card (in ui/), Chip, StatTile, ReceiptRow, Meter, Field/CheckField/FormActions, EmptyState/EmptyLine, Terminal/Prompt/Ok/Changed/Emitted. shadcn primitives in `resources/js/components/ui` have been retuned to the same palette and are meant to be mixed in freely.

Reach for an existing component before writing a new bordered box. If a screen genuinely needs something new, add it to `ds/`, export it from `ds/index.ts`, and add a specimen to `resources/js/pages/design.tsx` — do not invent it inside the page.

## Register self-chrome pages in SELF_LAYOUT_PAGES
`resources/js/app.tsx` picks a layout by page name and its `default` branch is `AppLayout`, which renders the app sidebar. A page that wraps itself in `MarketingLayout` (or any other chrome) must be listed in `SELF_LAYOUT_PAGES` or it silently gets the sidebar wrapped around its own layout — the page still renders, so nothing fails, it just looks wrong.

Marketing pages are written for a visitor with no account, and the sidebar navigates a signed-in install. Add the page name to `SELF_LAYOUT_PAGES` when you add a marketing page.

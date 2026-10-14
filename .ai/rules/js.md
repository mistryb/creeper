---
paths:
  - 'resources/js/**'
---

# Js

## Build UI from the Creeper design system, not from scratch
The design system is the landing page's language, tokenised. See it running at `/design` (registered in routes/web.php outside production) before building a screen.

Three rules: cards are round and controls are square (the radius scale is zeroed in `resources/css/app.css`, so buttons, inputs, selects and menus keep a hard corner; every card-like surface — Card, Panel, StatTile, EmptyState, Alert, Dialog, the table wrapper, a bordered list or callout, and the header nav bar — takes `rounded-card` (`--radius-card`), plus `overflow-hidden` when a child paints a background or a divider to the edge; `rounded-full` survives only for dots, avatars and the header nav pills; never round a button, and never hand-write a `rounded-[…]` value); shadows are hard offsets, never blurs (`shadow-xs`/`sm`/`md` are 1–3px offsets, `shadow-stamp` is the 6px framed-panel offset); anything a machine printed is mono (labels, counts, prices, statuses) and anything a person wrote is sans.

House components live in `resources/js/components/ds` and are imported from `@/components/ds`: Page, SectionHeading, Display, Eyebrow, Panel/PanelBar, Card (in ui/), Chip, StatTile, ReceiptRow, Meter, Field/CheckField/FormActions, EmptyState/EmptyLine, Terminal/Prompt/Ok/Changed/Emitted. shadcn primitives in `resources/js/components/ui` have been retuned to the same palette and are meant to be mixed in freely.

Reach for an existing component before writing a new bordered box. If a screen genuinely needs something new, add it to `ds/`, export it from `ds/index.ts`, and add a specimen to `resources/js/pages/design.tsx` — do not invent it inside the page.

## The signed-in app is one centred column, with no sidebar
`AppLayout` holds every signed-in page in a single `max-w-2xl` column: the wordmark centred, the company menu on its own centred line beneath it, then a contained nav bar exactly as wide as the column (rounded, a subtle `border-rule` edge, Digests / Competitors / Settings, plus Admin for ADMIN_EMAILS, as centred pills — `navPill`), the page, and a footer line with the account and log-out. On a billed install one quiet line under the bar notes a running trial, or (amber) that digests are paused for want of a plan. There is no sidebar, no full-width bar and no breadcrumbs — do not reintroduce them, and do not widen the column for one page. A page that needs more room needs less on it. Keep forms and lists inside the column; prefer a plain section under a `border-t border-rule` to a Card.

## Register self-chrome pages in SELF_LAYOUT_PAGES
`resources/js/app.tsx` picks a layout by page name and its `default` branch is `AppLayout`. A page that wraps itself in `MarketingLayout` (or any other chrome) must be listed in `SELF_LAYOUT_PAGES` or it silently gets the app column wrapped around its own layout — the page still renders, so nothing fails, it just looks wrong.

Marketing pages are written for a visitor with no account, and the app layout navigates a signed-in install. `MarketingLayout` deliberately copies the app's header — centred wordmark, contained rounded bar of `navPill`s — so the two read as one product; keep them in step. Add the page name to `SELF_LAYOUT_PAGES` when you add a marketing page.

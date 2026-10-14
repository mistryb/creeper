---
paths:
  - 'resources/js/pages/{welcome,deploy}.tsx'
---

# Pages

## Marketing pages are sans, single-column and quieter than the app
The public pages (welcome, deploy) sit in one `max-w-3xl` column with no Panels, chips, zebra rows or full-bleed ink bands. Headings use `MarketingHeading` (hero/section) from `@/components/marketing/type` — Instrument Sans, semibold, tight tracking — not the dot-matrix `Display`, which stays the app's voice. Numbered lists use `MarketingStep` inside an `<ol className="border-b border-rule">`. Reuse these rather than restyling headings per page.

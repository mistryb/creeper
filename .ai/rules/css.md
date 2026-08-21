---
paths:
  - 'resources/css/**'
---

# Css

## The palette is light-only and every colour is a token
There is no dark theme. The paper/ink/ribbon palette does not survive being inverted, so dark mode was removed deliberately: no `.dark` block, no `dark:` variants, no appearance setting, no `@custom-variant dark`. Do not reintroduce them.

Never write a raw hex or a Tailwind colour like `text-emerald-600` in a component. Use the palette tokens (`paper`, `paper-lit`, `greenbar`, `ink`, `ink-soft`, `ink-line`, `ink-lift`, `rule`, `ribbon`, `ribbon-lit`, `ribbon-pale`, `ribbon-amber`, `ribbon-red`) or the shadcn semantic tokens mapped onto them in `:root`.

Ribbon colours are a two-tone typewriter ribbon plus one: green is ordinary/positive, amber wants attention, red is damage. Every ribbon value clears 4.5:1 on paper, paper-lit and greenbar, and `ribbon-pale` clears 4.5:1 on ink — that is why they may carry a word and not only decorate one. If you add or change a palette value, check the ratio on every surface it is allowed on and update the figures on the `/design` page.

The four type recipes (`label-micro`, `label-mono`, `display-dot`, `numeral-dot`) are `@utility` rules and are emitted before the plain Tailwind utilities, so `label-micro text-[0.8125rem]` resolves to the explicit size regardless of class order. Keep them declared where they are.

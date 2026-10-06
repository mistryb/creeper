---
paths:
  - 'app/Http/Middleware/**'
  - app/Http/Middleware/HandleInertiaRequests.php
---

# Middleware

## A local checkout signs itself in, and `auth` outruns the web group
`AuthenticateLocalUser` creates and signs in an account on the first request so a local checkout never waits on an emailed code. It is confined to the `local` environment, honours `LOCAL_AUTO_SIGN_IN` / `LOCAL_USER_EMAIL`, and still has to pass `AuthorizedEmails` — a restricted install signs in as an address it already allows or not at all. Accounts are still born through `User::forEmail()`.

The trap: `auth` is on the middleware priority list (as the `AuthenticatesRequests` contract), so it runs before anything merely appended to the `web` group and would redirect first. Any middleware that must act before authentication needs `prependToPriorityList(before: AuthenticatesRequests::class, ...)` in bootstrap/app.php, not just a place in the group.

## Shared Inertia props must not share a name with any page prop
Inertia merges page props over shared props, so a page prop with the same name silently replaces the shared one on that page only. The sidebar then reads the wrong shape and crashes the whole page.

This happened when the shared `businesses` chooser collided with the businesses index's `businesses` collection. Name shared props for their consumer (`businessChooser`, not `businesses`), and check `resources/js/pages` for the name before adding one. CurrentBusinessTest asserts the chooser survives on businesses.index.

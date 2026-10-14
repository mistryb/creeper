---
paths:
  - 'app/Http/Controllers/Admin/**'
---

# Admin

## The admin panel is ADMIN_EMAILS-gated and read-mostly
`/admin` is behind `can:admin`, a Gate backed by `App\Auth\AdminEmails` (config `auth.admin_emails`). Empty ADMIN_EMAILS means nobody — the opposite default to AUTHORIZED_EMAILS, on purpose. Being an admin does not authorize sign-in on a private install.

The panel only reads accounts and companies, and comps/uncomps (`users.comped_at`). It never creates, invites, edits or deletes accounts or companies — sign-up stays AUTHORIZED_EMAILS + one-time code. Admin pages use `[AppLayout, AdminLayout]` (picked by the `admin/` page prefix in app.tsx) and live in the same centred column; revenue figures come from Cashier's local subscriptions table, so they are only as fresh as the webhooks.

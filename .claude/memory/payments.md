---
name: payments
description: Two payment flows — orgs paying us (Cashier) and parents paying orgs (Stripe Connect)
metadata:
  type: project
---

There are **two distinct payment flows** that must not be confused:

**Type A — Org pays us (the SaaS fee):**
- Handled by **Laravel Cashier (Stripe)**.
- Lives in the `subscriptions` table.
- Gated by a `CheckSubscription` middleware that blocks the dashboard when status ≠ `active`.
- Pricing recommendation: **start with flat-rate** (e.g., $99/mo per org). Per-team pricing requires listening to `TeamCreated` events to sync Stripe quantity — more code, more failure modes. Defer until needed.
- Free trial via a `trial_ends_at` column on `organizations` so a card isn't required up front.

**Type B — Parent pays org (registration fees):**
- Needs **Stripe Connect** so money flows to the org, not us.
- Track via a `payments`/`invoices` table linked to `submission_id` or `player_id`, OR via the `submissions.payment_status` enum (`unpaid|paid`) for the simple case.
- Status columns must cover `pending`, `paid`, `failed`, `refunded`.

Build Type A first (it's the gate); Type B comes with the form builder phase. See [[development-roadmap]] for sequencing.

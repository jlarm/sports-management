---
name: architecture-tenancy
description: Multi-tenant SaaS architecture decisions — single DB, organization_id scoping, user-org pivot
metadata:
  type: project
---

**Strategy:** Single database, multi-tenant via logic-based scoping. Every table except `users`, `subscriptions`, and system tables carries an `organization_id`. Add an index on `organization_id` (and `season_id` where present) on every table — they are queried on every page load.

**Golden Rule trait:** `BelongsToOrganization` adds a global scope `where('organization_id', session('current_org_id'))` so one org cannot accidentally query another's data.

**Users are NOT tenant-scoped.** A single user (email) can belong to multiple organizations. Use the `organization_user` pivot with a `role` column (`owner`, `admin`, `coach`, `guardian`). Do not put `organization_id` on `users`.

**Active org tracking:** Store `current_org_id` in the session, set on login or org switch.

**Subscription gating:** `organizations` table holds Stripe status (Cashier) plus a `trial_ends_at` column for card-free trials. A `CheckSubscription` middleware blocks dashboard access when status is not `active`.

**Slugs:** Org slugs must be unique per-tenant context. See [[schema-plan]] for full table list and [[global-vs-seasonal]] for the data-lifetime split.

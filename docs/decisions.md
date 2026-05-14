# Decisions

> ## ⚠️ STACK: Inertia v3 + Vue 3 + Fortify + Wayfinder. **No Livewire.**
>
> This is the highest-priority decision in this doc. The older planning docs
> (`potential-planning-phases.md`, `season-archiving.md`) were drafted assuming a
> Livewire UI. **Ignore every Livewire reference in those docs.** Build all UI as
> Inertia pages in `resources/js/pages/...` with Vue 3 components, `useForm` /
> `<Form>` for form state, and Wayfinder-generated typed routes from `@/actions/`
> and `@/routes/`. Do not install `livewire/livewire`. Do not create Blade
> components for application UI. If a planning doc says "Livewire component X,"
> read it as "Inertia page X."
>
> Authentication is handled by Fortify (already installed) — do not introduce
> Jetstream or Breeze-Livewire flows. See §1 below for the full translation.

This doc captures the non-functional and architectural decisions that the other planning
docs (`initial-research.md`, `initial-schema.md`, `potential-planning-phases.md`,
`season-archiving.md`) don't yet cover or get wrong for this repo's stack.

Treat this as the source of truth when those docs disagree. Update this doc when a
decision is revisited; don't silently override it in code.

---

## 1. Stack alignment: Inertia + Vue, not Livewire

The planning docs reference Livewire components throughout (`OrgSignup`, `FormBuilder`,
the "Dual-Listbox" rostering UI, the `SeasonRollover` wizard). This repo is
**Inertia v3 + Vue 3 + Fortify + Wayfinder**. Translate every Livewire reference to:

- **Pages** in `resources/js/pages/...` rendered via `Inertia::render(...)` from a
  controller action.
- **Forms** using Inertia's `<Form>` component or the `useForm` composable for
  client-side state and validation rendering.
- **Typed routing** via Wayfinder — no hardcoded URLs in Vue. Import from
  `@/actions/` for controller actions and `@/routes/` for named routes.
- **Auth/onboarding** wired through Fortify actions
  (`CreateNewUser`, `ResetUserPassword`) rather than custom controllers where possible.

When a planning doc says "Livewire component X," the equivalent here is "Inertia page X
backed by controller Y, with form state in `useForm` and routes resolved through
Wayfinder."

---

## 2. Tenancy enforcement model

The "Golden Rule" trait in `potential-planning-phases.md` resolves the current
organization from `session('current_org_id')`. That is necessary for the UI context
switcher but insufficient as the sole gate.

### 2.1 Container-bound `CurrentTenant`

Bind the resolved organization to the container per request:

```php
app()->scoped(CurrentTenant::class, function (Application $app): CurrentTenant {
    $user = $app['auth']->user();
    $orgId = $app['session']->get('current_org_id');

    // Fail closed: a session value alone is not authorization.
    abort_unless(
        $user && $user->organizations()->whereKey($orgId)->exists(),
        403,
    );

    return new CurrentTenant($app['db']->table('organizations')->find($orgId));
});
```

`BelongsToOrganization` then reads from the container, not the session directly. This
gives a single chokepoint to test and avoids accidental "no tenant set, no scope
applied" leaks.

### 2.2 Queues, console, scheduled jobs

Queued jobs and Artisan commands have **no session**. The naive
`if (session()->has(...))` short-circuit silently drops the global scope, which is
exactly where cross-tenant leaks happen.

Rules:

- Every queued job that touches tenant data **takes an `organization_id` parameter**
  and rebinds `CurrentTenant` at the top of `handle()`. No exceptions.
- Console commands that touch tenant data **must** accept an `--organization=` option
  or run for-each-organization explicitly.
- The global scope **throws** when `CurrentTenant` is not bound in non-auth contexts
  rather than returning unscoped results. A noisy failure beats a silent leak.

### 2.3 Authorization (Policies)

Tenancy scoping prevents cross-organization access. **Policies** prevent
wrong-role-within-organization access. Both are required.

Create policies for `Team`, `Player`, `Season`, `Division`, `Location`, `Form`,
`Submission`, `Payment`, `Invitation`. Each policy checks the acting user's role in
the current organization (resolved from `organization_user.role`). Use
`Gate::before()` for org owners.

### 2.4 Role as a PHP enum

`organization_user.role` is stored as `string` in the schema doc. Back it with a PHP
enum (`App\Enums\OrganizationRole`) so policies and casts are type-safe:

```php
enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Coach = 'coach';
    case Guardian = 'guardian';
}
```

Cast `role` on the pivot model to this enum. No string comparisons in policies.

### 2.5 Coaches per team

`teams.head_coach_id` only fits one user. Replace it with a `team_user` pivot:

```
team_user
- team_id
- user_id
- role (head_coach | assistant_coach | team_admin)  -- PHP enum
- timestamps
- UNIQUE (team_id, user_id, role)
```

Promote `head_coach_id` to a derived accessor on `Team` that returns the
`head_coach`-role member.

---

## 3. Migration / constraint matrix

Add these at migration time. Retrofitting uniqueness constraints to dirty data is
painful; do it now while the tables are empty.

### 3.1 Required UNIQUE constraints

| Table              | Columns                                                | Notes                                                                                  |
|--------------------|--------------------------------------------------------|----------------------------------------------------------------------------------------|
| `organization_user`| `(organization_id, user_id)`                           | A user has one row per org.                                                            |
| `team_user`        | `(team_id, user_id, role)`                             | One person can be head + admin, but not two head coaches.                              |
| `team_player`      | `(team_id, player_id)`                                 | A player is on a team once.                                                            |
| `team_player`      | `(team_id, jersey_number)` WHERE `jersey_number IS NOT NULL` | Partial unique — supports nullable jersey while preventing duplicates.        |
| `guardian_player`  | `(guardian_id, player_id)`                             |                                                                                        |
| `seasons`          | `(organization_id)` WHERE `is_active = true`           | Partial unique — at most one active season per org.                                    |
| `teams`            | `(organization_id, season_id, slug)`                   | Slugs unique within a season, not globally.                                            |
| `divisions`        | `(organization_id, name)`                              |                                                                                        |
| `locations`        | `(organization_id, name)`                              |                                                                                        |
| `organizations`    | `(slug)`                                               | Global — used for public URLs.                                                         |
| `guardians`        | `(organization_id, email)` (soft-unique on dedupe)     | See §5.2 for the dedupe flow.                                                          |

### 3.2 Required composite indexes

Every seasonal query filters by both `organization_id` **and** `season_id`. Single-column
indexes won't be selective enough at scale.

- `teams (organization_id, season_id)`
- `team_player (team_id)` — already implied via FK, but verify it exists.
- `submissions (form_id, payment_status)`
- `payments (organization_id, status)`
- `players (organization_id, last_name, dob)` — supports the dedupe match in §5.2.

### 3.3 Soft-delete cascade rules

`players` and `teams` use `softDeletes`. Decide once, document, and enforce in
observers (don't rely on DB cascades for soft deletes):

- Soft-deleting a `team` → soft-delete its `team_player` rows. Games tied to the team
  are **archived**, not deleted (preserve historical record).
- Soft-deleting a `player` → soft-delete `team_player` rows for that player in the
  **current and future** seasons only. Historical rosters stay intact.
- Restoring follows the inverse path.

### 3.4 Cashier billable model

Cashier's default is to put `subscriptions` on the `User` model. **We bill the
organization**, so configure `Organization` as the Cashier billable:

- `Organization` uses the `Billable` trait.
- The Cashier migration is published and `subscriptions.organization_id` replaces the
  default `user_id`/morph columns.
- Webhook handlers resolve the organization from `stripe_customer_id`, not the user.

Don't accept Cashier's defaults — they will silently bill users.

---

## 4. Form versioning

Forms are JSON-schema-driven. If a published form is edited after parents start
submitting, old submissions render against the new schema and look wrong (missing
fields, renamed labels, etc.).

### Decision

Snapshot the schema **per submission**. Cheapest option that works:

```
submissions
- ...existing columns...
- schema_snapshot (JSON) -- copy of forms.schema at submit time
- schema_version (integer) -- monotonic, incremented when forms.schema changes
```

Increment `forms.schema_version` in an observer whenever `schema` is dirtied and the
form has `status = published`. Submissions always render from `schema_snapshot`, not
from the live `forms.schema`.

If the form needs a structural migration later (e.g., field type changed), write a
one-off job that rewrites `submissions.data` against the new schema. Schema-snapshot
keeps that job deterministic.

### File uploads inside forms

Add a `submission_attachments` table:

```
submission_attachments
- id
- submission_id (FK)
- field_key (string) -- which form field this attachment satisfies
- disk (string)
- path (string)
- original_filename (string)
- mime_type (string)
- size_bytes (integer)
- timestamps
```

Birth certificates and insurance cards are first-class — they don't belong in the
`submissions.data` JSON.

---

## 5. Submission → Player materialization

`initial-research.md` says a submission "automatically creates/updates a Player."
That's not enough — without a dedupe key, two parents registering the same kid
produce two `players` rows.

### 5.1 Player matching

Match candidates by `(organization_id, last_name, dob)` (case-insensitive last name).

- **0 matches** → create a new player.
- **1 match** → present a confirmation step to the admin: "We think this is Johnny
  Smith (existing). Confirm?" Default is to merge; admin can force-create.
- **2+ matches** → admin must pick. Never auto-merge.

Never silently merge or silently create. Audit-log every choice.

### 5.2 Guardian matching

Match by `(organization_id, lower(email))`. Soft-unique (no DB constraint) so we can
hold duplicates temporarily but the dedupe step always runs on form submission.

Guardian → user account is opt-in: leave `user_id` null until the guardian claims
the account via the magic-link "Claim your account" email.

### 5.3 Promoted columns

`initial-research.md` calls out that JSON is not searchable. Decide once which form
fields are **always** promoted to first-class columns on `players` / `submissions`:

| Form field      | Lands on                  |
|-----------------|---------------------------|
| First/last name | `players.first_name/last_name` |
| Date of birth   | `players.dob`             |
| Jersey size     | `players.jersey_size`     |
| Allergies       | `players.medical_notes`   |
| Parent email    | `guardians.email`         |
| Parent phone    | `guardians.phone`         |

Promotion happens in the same DB transaction as the submission insert.

---

## 6. Payment ledger

`submissions.payment_status` enum is not a ledger. It can't represent partial
payments, refunds, Stripe webhook idempotency, or Connect application fees.

### Decision: dedicated `payments` table

```
payments
- id (UUID)
- organization_id (FK)
- submission_id (FK, nullable) -- registration fee
- player_id (FK, nullable) -- one-off charges
- stripe_payment_intent_id (string, unique)
- stripe_charge_id (string, nullable)
- amount_cents (integer)
- currency (char(3))
- status (enum: pending | succeeded | failed | refunded | partially_refunded)
- refunded_amount_cents (integer, default 0)
- application_fee_cents (integer, nullable) -- platform's cut via Connect
- failure_reason (string, nullable)
- paid_at (timestamp, nullable)
- timestamps
```

### 6.1 Webhook idempotency

Stripe webhooks redeliver. Add a `stripe_webhook_events` table keyed by Stripe
event id, persisted **before** processing, so reprocessing is a no-op:

```
stripe_webhook_events
- id (Stripe event id, primary key)
- type (string)
- payload (JSON)
- processed_at (timestamp, nullable)
- timestamps
```

Process inside `DB::transaction`; mark `processed_at` at the end.

### 6.2 Connect for parent → org payments

The platform (us) takes an application fee; the org receives the rest. Required:

- `organizations.stripe_connect_account_id` (string, nullable).
- Onboarding gate: an org can't enable paid forms until Connect onboarding completes.
- `payments.application_fee_cents` records the platform fee.
- Refunds reverse the application fee proportionally.

### 6.3 Refunds

Refunds always go through Stripe (never write-only DB updates). Webhook updates
`refunded_amount_cents` and flips `status` to `partially_refunded` or `refunded`.

---

## 7. COPPA / parental consent

Youth sports means minors under 13. COPPA applies. **This is a legal blocker, not a
nice-to-have.**

### Decision: explicit consent capture

```
consents
- id
- organization_id (FK)
- guardian_id (FK)
- player_id (FK)
- consent_type (enum: registration | media_release | medical_treatment | code_of_conduct)
- consent_text_snapshot (text) -- the exact text shown at consent time
- consent_text_version (integer)
- accepted_at (timestamp)
- ip_address (string)
- user_agent (string)
- timestamps
```

Rules:

- Consent is captured **before** submission insert, in the same transaction.
- Text is snapshotted — if legal updates the language, old consents stay valid for
  the version they accepted.
- Withdrawal is supported: a `withdrawn_at` column lets a guardian revoke consent;
  data retention rules trigger from there.

### Data retention / right to delete

Add a documented "delete my child's data" workflow:

1. Guardian requests deletion from their account.
2. Admin reviews (some data — financial records, code-of-conduct violations — has
   legal retention requirements).
3. Approved deletions soft-delete the player and anonymize their JSON submissions
   (`first_name='[deleted]'`, etc.) rather than hard-deleting, so historical roster
   stats stay sane.

### Background checks for coaches

State law varies; many states require coaches working with minors to have a current
background check. Add:

```
coach_background_checks
- id
- organization_id (FK)
- user_id (FK)
- provider (string) -- e.g., 'NCSI', 'Sterling'
- status (enum: pending | cleared | flagged | expired)
- cleared_through (date)
- timestamps
```

Block `team_user.role = head_coach | assistant_coach` assignment when the user
doesn't have a current `cleared` check.

---

## 8. Operational concerns the planning docs miss

### 8.1 Email and notifications

Use Laravel notifications for all of these — none belong in synchronous request flow:

- Parent: registration confirmation, payment receipt, refund notice, season-rollover
  announcement, "your child made the roster" notice.
- Admin: new submission alert, payment failure, large-batch import results.
- Auth: password reset, email verification, 2FA recovery (already handled by
  Fortify).

Queue everything. Notifications are `ShouldQueue`.

### 8.2 Queue work

Async by default:

- CSV roster import.
- Bulk email/notification fan-out.
- PDF roster / waiver generation.
- Stripe webhook processing.
- Season rollover (can clone hundreds of teams).
- Background-check status polling.

### 8.3 Rate limiting

Public form submission is unauthenticated. Rate limit by `(form_id, ip)`:

```php
RateLimiter::for('public-form-submit', fn (Request $r) =>
    Limit::perHour(10)->by($r->ip().'|'.$r->route('form'))
);
```

Apply to the public registration controller.

### 8.4 Audit log

Install `spatie/laravel-activitylog` (or equivalent). Log:

- Player created / soft-deleted / restored / merged.
- Roster changes (player added/removed from team).
- Role changes on `organization_user` and `team_user`.
- Form publish/unpublish/edit.
- Consent grant/withdraw.
- Payment refund (in addition to the Stripe ledger).

"Who deleted this player?" comes up in week one.

### 8.5 Caching

When caching tenant data, **always** include `organization_id` in the cache key.
Don't trust scopes to scope cache; they don't. Use `Cache::tags(["org:$orgId"])`
where the driver supports tags, or prefix keys.

### 8.6 Testing strategy

Every feature test for a tenant resource must assert a **negative tenancy case**:
"Org A authenticated user gets 404/403 when fetching Org B's resource." Bake this
into a shared dataset/trait so it's hard to forget.

For policies, every action gets a positive and negative test per role.

---

## 9. Season rollover (corrections to `season-archiving.md`)

The pseudocode there is mostly right; three fixes:

1. **Activate atomically.** The `is_active` partial unique constraint (§3.1) will
   reject the naive "create new with `is_active=true`, then update old to `false`"
   order. Do it the other way: deactivate first inside the transaction, then
   create the new active season.
2. **Make the rollover wizard-driven.** Checkboxes for what to clone — at minimum:
   teams (default on), divisions (default on, idempotent merge), locations
   (default on), form templates (default off), default fee structure (default off).
   Hardcoded "clone teams only" is the wrong default for many orgs.
3. **Roster carry-over is opt-in per division.** Some orgs want "everyone moves up
   a division" prefilled; some want a blank slate. Make it a wizard step.

---

## 10. Implementation checklist (ordered)

This replaces the looser checklist at the bottom of `potential-planning-phases.md`.

1. [ ] Translate Phase 1 (tenancy) to Inertia/Vue terms; build `CurrentTenant`
       service, `BelongsToOrganization` trait, and policies before any feature work.
2. [ ] Migrations with **all** constraints from §3 baked in from day one.
3. [ ] `organization_user` + `team_user` pivots with PHP enum roles.
4. [ ] Cashier configured against `Organization` (not `User`); webhook handler with
       `stripe_webhook_events` idempotency table.
5. [ ] Onboarding flow: org signup → Stripe checkout → org dashboard. Middleware
       gates non-active subscriptions.
6. [ ] Org settings CRUD: seasons (with partial unique enforcement),
       divisions, locations, invitations.
7. [ ] Consent capture flow + audit log + COPPA documentation in admin help.
8. [ ] Form builder with schema versioning (§4) and `submission_attachments`.
9. [ ] Public submission flow: rate-limited, dedupe step for players/guardians,
       Stripe Connect for fees, consent capture in the same transaction.
10. [ ] Roster manager (Inertia/Vue page, not Livewire dual-listbox).
11. [ ] Season rollover wizard with the corrections in §9.
12. [ ] Background-check gate before coaches can be assigned.
13. [ ] Notification fan-out (queued) and email templates.
14. [ ] Tenancy isolation test suite (§8.6) wired into CI.

Each item should land behind a feature test that covers happy path **and** the
tenancy isolation negative case.

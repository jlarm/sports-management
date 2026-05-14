---
name: players-guardians
description: Players vs guardians vs users — unclaimed contacts, guest registration, claim-later
metadata:
  type: project
---

**Three distinct concepts, do not conflate:**
- `users` — anyone who can log in (admin, coach, parent who created an account).
- `guardians` — parent/guardian contact info (name, email, phone). May or may not have a login.
- `players` — the kid. Linked to guardians via `guardian_player` pivot with a `relationship` column.

**The unclaimed guardian:** `guardians.user_id` is **nullable**. If null → "unclaimed" contact (admin typed them in, or they registered as a guest). If populated → they've claimed their login and can manage their kid's profile.

**Parent registration UX rule:** Don't force account creation up front — it kills conversion. Let parents register as a guest, then offer "Claim Account" later. Implementation: form submission creates the guardian (and player) without a `user_id`; a later flow links a `user_id` when they sign up.

**Two creation paths for players:**
- Admin-created: manual entry from the dashboard.
- Parent-created: a registration form submission auto-creates/updates the player.

Both end up in the same "Unassigned Player Pool" the team manager rosters from. See [[form-builder]] for the submission → player materialization step and [[schema-plan]] for column definitions.

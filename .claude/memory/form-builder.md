---
name: form-builder
description: Form builder uses JSON schema/data columns, but must duplicate searchable fields onto players
metadata:
  type: project
---

The registration form builder uses JSON storage to avoid an EAV nightmare:
- `forms.schema` (JSON) — the field definitions: `[{type: 'text', label: 'Allergies?'}, ...]`.
- `submissions.data` (JSON) — the parent's answers: `{allergies: 'Peanuts'}`.

**Critical pitfall:** JSON columns are not efficiently searchable. Any field admins will need to filter or sort by — `dob`, `jersey_size`, `graduation_year` — must be **duplicated** out of the JSON into a real column on `players` when the submission is processed. Keep the JSON as the source of truth for the raw answer, but mirror queryable fields.

**Submission flow:** parent submits form → create `Submission` row → create-or-update matching `Player` (this populates the "unassigned player pool" the team manager rosters from).

UI side: admin uses a drag/drop builder Livewire component to author `forms.schema`. Public-facing Livewire component at `app.com/org/{slug}/register` renders the form to parents. See [[payments]] for the Stripe Connect piece (parent → org payment) and [[players-guardians]] for the player/guardian creation rules.

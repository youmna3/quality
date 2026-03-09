# Quality Dashboard User Guide

This guide explains how to use Quality Dashboard day to day. It is written for end users, not developers.

## What the app does

Quality Dashboard runs the full weekly quality cycle for tutor sessions:

1. Admin prepares tutors, reviewers, mappings, and cycle dates.
2. Tutors can submit session or student issues before review.
3. Reviewers complete assigned quality reviews.
4. Admin checks reports, reviews flags, and publishes weekly reports.
5. Tutors read their published reports and submit objections when needed.
6. Team leads monitor mentor, tutor, and reviewer performance.

## Who uses it

- `Admin`: full operational control.
- `Reviewer`: completes reviews and requests admin edits when locked.
- `Tutor`: sees assignments, submits issues, views reports, and objects to flags.
- `Team Lead`: sees analytics for mentors mapped to that team lead.

## Common screen patterns

- The top navigation changes based on your role.
- Most pages include a `Week` selector so you can move between cycles.
- Green banners confirm successful actions. Red banners show errors or blocked actions.
- Large KPI cards summarize the current week at a glance.
- Most lists use tables with pagination at the bottom.

## Quick start by role

### Tutor

1. Open `Overview` to check your current week assignment status.
2. Submit a `Session Issue Form` or `Student Issue Form` if there is something reviewers should know.
3. Open `Reports` after the week is published to view your performance report.
4. Open `Flags` to see any flags raised on your sessions and submit objections if needed.

### Reviewer

1. Open `Overview` to see your assigned tutors for the selected week.
2. Click `Start Review` or `Edit Review` for an assignment.
3. Complete session data, comments, score, and flags.
4. Submit the review before the cycle deadline.
5. If editing is locked, send an admin edit request from the dashboard.

### Admin

1. Maintain tutor and reviewer accounts.
2. Set mentor and team lead mappings.
3. Configure weekly cycle dates and assignments.
4. Review reports, publish them to tutors, and handle flags and objections.
5. Monitor analytics and edit history.

### Team Lead

1. Open `Overview`.
2. Select the week you want.
3. Review mentor score averages, reviewer performance, and objections.

## Tutor guide

### Tutor overview

The tutor home page shows:

- Your `Tutor ID`
- Your assigned `Mentor`
- Whether your current week is assigned for review
- Quick buttons for `Weekly Report`, `Flags Tab`, `Session Issue Form`, and `Student Issue Form`
- Your recent submitted issues

### Session Issue Form and Student Issue Form

Use these forms when reviewers need extra context for a session.

Each submission includes:

- `Session Date`
- `Slot`
- `Group ID`
- Issue details

Important behavior:

- The app tries to auto-fill `Group ID` when slot and session date match an existing group mapping.
- Reviewers can automatically see matching issue data when the tutor, date, and slot match.
- Submit one clear issue entry per real issue. Keep the text specific and factual.

### Reports page

The `Reports` page becomes useful after admin publishes the week.

What you can do there:

- Switch between published weeks
- View your average score and best score
- View category averages across the five main groups:
  `Setup`, `Attitude`, `Preparation`, `Curriculum`, and `Teaching`
- Read session-by-session positive comments, improvement points, and flag notes
- Download the report as `CSV`
- Download the report as `PDF`
- Use `Share LinkedIn` to share the current report link

Important notes:

- Reports are hidden until admin publishes that week.
- The tutor report is designed to be shareable and does not show reviewer identity.
- Scores are shown out of `100`.

### Flags page

The `Flags` page shows every flag raised on your reviewed sessions.

Each row includes:

- Session date, slot, and group
- Flag color: `yellow`, `red`, or `both`
- Flag subcategory, reason, and duration
- Session link
- Screenshot link, if one was uploaded
- Flag status
- Objection status

### How tutor objections work

You can submit an objection from the `Flags` page.

Rules:

- The objection window closes `2 days` after the flag was created.
- You cannot object to a flag that was already `removed`.
- If your objection is still open, you can reopen the objection form and update the text.
- Once submitted, the objection status becomes `pending` until admin reviews it.

Recommended objection style:

- Explain exactly what happened in the session.
- Refer to the session date, slot, and context.
- Keep the objection professional and evidence-based.

## Reviewer guide

### Reviewer overview

The reviewer home page shows:

- Week cycle start and deadline
- KPI cards for assigned, submitted, pending, and late work
- Your assignment table
- A weekly leaderboard
- A `Resources` tab with the review checklist and slot reference

Assignment statuses:

- `Pending`: no review submitted yet
- `Pending Late`: still not submitted after the deadline or late threshold
- `Submitted`: submitted on time
- `Submitted Late`: submitted after the deadline or late threshold

### Starting a review

Open `Start Review` from the assignment table.

The review form includes these main sections:

1. `Session Data`
2. `Previous Flags Detection`
3. `Flags`
4. `Comments & Score`

### Session Data rules

You must complete:

- Tutor role: `main` or `cover`
- Session date
- Slot
- Group ID
- Recorded link

Important rules:

- Friday slots must use a Friday session date.
- Saturday slots must use a Saturday session date.
- The session date must stay inside the active week cycle when cycle dates are set.
- Group ID can be auto-filled from uploaded group mappings.
- Issue text can be auto-filled from tutor issue forms or admin-uploaded issue rows.

### Previous Flags Detection

This section helps reviewers avoid missing repeated issues.

What it shows:

- Previous flags for the same tutor
- Negative comment history
- Tutor review history from earlier sessions

Important rule:

- If the same flagged subcategory already appeared at least `3 days` earlier, the system recommends `red`.
- If you still submit that flag as `yellow`, the app can auto-escalate it to `red` during save.

### Adding flags

Each flag entry can include:

- Type: `none`, `yellow`, `red`, or `both`
- Subcategory
- Reason
- Duration text
- Optional screenshot

Use flags carefully:

- `yellow` for a flagged issue that does not yet qualify for red
- `red` for a repeated or more serious issue
- `both` when both levels were recorded in the same review context

### Comments and score

The scoring model is built from the review criteria library.

Key rules:

- Every criterion starts from `5` points.
- The full review total is out of `100`.
- Negative comments reduce the linked criterion score.
- `Red` and `both` flags add extra score penalty to the linked criterion.
- You must choose at least `one positive comment in every main category` before submitting.

Main categories:

- `Setup`
- `Attitude`
- `Preparation`
- `Curriculum`
- `Teaching`

### Reviewer edit limits

Reviewers can edit their own submitted review only while it is still open.

Direct reviewer editing stops when:

- The cycle deadline has passed, or
- The review already used `2 reviewer edits`

After that:

- The review becomes locked for the reviewer.
- The dashboard shows `Request Admin Edit`.
- You must describe the exact correction needed.

### Resources tab

The `Resources` tab gives quick reminders:

- Review checklist
- Slot reference
- Auto data sources used by the form

## Admin guide

### Admin overview

The admin dashboard gives quick access to the main management pages:

- `Tutors Data`
- `Reviewer Accounts`
- `Mentor Coordinators`
- `Team Leads`
- `Weekly Assignments`
- `Analytics`
- `Reports`
- `Flags`

### Tutors Data

Use this page to:

- Create a new tutor manually
- Edit tutor data
- Deactivate a tutor without deleting the record
- Upload tutors in bulk from a sheet

The bulk upload expects these columns:

`Tutor ID, Name, Mentor, Grade, Zoom email, Zoom password, Dashboard Email, Dashboard Password, Status`

### Reviewer Accounts

Use this page to create and maintain reviewer users.

Reviewer types:

- `mentor`
- `coordinator`

### Mentor Coordinators

This page is the mentor-to-coordinator matrix.

Use it to:

- Search mentor names
- Assign a coordinator reviewer to each mentor
- Save all assignments in one step

This mapping matters because weekly assignment balancing depends on it.

### Team Leads

Use this page to:

- Create team lead accounts
- Edit team lead accounts
- Map mentors to team leads

Team lead analytics only include mentors mapped here.

### Weekly Assignments

This page controls the review cycle for each week.

What you can do:

- Select a week
- Search assignments
- Run `Auto Assign Week`
- Run `Redo Assignment`
- Set `Cycle Start` and `Cycle Deadline`
- Upload session group mapping CSV
- Upload session issue CSV
- Review the final assignment table

Recommended weekly admin order:

1. Make sure tutors and reviewers are up to date.
2. Make sure mentor-coordinator mappings are complete.
3. Set the week cycle start and deadline.
4. Upload session data if needed.
5. Run `Auto Assign Week`.
6. Use `Redo Assignment` only if you intentionally want to rebalance the week.

### Session data uploads

#### Session Group Mapping CSV

Required columns:

- `Tutor ID`
- `Slot`
- `Group ID`

Optional column:

- `Session Date`

This data helps the reviewer form auto-fill group IDs.

#### Session Issue CSV

Required columns:

- `Tutor ID`
- `Issue`

Optional columns:

- `Slot`
- `Session Date`

This data helps the reviewer form auto-fill issue text.

### Analytics

The admin analytics page supports both:

- A specific week
- `All Weeks`

Tabs on this page:

- `Overview`
- `Trend Charts`
- `Reviewer Performance`
- `Quality Signals`

Main insights available:

- Reviewer completion and lateness
- Top fast reviewers
- Average score trends by week
- Flag trends by week
- Reviewer edit activity
- Average score by mentor
- Average score by team lead
- Reviewer flag and objection analytics
- Most repeated negative comments
- Recent report edit trail

### Reports

Use `Reports` to manage submitted reviews.

What you can do:

- Search reports by tutor
- Switch week
- Export the selected week as CSV
- Publish the selected week to tutors
- See pending reviewer edit requests
- Open any report in `Edit Report`

Inside `Edit Report`, admin can update:

- Session data
- Positive and negative comments
- Criterion scores
- Total score

Important behavior:

- Saving a report recalculates group percentages and the total score.
- Saving a report automatically completes any pending edit request linked to that report.

### Flags

Use the `Flags` page to manage flag decisions and objections.

What you can do:

- Search by tutor, subcategory, reason, or objection text
- Filter by color
- Filter by status
- Change the flag status
- Accept or reject pending objections
- Add an optional admin note when handling an objection

## Team lead guide

### Team lead overview

The team lead dashboard is analytics-only.

It shows:

- Mentor count
- Tutor count
- Average tutor score
- Total flags
- Total objections

Detailed sections:

- `Mentors & Tutor Scores`
- `Reviewer Performance`
- `Most Objections by Reviewer`

Important scope rule:

- The dashboard only includes mentors mapped to your team lead account by admin.

## Status guide

### Flag colors

- `yellow`: a flagged issue that does not yet qualify for red
- `red`: a repeated or higher-severity issue
- `both`: both levels were recorded for the same review context

### Flag status

- `open`: newly submitted and still active
- `appealed`: tutor submitted an objection
- `accepted`: admin accepted the flag outcome
- `partial`: admin kept part of the flag decision
- `removed`: admin removed the flag from active reporting
- `resolved`: admin finished handling the case

### Objection status

- `none`: no objection submitted
- `pending`: waiting for admin review
- `accepted`: admin accepted the objection
- `rejected`: admin rejected the objection

## Common questions

### Why can't I edit my review anymore?

Most likely one of these is true:

- The cycle deadline already passed.
- You already used the `2 reviewer edits` allowed for that review.

In that case, use `Request Admin Edit` from the reviewer dashboard.

### Why is my weekly report missing?

Tutor reports appear only after admin publishes that week from the `Reports` page.

### Why is the objection button disabled?

Tutor objections close when:

- The flag was already `removed`, or
- More than `2 days` passed since the flag was created

### Why does the team lead dashboard show no data?

Usually this means no mentors were mapped to that team lead account yet.

### Why does a yellow flag turn red after submit?

The system checks older flags for the same tutor and subcategory. If the repeated-issue rule is met with the required time gap, the app can auto-escalate the flag to red.

## Best practices

- Keep session dates, slots, and group IDs accurate before saving.
- Use issue forms early so the reviewer sees the right context.
- Write comments that are specific enough to support the score and any flag.
- Use objections and admin edit requests for factual corrections, not general frustration.
- Publish reports only after report edits and flag decisions are ready.

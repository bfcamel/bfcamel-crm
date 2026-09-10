# Submissions

The **BfCamel CRM → Submissions** screen is the operational queue for incoming form data.

## Submission record

A submission stores the form and form revision that received it, a UUID, linked contact when available, workflow status, priority, responsible WordPress user, original payload, source metadata and received time.

If contact matching detects a conflict, BfCamel CRM can place the submission into the built-in `needs_review` status when that status is enabled.

## Search and filters

The submissions list supports search and filtering by:

- Search text, including ID, name, email, phone and form data
- Status
- Form
- Responsible employee
- Priority
- Tag
- Date from / date to

Quick views include all submissions, the current user's submissions, the configured default status, the highest configured priority, unassigned submissions and the built-in review status when applicable.

The list is paginated at 25 items per page.

## Editing a submission

Users with the **Edit submissions** capability can update workflow data on an individual submission, including status, priority, responsible employee and tags. Existing historical status or priority values are preserved for old records even if a definition later becomes disabled.

Internal notes can be added to a submission. Notes are stored separately and included in WordPress personal-data export when the submission is associated with a matching contact.

## Bulk actions

For selected submissions, authorized users can:

- Set status
- Set priority
- Set responsible employee
- Add tags
- Remove tags

Bulk operations validate the requested action and update records transactionally where the underlying service requires it. Invalid or incomplete actions do not report a false success.

## Tags

Submission tags use the shared BfCamel CRM tag catalog. A tag may be configured for submission use, contact use, or both. See [Workflow and Tags](Workflow-and-Tags.md).

## Export

Users who have both **Export CRM data** and access to submissions can export the current filtered result set as CSV or XLSX. Exported submission columns include core workflow fields plus discovered payload field keys.

See [Export](Export.md).

## Contact synchronization

During public submission processing, BfCamel CRM attempts to resolve and synchronize a CRM contact from fields mapped in the form schema. The submission records the resulting contact ID and contact synchronization state. The full form submission, contact synchronization, consent capture and activity-log creation are performed inside a database transaction so partial CRM records are not intentionally committed as a successful submission.

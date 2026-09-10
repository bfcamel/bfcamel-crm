# Workflow and Tags

The **BfCamel CRM → Workflow** screen controls statuses, priorities, field-based priority rules and the shared tag catalog.

## Built-in statuses

A fresh installation defines:

- `new` — New
- `in_progress` — In progress
- `waiting` — Waiting
- `completed` — Completed
- `needs_review` — Needs review

Each status has a name, color, weight, enabled state and default flag. Built-in names are translated according to the active WordPress user locale.

## Built-in priorities

A fresh installation defines:

- `normal` — Normal
- `high` — High
- `urgent` — Urgent

Priorities use the same configurable model: name, color, weight, enabled state and default flag.

## Defaults and safety rules

BfCamel CRM guarantees at least one enabled status and one enabled priority. It also guarantees one default among the enabled definitions. If a configured default becomes invalid, the service falls back to an enabled definition.

Form revisions store their own default status and default priority. These values are used when a new submission is received.

## Custom definitions

Administrators can add or edit workflow definitions. Slugs are sanitized and bounded to the database column size: status slugs fit the 40-character status column and priority slugs fit the 20-character priority column.

Weights determine ordering. For priorities, the active priority with the highest weight is used by the “highest priority” quick view.

## Priority automation rules

Automation rules can change the priority of a new submission based on a field value. Each rule is tied to one form and one field and may use one of these operators:

- `equals`
- `not_equals`
- `contains`
- `filled`

A matching enabled rule replaces the current submission priority with the rule's configured priority. Rules are evaluated in their stored order; if multiple rules match, later matching rules can replace the priority selected by an earlier one.

Consent fields and content/heading fields are excluded from the automation field catalog.

## Shared tag catalog

BfCamel CRM uses one tag catalog for both submissions and contacts. Tags can be associated with either entity type through separate relation tables.

A tag can be scoped to:

- Submissions
- Contacts
- Both

If no explicit scope exists for a tag, the service allows it in both areas.

Tags can be assigned individually and through bulk actions. Tag catalog changes and relationship changes are logged where the corresponding administrative operation records history.

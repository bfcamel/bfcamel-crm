# Data Model

BfCamel CRM 0.5.1 uses database schema version **6**. Tables use the active WordPress table prefix followed by `bfcamel_crm_` and are created as InnoDB tables.

## Core tables

| Table suffix | Purpose |
| --- | --- |
| `forms` | Form identity, status, current revision and current settings snapshot |
| `form_revisions` | Immutable published form schemas and settings by version |
| `contacts` | Core contact record: display name, organization and status |
| `contact_emails` | Contact email values, normalized values and primary flag |
| `contact_phones` | Contact phone values, normalized values and primary flag |
| `contact_fields` | Custom contact fields by key |
| `submissions` | Form submissions, workflow state, contact link, payload and source metadata |
| `consent_events` | Append-style consent history and evidence snapshots |
| `tags` | Shared tag catalog |
| `submission_tags` | Many-to-many relation between submissions and tags |
| `contact_tags` | Many-to-many relation between contacts and tags |
| `notes` | Internal notes for contacts and submissions |
| `activity_log` | Auditable CRM activity events and metadata |

## Forms and revisions

`forms` stores the stable form ID and slug plus the `current_revision_id`. Each publish operation creates a new `form_revisions` row with a monotonically increasing version for that form.

A revision stores `schema_json` and `settings_json`. Submissions reference the exact `revision_id` that rendered the form at submission time.

## Contacts

The main contact table deliberately does not place email and phone in single columns. Email and phone identifiers are stored in dedicated relation tables, allowing multiple values per contact and normalized matching.

Custom fields are stored in `contact_fields` with a unique `(contact_id, field_key)` key.

## Submissions

Important submission fields include:

- `form_id` and `revision_id`
- `submission_uuid`
- `contact_id`
- `status`
- `contact_sync_status`
- `assigned_to`
- `priority`
- `payload_json`
- `source_url`
- `source_ip`
- `user_agent`
- `submitted_at`

The schema indexes the main filtering fields used by the administrative interface.

## Consent events

Consent events store contact/submission references, consent type, status, form and revision references, serialized document evidence, source metadata, source type, recording user and timestamp.

Manual and form-originated changes are stored as separate events rather than overwriting the previous state.

## Notes and activity

Both notes and activity records use an `entity_type` plus `entity_id` model, allowing the same tables to represent contact and submission history.

## Plugin options

Important WordPress options include:

- `bfcamel_crm_db_version`
- `bfcamel_crm_schema_error`
- `bfcamel_crm_legal_documents`
- `bfcamel_crm_settings`
- `bfcamel_crm_role_permissions`
- `bfcamel_crm_role_permissions_version`
- `bfcamel_crm_workflow_config`
- `bfcamel_crm_automation_rules`
- `bfcamel_crm_tag_scopes`

## Migration behavior

BfCamel CRM runs `dbDelta()` for the schema, ensures CRM tables are transactional, verifies the resulting structure and only then advances the stored schema version. A temporary schema lock prevents concurrent upgrade attempts.

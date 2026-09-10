# Contacts

The **BfCamel CRM → Contacts** area stores people and organizations linked to form submissions or created manually by CRM users.

## Contact data

A contact can contain:

- Display name
- Organization
- One or more email addresses
- One or more phone numbers
- Custom contact fields
- Tags
- Internal notes
- Personal-data consent history
- Marketing consent history
- Related submissions and activity

Email addresses and phone numbers are stored both in their original display form and in normalized form for matching.

## Creating contacts

Contacts can be created automatically from public form submissions when fields are mapped to CRM contact properties, or manually from the Contacts screen.

Manual creation supports the same main identity fields and tags. Users who have the **Manage consents** capability can also set consent states while creating or editing CRM contact information.

## Contact matching

When a form submission contains mapped email or phone identifiers, BfCamel CRM attempts to match an existing contact and synchronize the mapped fields. The contact service includes conflict handling and an identifier lock intended to reduce duplicate-contact creation during concurrent submissions.

A submission that cannot be safely linked because of conflicting identifiers is marked for review instead of silently merging incompatible identities.

## Search and filters

The contacts list supports search by name, organization, email or phone. It can also be filtered by:

- Tag
- Personal-data consent status
- Marketing consent status

The list is paginated at 25 contacts per page.

## Tags and bulk actions

Selected contacts can receive or lose tags in bulk. Contacts and submissions use the same tag catalog, while workflow settings can restrict an individual tag to one scope or allow it in both.

## Consent status

BfCamel CRM does not overwrite consent history with a single mutable checkbox. Instead, consent changes are recorded as events. The current contact status is derived from the latest valid event for each consent type.

Supported consent states are:

- Not specified (`unknown`)
- Granted (`granted`)
- Not granted (`denied`)
- Revoked (`revoked`)

Manual consent changes record who made the change and when. Form-originated consent events also keep form/revision and evidence information.

## Notes and history

Internal notes can be added to contacts. Important changes are also written to the CRM activity log, creating a traceable history for administrative work.

## Export

Contacts can be exported as CSV or XLSX. The current filtered result set can be exported, and selected contacts can be exported separately. Contact export includes name, email, phone, organization, status, tags, both consent states, and created/updated timestamps.

See [Export](Export.md).

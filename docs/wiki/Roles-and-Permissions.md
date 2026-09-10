# Roles and Permissions

BfCamel CRM uses dedicated WordPress capabilities so site owners can grant only the access a role needs.

## Default policy

Since version 0.5.0, BfCamel CRM follows a least-privilege default: **only administrators receive CRM permissions automatically**. Other WordPress roles start with no BfCamel CRM access until an administrator grants it in **BfCamel CRM → Settings → Access**.

Administrators always retain the managed CRM capabilities and are the only role allowed to manage global CRM settings.

## Managed capabilities

| Capability | Purpose |
| --- | --- |
| `bfcamel_crm_view_dashboard` | View the CRM dashboard |
| `bfcamel_crm_manage_forms` | Create, edit, archive and restore forms |
| `bfcamel_crm_view_submissions` | View and search submissions |
| `bfcamel_crm_edit_submissions` | Change submission workflow data and notes |
| `bfcamel_crm_manage_contacts` | View and manage CRM contacts |
| `bfcamel_crm_manage_consents` | Record manual consent-status changes |
| `bfcamel_crm_export_data` | Export CRM data |

Two additional internal access capabilities are managed by the plugin:

- `bfcamel_crm_access` — controls visibility/access to the top-level CRM area when the role has at least one primary CRM area permission.
- `bfcamel_crm_manage_settings` — global settings access; retained for administrators only.

## Dependency rules

Some permissions imply another permission:

- **Edit submissions** automatically enables **View submissions**.
- **Manage consents** automatically enables **Manage contacts**.

This prevents permission combinations that would allow an action without access to the corresponding interface.

## Recommended role patterns

A read-only intake role can receive dashboard and submission-view permissions only. A case-management role can additionally receive edit-submission and contact-management access. Consent-management and export access should be granted only when needed because they expose more sensitive operations.

These are examples rather than hard-coded roles; BfCamel CRM works with the WordPress roles present on the site.

## Legacy permission migration

Version 0.5.0 introduced role-permission matrix version 3. If a non-administrator role still has the unsafe legacy “everything enabled” matrix created by an older release, BfCamel CRM resets that role to the new least-privilege defaults during permission migration.

## Settings protection

The Settings screen checks both WordPress `manage_options` and `bfcamel_crm_manage_settings`. As implemented in 0.5.1, non-administrator roles cannot receive the settings capability through the role matrix.

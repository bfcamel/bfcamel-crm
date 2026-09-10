# Developer Guide

BfCamel CRM is a small, dependency-light WordPress plugin written in namespaced PHP. The main namespace is `BfCamel\\CRM`, loaded from `src/` by the plugin's own autoloader.

## Entry point

The main plugin file is `bfcamel-crm.php`. It defines the plugin metadata and constants, registers the autoloader, activation/deactivation hooks and boots `BfCamel\\CRM\\Plugin` on `plugins_loaded`.

The plugin protects against a second active copy by checking whether `BFCAMEL_CRM_FILE` is already defined before registering the full plugin.

## Main modules

The source tree is organized by responsibility:

- `src/Access/` — WordPress role and capability management.
- `src/Admin/` — CRM admin menu, dashboard, forms, contacts, submissions and workflow screens.
- `src/CRM/` — contact, submission, tags, notes, workflow and activity services.
- `src/Consent/` — consent events and evidence snapshots.
- `src/Database/` — schema installation, verification, transactions and activity logging support.
- `src/Export/` — CSV/XLSX export.
- `src/Forms/` — form repository, renderer and public submission handler.
- `src/Privacy/` — WordPress personal-data exporter, eraser and privacy-policy helper.
- `src/I18n.php` — bundled translation loading.

Frontend and administration assets are in `assets/`.

## Boot sequence

`Plugin::boot()` loads translations at `init`, schedules schema upgrades for admin/WP-CLI requests, registers roles, public form rendering, submission handling and privacy integration, then registers the administration layer when `is_admin()` is true.

Activation installs the database schema, seeds settings and workflow defaults and ensures role capabilities. Network-wide multisite activation is rejected.

## Form architecture

Forms have a stable row in `forms` and immutable rows in `form_revisions`. Each publish operation creates a new revision. Public rendering resolves the current revision, and every submission stores the revision ID that was used.

The schema is JSON and supports typed fields plus CRM mappings. Form settings are versioned with the schema.

## Public shortcodes

The renderer registers:

```text
[bfcamel_form id="1"]
[bfcamel_form slug="contact-form"]
```

It also keeps `[gfr_form ...]` as a backward-compatibility alias.

## Submission lifecycle

The public handler performs nonce and honeypot checks, rate limiting, schema-based validation, workflow evaluation, submission insertion, contact synchronization, consent capture and activity logging.

The storage path is transactional. If a critical contact, consent or activity operation fails, the transaction is rolled back and the form redirects with an error state.

After a successful commit, the plugin fires:

```php
do_action(
    'bfcamel_crm_submission_created',
    $submission_id,
    $contact_id,
    $form_id,
    $revision_id
);
```

This is the primary integration point exposed by the public submission flow in version 0.5.1.

## Database transactions

CRM tables are expected to use InnoDB. Schema installation verifies the database structure before updating the stored schema version. Core multi-record operations use `Schema::begin_transaction()`, `commit()` and `rollback()` so dependent records are not intentionally left half-written.

## Options and compatibility

Keep the following technical identity stable when developing upgrades:

- Plugin directory: `bfcamel-crm/`
- Main file: `bfcamel-crm.php`
- Text domain: `bfcamel-crm`
- Namespace: `BfCamel\\CRM`
- Option/action/capability prefix: `bfcamel_crm_`
- Database prefix after the WordPress prefix: `bfcamel_crm_`

Changing these casually can break in-place updates, translations, existing data or third-party integrations.

## Validation and CI

The release workflow validates PHP syntax and the smoke suite across PHP 7.4, 8.1, 8.3, 8.4 and 8.5. It also runs the duplicate-load regression test, JavaScript syntax checks and the official WordPress Plugin Check action.

Packaging is allowed only after validation and Plugin Check succeed. The release workflow then builds both a versioned ZIP and the stable `bfcamel-crm.zip` install/update package.

## Release packaging

Use `scripts/build-release.sh` or the GitHub Actions release workflow rather than zipping the repository root manually. GitHub's generic source archives are not the canonical WordPress update package.

## Coding changes

When changing form storage, workflow slugs, capabilities, option names or database columns, treat backward compatibility as part of the feature. Existing submissions and consent evidence are historical records and should not be silently rewritten by a new release.

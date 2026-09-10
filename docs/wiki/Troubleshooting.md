# Troubleshooting

## WordPress installs BfCamel CRM as a second plugin

Use the official `bfcamel-crm.zip` release package. GitHub's generic source-code ZIP may unpack into a different directory name, causing WordPress to treat it as another plugin.

The canonical directory is:

```text
wp-content/plugins/bfcamel-crm/
```

If two copies are active, BfCamel CRM 0.5.1 stops the later copy and shows an administrator notice. Remove the duplicate directory and keep the canonical installation.

## “A second active copy was detected”

This message means another loaded plugin file already defined the BfCamel CRM identity. Check `wp-content/plugins/` for old folders such as repository-name archives or manually renamed copies. Deactivate and remove the duplicate, then update using `bfcamel-crm.zip`.

## CRM database update is incomplete

BfCamel CRM blocks CRM operations and public submission handling while the stored schema does not match schema version 6.

Administrators may receive a detailed database-upgrade message. Check the database user's permissions and confirm the CRM tables can use InnoDB. Reloading an administration page lets the normal upgrade check run again after the underlying database problem is fixed.

Do not manually change the `bfcamel_crm_db_version` option to bypass verification.

## Public form does not appear

Check the following:

1. The shortcode uses an existing form ID or slug.
2. The form is published rather than archived.
3. The form has a current revision.
4. The CRM database schema is current.
5. Use the preferred shortcode, for example `[bfcamel_form id="1"]`.

Administrators with form-management access may see a “form not found” notice where normal visitors receive no output.

## Form immediately returns an error

Possible causes include an invalid/expired nonce, missing required fields, invalid email or number input, a select/radio value not present in the stored form revision, rate limiting, an incomplete database schema, or a database failure during the transactional submission path.

If testing repeatedly, avoid submitting the same form multiple times within only a few seconds because the built-in basic rate limiter uses a short-lived fingerprint.

## Consent field is plain text instead of a link

Legal-document URLs are optional. If a token such as `{privacy_policy}` has no configured URL, BfCamel CRM displays its text without a link. Configure the URL under **BfCamel CRM → Settings → Legal documents** if you want the token rendered as a link.

## Consent field disappeared after leaving document URLs empty

In version 0.5.1, legal-document URLs do not hide consent fields. If a consent field is missing, check the current form revision in the form builder and make sure the field is part of the schema.

## Russian translation is not showing

BfCamel CRM loads bundled translations from `languages/` using the `bfcamel-crm` text domain. Confirm the WordPress user/site locale is Russian as appropriate and that `languages/bfcamel-crm-ru_RU.mo` is present in the installed plugin.

Built-in status and priority labels are translated at display time using the active WordPress locale.

## A staff role cannot see the CRM

Since version 0.5.0, non-administrator roles receive no CRM access by default. An administrator must grant the required permissions under **BfCamel CRM → Settings → Access**.

Remember that editing submissions implies viewing submissions, and managing consents implies managing contacts.

## Export button is missing or export is denied

The user needs `bfcamel_crm_export_data` plus access to the relevant CRM area. Contact export also requires contact-management access; submission export also requires submission-view access.

## Uninstall did not remove the CRM data

This is the intended default. Data is retained unless **Delete all BfCamel CRM data when the plugin is uninstalled** was enabled before uninstalling.

If the option was enabled, uninstall removes BfCamel CRM tables and settings. Always back up the database before intentionally enabling destructive uninstall cleanup.

## Multisite network activation fails

BfCamel CRM intentionally rejects network-wide activation. Activate the plugin separately for each site in the multisite network.

# Installation and Updates

## Install BfCamel CRM

1. Download the official installable `bfcamel-crm.zip` release package.
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Select `bfcamel-crm.zip`, install it, and activate **BfCamel CRM**.
4. Open **BfCamel CRM** in the WordPress administration menu.

The plugin creates and verifies its CRM database schema during activation. If the database schema cannot be completed, CRM screens and public form submission are blocked until the upgrade succeeds.

## Requirements

- WordPress 6.2+
- PHP 7.4+
- A database engine capable of using InnoDB for the CRM tables

BfCamel CRM does not support network-wide multisite activation. On a multisite installation, activate it separately for each site.

## Updating an existing installation

BfCamel CRM is designed to update in place. Its plugin directory, main file, text domain, namespace, option prefixes and database table prefixes remain stable across current releases.

For manual updates, install the official `bfcamel-crm.zip` package over the existing plugin. **Do not install GitHub's generic “Source code (zip)” archive**. A generic source archive can create a differently named directory and WordPress may treat it as a second plugin.

## Duplicate-copy protection

If WordPress loads a second active copy of BfCamel CRM, the later copy stops before defining the plugin constants and an administrator notice explains that a duplicate directory must be removed. This protects the site from duplicate constant declarations and duplicate hook registration.

If you see that notice:

1. Back up the site and database.
2. Open the plugins directory and identify the extra BfCamel CRM folder.
3. Keep the canonical `bfcamel-crm/` installation.
4. Remove the duplicate directory.
5. Reinstall or update using the official `bfcamel-crm.zip` package if necessary.

## Database upgrades

The current database schema version is **6**. BfCamel CRM checks the stored schema version in the WordPress admin area and performs required migrations. A short-lived lock prevents two schema upgrades from running at the same time.

The plugin verifies the resulting schema before advancing the stored database version. Public forms display nothing to normal visitors while the schema is incomplete; administrators receive a diagnostic message.

## Uninstall behavior

Uninstalling the plugin does **not** delete CRM data by default.

To remove CRM tables and plugin settings during uninstall, enable **Settings → Privacy → Delete all BfCamel CRM data when the plugin is uninstalled** before uninstalling. When enabled, the uninstall routine removes the CRM tables and plugin options. BfCamel CRM capabilities are removed from WordPress roles during uninstall in either case.

Because deletion is destructive, create a database backup before enabling this option.

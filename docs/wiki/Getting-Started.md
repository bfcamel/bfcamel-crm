# Getting Started

This guide covers the shortest path from activation to a working public form and a manageable CRM workflow.

## 1. Review global settings

Open **BfCamel CRM → Settings**. The settings page contains three sections: legal documents, privacy and access.

Legal-document URLs are optional. You can configure a document title, URL, link text, version and effective date for the Personal Data Processing Consent, Privacy Policy, and Marketing / Information Messages Consent. When configured, the current document information is copied into consent evidence so later edits do not rewrite the historical event.

Under privacy, decide whether BfCamel CRM should store visitor IP addresses, source page URLs and browser information. IP storage is disabled by default. Source URL and User-Agent storage are enabled by default on a fresh installation.

Under access, grant CRM capabilities only to roles that need them. Administrators receive access by default; other roles start without CRM permissions.

## 2. Create a form

Open **BfCamel CRM → Forms → Add form**. Give the form a name and optional slug. If the slug is omitted, BfCamel CRM derives one from the form name.

A new form starts with a practical default schema containing name, phone, email, message, personal-data consent and marketing consent fields.

Build the form by adding, removing and reordering fields. For contact fields, choose a CRM mapping so submitted values update the associated CRM contact. See [Forms and Shortcodes](Forms-and-Shortcodes.md).

## 3. Configure the form workflow

Each form has its own default submission status and priority. Choose them in the form editor. Global status and priority definitions are managed in **BfCamel CRM → Workflow**.

You can also create priority automation rules so particular field values change the priority of new submissions.

## 4. Choose the form appearance

The form editor supports three style modes:

- **Theme / unstyled** — minimal plugin styling for theme or page-builder control.
- **Default** — BfCamel CRM's standard frontend design.
- **Custom** — uses the plugin's CSS-variable based form system with configured colors and radius.

You can choose one or two columns, set field widths, assign a custom wrapper class, change button text, and customize success and error messages.

## 5. Publish the form on a page

After saving the form, copy its shortcode. The preferred syntax is:

```text
[bfcamel_form id="1"]
```

You can also address a form by slug:

```text
[bfcamel_form slug="contact-form"]
```

Insert the shortcode into a WordPress page, post or compatible page-builder shortcode widget.

## 6. Test the full path

Submit a test entry from the public page. Then check:

1. **Submissions** — the new submission should appear with its form, status, priority and received time.
2. **Contacts** — if the form maps identity fields, a contact should be created or matched.
3. **Consent history** — configured consent fields should create consent events.
4. **Activity history** — BfCamel CRM records important CRM changes and submission creation.

## 7. Prepare staff access

Return to **Settings → Access** and grant the minimum permissions required for each WordPress role. For example, a staff role can be allowed to view and edit submissions without receiving settings access.

For details, see [Roles and Permissions](Roles-and-Permissions.md).

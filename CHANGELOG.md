# Changelog

## 1.4.0

### What's new

- **Proof of consent**, off by default. Article 7(1) GDPR puts the burden of proof on the controller,
  and a value in the visitor's own browser is not proof. Switch on `record.enabled`, run
  `php artisan migrate`, and every decision is recorded server-side — the id, timestamp, version,
  granted handles and how it was decided. **No IP address, no user agent**: both are personal data in
  their own right and neither is needed.
- **`php please consent:lookup`** answers the question the log exists for, with `--latest` and
  `--csv`. **`php please consent:prune`** enforces the retention.

## 1.3.1

### What's fixed

- **`{{ consent:granted }}` never worked.** The cookie is written by JavaScript and is therefore not
  encrypted; Laravel's `EncryptCookies` middleware discarded it, so the server saw no cookie at all.
  The failure looked exactly like "nobody has consented yet", which is why it survived a green
  suite, a playground and a production install. The cookie is now registered with
  `EncryptCookies::except()`, and the exemption follows a renamed cookie.

## 1.3.0

Both of these came out of the first real installation, on a site that loads no third party at all.

### What's new

- **No services ship.** A service listed by default appears in the banner of every fresh install,
  describing data processing that site may not do. Add the ones your site actually loads; the config
  file carries the three most common as commented examples.

### What's fixed

- **A list emptied in the control panel stayed empty no longer than a page load.** Deleting every
  service fell back to the shipped list, so a client who removed them got them back — along with a
  banner asking about services their site does not load. An emptied list is an answer, not a missing
  value.

## 1.2.1

### What's fixed

- `php please consent:install` died with "mkdir(): Permission denied" on a containerised Statamic,
  where the application directory belongs to root while the process runs as www-data. Publishing the
  blueprint is a convenience; the global set is the job. It now warns, says where to copy the file
  from, and carries on.
- The install command had no test at all. It has three now, including the unwritable case.

## 1.2.0

### What's new

- **Nothing to ask, nothing rendered.** With no optional service configured, `{{ consent:head }}`,
  `{{ consent:banner }}` and `{{ consent:settings_link }}` render nothing at all. Strictly necessary
  cookies need no consent, so a site that loads no third party has nothing to put in a banner — and
  that is the state every installation is in on its first day. A gate still blocks.

## 1.1.0

### What's new

- **Google Consent Mode v2**, off by default. Map each of Google's four signals to the services
  behind it; the `consent default` call is written inline and first, the update follows the
  visitor's decision.
- **Browser tests** for the three behaviours the PHP suite cannot reach: the localStorage mirror,
  Global Privacy Control and parked scripts. `npm test`.

### What's fixed

- Closing the settings panel with **Escape** without deciding left the visitor with no banner and no
  decision. Every way out now behaves the same.
- The CI matrix tested a Laravel 11 leg that Statamic 6 can never satisfy, and `orchestra/testbench`
  was pinned to Laravel 12 only, so the Laravel 13 leg could not resolve either.

## 1.0.0

### What's new

- **The banner is a card in the bottom-left corner and the settings panel sits in the opposite
  corner**, in the shape adriangoldner.com established — not a bar across the page. No font is
  loaded: the banner inherits the site's own faces. Every colour, radius and shadow is a custom
  property.

- **Cookie banner and settings dialog**, with the wording editable in the control panel under
  **Globals → Consent**. Every route out of the banner — accept all, essential only, settings — is
  one click and looks like the others.
- **`{{ consent:gate }}`**, a two-click gate that parks the embed in a `<template>`, so no request
  reaches the third party before the visitor allows it.
- **`{{ consent:granted }}`, `{{ consent:settings_link }}`, `{{ consent:head }}`,
  `{{ consent:banner }}`** — see the README for signatures.
- **Parked scripts** via `<script type="text/plain" data-consent-service="…">`.
- **Global Privacy Control** is honoured: a visitor sending the signal is recorded as having
  rejected, and is not asked again.
- **English and German** translations; a field left empty in the control panel falls back to the
  shipped text in the visitor's language.
- **`php please consent:install`** publishes the assets and blueprint and seeds the global set.
- **Google Consent Mode v2**, off by default. Map each of Google's four signals to the services
  behind it; the `consent default` call is written inline and first, the update follows the
  visitor's decision.

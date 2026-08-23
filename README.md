# Statamic Consent

Cookie banner and two-click embed gate for Statamic 6, editable in the control panel.

Built for sites where the same person maintains the site and answers for it: the wording lives in
a global set the client can edit, the handles live in the config file the developer controls, and
a blocked embed is genuinely absent from the page rather than hidden with CSS.

## Requirements

- PHP 8.2 or newer
- Statamic 6

## Install

```bash
composer require goldnead/statamic-consent
php please statamic:consent:install
```

The install command publishes the assets to `public/vendor/statamic-consent`, publishes the
blueprint, and creates the `consent` global set seeded from the config file. Re-run it after every
update — the assets are overwritten on purpose, because a half-updated pair of `consent.js` and
`consent.css` behaves like the previous release.

Then, in your layout:

```antlers
<head>
    {{ consent:head }}
</head>
<body>
    ...
    <footer>{{ consent:settings_link }}</footer>
    {{ consent:banner }}
</body>
```

`{{ consent:settings_link }}` belongs on every page. A decision that cannot be revisited is not a
decision that was freely given.

## Blocking an embed

```antlers
{{ consent:gate service="youtube" title="Konzertmitschnitt" cover="/img/cover.jpg" }}
    <iframe src="https://www.youtube.com/embed/xyz" allowfullscreen></iframe>
{{ /consent:gate }}
```

The iframe is parked in a `<template>`. Browsers parse a template but issue no requests for what is
inside it, so nothing reaches YouTube until the visitor presses the button. Rendering the iframe and
hiding it with CSS would look identical and be exactly the violation this tag exists to prevent.

A gate naming a service that is not configured stays blocked and says so, rather than falling
through — a typo must not publish an unconsented embed.

## Blocking a script

```html
<script type="text/plain" data-consent-service="analytics_pixel" src="https://…"></script>
```

Parked scripts are re-created as real script elements once the service is allowed. Setting `.type`
on the existing node would do nothing; the browser decided how to treat it at parse time.

## Tags

| Tag | What it does |
|---|---|
| `{{ consent:head }}` | Stylesheet, inline configuration, script. Belongs in `<head>`. |
| `{{ consent:banner }}` | The banner and the settings dialog. Belongs before `</body>`. |
| `{{ consent:gate service="…" }}` | Two-click gate around an embed. Takes `title` and `cover`. |
| `{{ consent:granted service="…" }}` | Renders its contents only with consent, server-side. |
| `{{ consent:settings_link }}` | A button that reopens the dialog. Takes `label` and `class`. |

`{{ consent:granted }}` reads the cookie on the server, so it is wrong on a page served from a
full-page cache. Use the gate for anything that loads a third party; use `granted` for prose.

## Configuration

`config/statamic-consent.php` owns the handles, the cookie and the behaviour. The `consent` global
set owns the wording, and overrides the config key by key — a field left empty falls back to the
shipped text in the visitor's language rather than rendering blank.

Two settings are worth understanding:

- **`version`** — raising it invalidates every stored decision and asks again. Do that when you add
  a service that is not essential: the old yes never covered it.
- **`reject_on_dismiss`** — keep it true. Under the GDPR, no decision is a rejection.

`respect_gpc` honours the Global Privacy Control browser signal, which German courts have read as a
valid objection. A visitor sending it is recorded as having rejected, without being asked again.

## What it stores

A first-party cookie (`statamic_consent`, six months) and a localStorage mirror, holding the granted
handles, a timestamp, how the decision was made, and a random id. Nothing is sent anywhere. There is
no server-side consent log — if you need one for evidence, that is a separate decision, not
something this addon does behind your back.

## Styling

The default stylesheet is driven by custom properties. To restyle, set them:

```css
:root {
    --csnt-accent: #f0421e;
    --csnt-accent-fg: #fff;
    --csnt-radius: 0;
}
```

To replace it entirely, set `assets.styles` to `false` and write your own CSS against the documented
class names (`csnt-banner`, `csnt-modal`, `csnt-gate`, …). The class names are part of the public
API; the rules are not.

## The JavaScript API

```js
StatamicConsent.granted('youtube')   // boolean
StatamicConsent.open()               // open the dialog
StatamicConsent.reset()              // forget the decision, show the banner
document.addEventListener('consent:changed', e => e.detail.granted)
```

## What this addon does not do

It is not a legal opinion, and it does not discover what your site loads. Every third party has to be
entered as a service, and an embed only gets blocked where a gate is put around it. It also does not
scan your theme to check that you did.

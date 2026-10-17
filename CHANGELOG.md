# Changelog

All notable changes to `1994/cookie-connect-core` are documented here. It follows semantic versioning: a minor or patch release doesn't break the public API.

## Unreleased

### Added
- Providers as data (`resources/providers/*.yaml`, checked by `Providers\ProviderSchema`): Cookiebot, Usercentrics, OneTrust, CookieYes, iubenda, Termly, CookieFirst, Cookie-Script and Custom, each checked against the platform's docs on 2026-10-17.
- `Output\Renderer`: the head block (Consent Mode defaults, the browser script, the platform's tags, Google Tag Manager and Analytics in the order the platform asks for), the body blocks, `gate()`, `declaration()`, `settingsLink()`, `placeholder()` and `cspHashes()`.
- `Output\Gate`: the platform's own blocking markup where it has one, Cookie Connect's own where it doesn't.
- `Output\Injector`: adds the blocks to a finished page, so no template has to change.
- `Settings\Settings`, `GoogleTags`, `Script` and `Validator` (IDs taken out of pasted snippets).
- The browser script (`resources/runtime/cookie-connect.js`): `window.CookieConnect`, the `cookieconnect:change` event, Consent Mode updates, and running blocked content.

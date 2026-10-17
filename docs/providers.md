# Providers

Each consent platform is one YAML file in `resources/providers`, named after its handle. `Providers\ProviderSchema` checks every file when it's loaded, and the tests check each one's output against a snapshot.

## The platforms

| Platform | Blocking in markup | Consent Mode defaults | Declaration |
|---|---|---|---|
| Cookiebot | Its own (`data-cookieconsent`) | Cookie Connect | `cd.js` script |
| Usercentrics | Cookie Connect's (it blocks by service name, not category) | Cookie Connect | `uc-embed` |
| OneTrust | Its own (`optanon-category-…`); images: Cookie Connect's | Cookie Connect, before Google tags and OneTrust | `ot-sdk-cookie-policy` |
| CookieYes | Cookie Connect's (CookieYes's `data-cookieyes` leaves scripts runnable if CookieYes fails to load) | Cookie Connect | `cky-audit-table-element` |
| iubenda | Its own (`_iub_cs_activate`, `data-iub-purposes`) | iubenda | Cookie policy embed |
| Termly | Its own (`data-categories`), one category per element | Termly (or Cookie Connect if turned off) | `termly-embed` |
| CookieFirst | Its own (`data-cookiefirst-category`), one category per element; images: Cookie Connect's | Cookie Connect (or CookieFirst if set there) | `cookiefirst-policy-page` |
| Cookie-Script | Its own (`data-cookiescript`, `data-cookiecategory`) | Cookie Connect | Cookie report script |
| Custom | Cookie Connect's | Cookie Connect (or the platform) | None |

Module scripts, and images with `srcset`, always use Cookie Connect's blocking: platforms that flip `type` back would lose `module`, and none of them swaps `srcset`.

## The file format

```yaml
handle: cookiebot              # lower case, digits, hyphens; the file's name
name: Cookiebot
website: https://www.cookiebot.com
docs: [https://…]              # where each fact came from
verified: 2026-10-17           # when the facts were last checked against those docs
google_partner: true           # on Google's CMP Partner list
tcf: true                      # supports IAB TCF
unverified: [...]              # facts the docs don't state, in plain words

fields:                        # the settings an editor fills in
  - handle: cbid
    label: Domain Group ID
    type: text                 # text, select, toggle or code
    required: true
    instructions: …
    example: 1a2b…             # must match pattern
    pattern: '^…$'             # checked after normalising
    extract: 'data-cbid="(…)"' # takes the value out of a pasted snippet (first group)
    extract_from: otherField   # also look in another field's input when this one is empty
    lowercase: true
    options: { com: …, eu: … } # for selects
    default: com               # may use the context: '{host}'

head:                          # printed in order, after Cookie Connect's script
  - tag: script
    when: autoBlocking         # see Conditions
    attrs:
      src: 'https://consent.cookiebot.{cdn}/uc.js'   # {field} and {host}, {lang}, {mode}
      data-blockingmode: { value: auto, when: autoBlocking }   # left out when the condition fails
      async: { value: true, when: '!autoBlocking' }            # true: a bare attribute
      src2:                                                    # a list: the first that applies
        - { when: staging, value: … }
        - { value: … }
    content: '…'               # inline code; {x} is JSON-encoded, {x|raw} is inserted as it is
  - raw: '{headHtml}'          # HTML printed as it is (Custom only)

exempt:                        # added to Cookie Connect's own scripts so an auto-blocker leaves them alone
  data-cookieconsent: { value: ignore, when: autoBlocking }

categories:                    # our category => the platform's id (editors can override per site)
  necessary: null              # null: no markup needed
  analytics: statistics

blocking:
  join: ','                    # how several categories are joined in one attribute
  multiple: false              # the platform takes one category per element; more use Cookie Connect's
  script_inline: { strategy: native, attrs: { type: text/plain, data-cookieconsent: '{categories}' } }
  script_src:    { strategy: native, rename: { src: data-src }, attrs: { … } }
  iframe:        { strategy: native, rename: { src: data-cookieblock-src }, attrs: { … } }
  img:           { strategy: managed }   # missing kinds are managed too

consent_mode:
  defaults: ours               # ours or cmp, or a list of { when, value }
  updates: cmp                 # cmp: the platform sends updates; ours: Cookie Connect's script does
  wait_for_update: 500

google_tags: after_cmp         # or before_cmp, or a list of { when, value } (mode is basic or advanced)

preferences:
  class: cky-banner-element    # added to "Cookie settings" links; the platform opens its preferences from it

declaration: [ …tags, as in head… ]

api:                           # for people reading the file; the browser script has the code
  open: Cookiebot.renew()
  read: …
  events: …

notes: [ … ]                   # shown in the Control Panel under the platform's settings
```

`class` attributes are added to, never replaced. Attributes whose value comes out empty are left out, so a presence-only attribute like Usercentrics' `data-draft` is only printed when it's on.

### Conditions

`when` takes `name` (truthy), `!name`, `name=value`, `name!=value`, joined with `&`. Names are field handles or `host`, `lang` and `mode` (`advanced` or `basic`).

### Strategies

- **native**: the platform's own markup. The platform runs the element; Cookie Connect marks it `data-cookie-connect-native` so it's never handled twice.
- **managed**: `type="text/plain"` (scripts), `src` → `data-cookie-connect-src`, and `data-cookie-connect="analytics marketing"`. Cookie Connect's script runs it once every category is allowed.

## Adding a platform

1. Read the platform's own docs for: the install snippet and where its ID is, auto-blocking, how to mark a script and an iframe for a category, the JavaScript to open the preferences and read consent, the events it fires, whether it sets Consent Mode defaults or sends updates, and the declaration embed.
2. Write `resources/providers/{handle}.yaml`, with every docs page you used in `docs` and anything you couldn't confirm in `unverified`.
3. Add an adapter to `resources/runtime/cookie-connect.js` (`start(emit)` and `open()`), with tests in `tests/js/runtime.test.js`. Run `npm run build`.
4. Add working values and the platform's own snippet to `tests/Fixtures/Sites.php`, run the tests, and read the new snapshots before committing them.
5. Add it to `Providers::ORDER` if it belongs in the main list, and to the products' docs.

## Checking a platform again

`verified` must be within the last year, or the tests fail. To recheck: go through the pages in `docs`, compare them with the file, update anything that changed (and the snapshots), and set `verified` to the day you checked.

A site or another package can add its own platform without changing core:

```php
Providers::default()->register(Providers::load('/path/to/acme.yaml'));
```

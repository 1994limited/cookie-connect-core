# The browser API

Every page gets `window.CookieConnect`, whichever consent platform the site uses (or none). It's printed inline in the head, before the platform's own script.

```js
// The visitor's choice, once the platform has said
CookieConnect.ready.then((consent) => {
  // { necessary: true, preferences: false, analytics: true, marketing: false, decided: true, provider: 'cookiebot' }
});

CookieConnect.get();                     // the same, now
CookieConnect.has('analytics');          // true or false
CookieConnect.has('analytics marketing') // both needed

// Called at once (when known) and on every change; returns a function that stops it
const stop = CookieConnect.on('change', (consent) => { … });

CookieConnect.openPreferences();         // opens the platform's cookie preferences
CookieConnect.refresh();                 // looks again for blocked content, e.g. after loading HTML over AJAX
```

`decided` is true once the visitor has made a choice, false before, and stays as it was when the platform can't tell (Usercentrics, iubenda and Termly on page load).

The same reading is sent as an event and to the dataLayer:

```js
document.addEventListener('cookieconnect:change', (event) => event.detail);
document.addEventListener('cookieconnect:ready', (event) => event.detail);   // once
// dataLayer: { event: 'cookie_connect_update', cookie_connect: { … } }
```

In Google Tag Manager, a Custom Event trigger on `cookie_connect_update` fires after the platform's Consent Mode update.

## Cookie settings links

Any element with `data-cookie-connect-open`, and any link whose address ends in `#cookie-settings`, opens the preferences. A page opened with `#cookie-settings` in its address opens them once the platform is ready, so a footer link on another site or in an email can point at `https://example.com/#cookie-settings`.

## Blocked content

Content blocked with Cookie Connect's own markup (`type="text/plain" data-cookie-connect="analytics"`) runs once every category it lists is allowed, in page order: a script with a `src` that isn't `async` is loaded before the scripts after it run. Content added later is found by itself. Scripts get the nonce of Cookie Connect's own script, so they run under a nonce-based Content Security Policy.

Placeholders (`data-cookie-connect-placeholder="marketing"`) are hidden once their categories are allowed.

## Custom platforms

```js
// In "Tell Cookie Connect about consent"
window.addEventListener('my-cmp-consent', function (e) {
  CookieConnect.set({ preferences: e.detail.functional, analytics: e.detail.stats, marketing: e.detail.ads });
});

// In "Open the preferences": the code is wrapped in CookieConnect.onOpen(function () { … })
MyCmp.showPreferences();
```

## Debugging

With debug on (in the settings), the script logs each reading and, for Usercentrics, any mapped category the account doesn't have.

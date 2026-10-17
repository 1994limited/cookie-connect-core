import { test, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { page, fire, tick, consentCommands, closeAll, plain } from './page.js';

afterEach(closeAll);

const gated = `
  <script type="text/plain" data-cookie-connect="analytics">window.ranAnalytics = (window.ranAnalytics || 0) + 1;</script>
  <script type="text/plain" data-cookie-connect="marketing">window.ranMarketing = true;</script>
  <script type="text/plain" data-cookie-connect="analytics marketing">window.ranBoth = true;</script>
  <iframe data-cookie-connect-src="https://www.youtube.com/embed/x" data-cookie-connect="marketing"></iframe>
  <div class="cookie-connect-placeholder" data-cookie-connect-placeholder="marketing"><p>Needs marketing cookies</p></div>
`;

test('with no platform, nothing optional is allowed and blocked content stays blocked', async () => {
  const window = page({ body: gated });
  const state = await window.CookieConnect.ready;

  assert.deepEqual(plain(state), { necessary: true, preferences: false, analytics: false, marketing: false, decided: false, provider: null });
  assert.equal(window.CookieConnect.has('analytics'), false);
  assert.equal(window.CookieConnect.has('necessary'), true);
  await tick();
  assert.equal(window.ranAnalytics, undefined);
  assert.equal(window.document.querySelector('iframe').getAttribute('src'), null);
  assert.equal(window.document.querySelector('.cookie-connect-placeholder').hidden, false);
});

test('in run mode (off, but set to run everything) blocked content runs', async () => {
  const window = page({ body: gated, config: { state: 'run' } });
  await window.CookieConnect.ready;
  await tick();

  assert.equal(window.ranAnalytics, 1);
  assert.equal(window.ranMarketing, true);
  assert.equal(window.document.querySelector('iframe').getAttribute('src'), 'https://www.youtube.com/embed/x');
  assert.equal(window.document.querySelector('.cookie-connect-placeholder').hidden, true);
});

test('Cookiebot: reads its consent and runs only what is allowed, once', async () => {
  const window = page({
    body: gated,
    config: { provider: 'cookiebot', state: 'live', map: { necessary: null, preferences: 'preferences', analytics: 'statistics', marketing: 'marketing' } },
  });
  window.Cookiebot = { consent: { necessary: true, preferences: false, statistics: true, marketing: false }, hasResponse: true };
  fire(window, 'CookiebotOnConsentReady');
  const state = await window.CookieConnect.ready;
  await tick();

  assert.equal(state.analytics, true);
  assert.equal(state.marketing, false);
  assert.equal(state.decided, true);
  assert.equal(window.ranAnalytics, 1);
  assert.equal(window.ranMarketing, undefined);
  assert.equal(window.ranBoth, undefined);

  window.Cookiebot.consent.marketing = true;
  fire(window, 'CookiebotOnAccept');
  await tick();

  assert.equal(window.ranAnalytics, 1, 'already-run scripts are not run again');
  assert.equal(window.ranMarketing, true);
  assert.equal(window.ranBoth, true);
  assert.equal(window.document.querySelector('iframe').getAttribute('src'), 'https://www.youtube.com/embed/x');
  assert.equal(window.document.querySelector('.cookie-connect-placeholder').hidden, true);
});

test('activated scripts keep their attributes and get the nonce', async () => {
  const window = page({
    body: '<script type="text/plain" data-cookie-connect="analytics" data-cookie-connect-type="module" id="mod" data-x="1">window.x = 1;</script>',
    config: { state: 'run' },
  });
  await window.CookieConnect.ready;
  const script = window.document.getElementById('mod');

  assert.equal(script.getAttribute('type'), 'module');
  assert.equal(script.getAttribute('data-x'), '1');
  assert.equal(script.getAttribute('nonce'), 'abc123');
  assert.equal(script.hasAttribute('data-cookie-connect'), false);
});

test('a blocked script with a src holds later ones until it has loaded', async () => {
  const window = page({
    body: `
      <script type="text/plain" data-cookie-connect="analytics" data-cookie-connect-src="https://cdn.example.com/lib.js" id="lib"></script>
      <script type="text/plain" data-cookie-connect="analytics">window.afterLib = true;</script>`,
    config: { state: 'run' },
  });
  await window.CookieConnect.ready;
  const lib = window.document.getElementById('lib');

  assert.equal(lib.getAttribute('src'), 'https://cdn.example.com/lib.js');
  assert.equal(window.afterLib, undefined);

  lib.dispatchEvent(new window.Event('load'));
  assert.equal(window.afterLib, true);
});

test('content added later (AJAX) is released too', async () => {
  const window = page({ config: { state: 'run' } });
  await window.CookieConnect.ready;

  const added = window.document.createElement('div');
  added.innerHTML = '<iframe data-cookie-connect="marketing" data-cookie-connect-src="https://player.vimeo.com/video/1"></iframe>';
  window.document.body.appendChild(added);
  await tick(5);

  assert.equal(added.querySelector('iframe').getAttribute('src'), 'https://player.vimeo.com/video/1');
});

test('OneTrust: reads active groups from OneTrustGroupsUpdated', async () => {
  const window = page({ config: { provider: 'onetrust', state: 'live', map: { necessary: 'C0001', preferences: 'C0003', analytics: 'C0002', marketing: 'C0004' } } });
  window.OneTrust = { IsAlertBoxClosed: () => false, ToggleInfoDisplay() { window.opened = true; } };
  fire(window, 'OneTrustGroupsUpdated', ['C0001', 'C0002']);
  const state = await window.CookieConnect.ready;

  assert.equal(state.analytics, true);
  assert.equal(state.preferences, false);
  assert.equal(state.decided, false);

  window.CookieConnect.openPreferences();
  assert.equal(window.opened, true);
});

test('CookieYes: listens on document and reads getCkyConsent()', async () => {
  const window = page({ config: { provider: 'cookieyes', state: 'live', map: { necessary: 'necessary', preferences: 'functional', analytics: 'analytics', marketing: 'advertisement' } } });
  window.getCkyConsent = () => ({ categories: { necessary: true, functional: true, analytics: false, advertisement: true }, isUserActionCompleted: true });
  fire(window.document, 'cookieyes_banner_load', {});
  const state = await window.CookieConnect.ready;

  assert.deepEqual([state.preferences, state.analytics, state.marketing, state.decided], [true, false, true, true]);
});

test('Usercentrics: a category counts only when all of it is accepted', async () => {
  const window = page({ config: { provider: 'usercentrics', state: 'live', map: { necessary: 'essential', preferences: 'functional', analytics: 'marketing', marketing: 'marketing' } } });
  fire(window, 'UC_CONSENT', { consent: { fromUserAction: false }, categories: { essential: { state: 'ALL_ACCEPTED' }, functional: { state: 'ALL_ACCEPTED' }, marketing: { state: 'SOME_ACCEPTED' } } });
  const state = await window.CookieConnect.ready;

  assert.equal(state.preferences, true);
  assert.equal(state.marketing, false);
});

test('iubenda: chains onto existing callbacks and reads purposes', async () => {
  const window = page({
    config: { provider: 'iubenda', state: 'live', map: { necessary: '1', preferences: '2', analytics: '4', marketing: '5' } },
    before(window) {
      window._iub = [];
      window._iub.csConfiguration = { callback: { onReady() { window.theirs = true; } } };
    },
  });
  window._iub.cs = { api: { getPreferences: () => ({ purposes: { 1: true, 2: false, 4: true, 5: false } }), openPreferences() { window.opened = true; } } };
  window._iub.csConfiguration.callback.onReady(true);
  const state = await window.CookieConnect.ready;

  assert.equal(window.theirs, true);
  assert.equal(state.analytics, true);
  assert.equal(state.preferences, false);
});

test('iubenda without per-purpose consent: one answer for everything', async () => {
  const window = page({ config: { provider: 'iubenda', state: 'live', map: { necessary: '1', preferences: '2', analytics: '4', marketing: '5' } } });
  window._iub.cs = { api: { getPreferences: () => ({ consent: true }) } };
  window._iub.csConfiguration.callback.onPreferenceExpressed({});
  const state = await window.CookieConnect.ready;

  assert.equal(state.marketing, true);
});

test('Termly: waits for Termly, then reads getConsentState()', async () => {
  const window = page({ config: { provider: 'termly', state: 'live', map: { necessary: 'essential', preferences: 'performance', analytics: 'analytics', marketing: 'advertising' } } });
  const handlers = {};
  window.Termly = {
    on: (name, fn) => { handlers[name] = fn; },
    getConsentState: () => ({ essential: true, performance: false, analytics: true, advertising: false }),
  };
  const state = await window.CookieConnect.ready;
  assert.equal(state.analytics, true);

  window.Termly.getConsentState = () => ({ essential: true, performance: true, analytics: true, advertising: true });
  handlers.consent({});
  assert.equal(window.CookieConnect.get().marketing, true);
  assert.equal(window.CookieConnect.get().decided, true);
});

test('CookieFirst: no choice yet means nothing allowed; cf_consent updates', async () => {
  const window = page({ config: { provider: 'cookiefirst', state: 'live', map: { necessary: 'necessary', preferences: 'functional', analytics: 'performance', marketing: 'advertising' } } });
  fire(window, 'cf_consent_loaded', null);
  const state = await window.CookieConnect.ready;
  assert.equal(state.decided, false);

  fire(window, 'cf_consent', { necessary: true, performance: true, functional: false, advertising: false });
  assert.equal(window.CookieConnect.has('analytics'), true);
  assert.equal(window.CookieConnect.has('analytics marketing'), false);
});

test('Cookie-Script: reads currentState() on its events', async () => {
  const window = page({ config: { provider: 'cookie-script', state: 'live', map: { necessary: 'strict', preferences: 'functionality', analytics: 'performance', marketing: 'targeting' } } });
  window.CookieScript = { instance: { currentState: () => ({ action: 'accept', categories: ['strict', 'targeting'] }), show() { window.opened = true; } } };
  fire(window, 'CookieScriptLoaded');
  const state = await window.CookieConnect.ready;

  assert.equal(state.marketing, true);
  assert.equal(state.analytics, false);
});

test('Custom: CookieConnect.set() and onOpen()', async () => {
  const window = page({ config: { provider: 'custom', state: 'live', map: {} } });
  window.CookieConnect.onOpen(() => { window.opened = true; });
  window.CookieConnect.set({ analytics: true });
  const state = await window.CookieConnect.ready;

  assert.equal(state.analytics, true);
  assert.equal(state.decided, true);
  window.document.body.innerHTML = '<a href="/privacy#cookie-settings">Cookie settings</a>';
  window.document.querySelector('a').click();
  assert.equal(window.opened, true);
});

test('Cookie settings links open the platform, except ones the platform handles itself', async () => {
  const window = page({
    body: '<button data-cookie-connect-open id="ours">Cookie settings</button><button data-cookie-connect-open class="cky-banner-element" id="native">Cookie settings</button>',
    config: { provider: 'cookieyes', state: 'live', nativeClass: 'cky-banner-element', map: { analytics: 'analytics' } },
  });
  let opened = 0;
  window.revisitCkyConsent = () => { opened++; };

  window.document.getElementById('ours').click();
  window.document.getElementById('native').click();

  assert.equal(opened, 1);
});

test('sends Consent Mode updates when told to, mapped by category', async () => {
  const window = page({ config: { provider: 'custom', state: 'live', consentMode: { update: true, types: (await import('./page.js')).TYPES } } });
  window.CookieConnect.set({ analytics: true, marketing: false });
  await window.CookieConnect.ready;

  const [command] = consentCommands(window);
  assert.deepEqual(plain(command[2]), {
    security_storage: 'granted',
    functionality_storage: 'denied',
    personalization_storage: 'denied',
    analytics_storage: 'granted',
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
  });
});

test('does not send Consent Mode updates when the platform does', async () => {
  const window = page({ config: { provider: 'custom', state: 'live' } });
  window.CookieConnect.set({ analytics: true });
  await window.CookieConnect.ready;

  assert.equal(consentCommands(window).length, 0);
});

test('events, listeners and the dataLayer hear about every change', async () => {
  const window = page({ config: { provider: 'custom', state: 'live' } });
  const seen = [];
  const events = [];
  window.document.addEventListener('cookieconnect:change', (e) => events.push(e.detail.analytics));
  const stop = window.CookieConnect.on('change', (state) => seen.push(state.analytics));

  window.CookieConnect.set({ analytics: false });
  window.CookieConnect.set({ analytics: true });
  window.CookieConnect.set({ analytics: true });
  stop();
  window.CookieConnect.set({ analytics: false });

  assert.deepEqual(seen, [false, true]);
  assert.deepEqual(events, [false, true, false]);
  assert.equal(window.dataLayer.filter((e) => e.event === 'cookie_connect_update').length, 3);

  const late = [];
  window.CookieConnect.on('change', (state) => late.push(state.analytics));
  assert.deepEqual(late, [false], 'a late listener hears the current state at once');
});

test('ready() resolves after five seconds if the platform never answers', { timeout: 8000 }, async () => {
  const window = page({ config: { provider: 'cookiebot', state: 'live', map: { analytics: 'statistics' } } });
  const state = await window.CookieConnect.ready;

  assert.equal(state.analytics, false);
});

test('is only set up once even if printed twice', async () => {
  const window = page({ config: { state: 'run' } });
  const first = window.CookieConnect;
  const again = window.document.createElement('script');
  again.textContent = (await import('node:fs')).readFileSync(new URL('../../resources/runtime/cookie-connect.js', import.meta.url), 'utf8');
  window.document.head.appendChild(again);

  assert.equal(window.CookieConnect, first);
});

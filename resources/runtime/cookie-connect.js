/*!
 * Cookie Connect: one consent API for every cookie consent platform.
 * (c) 1994 Limited. Commercial software, see LICENSE.md.
 *
 * Printed inline in <head> before the consent platform's own script, with
 * window.CookieConnectConfig set just before it. It:
 *  - reads consent from the platform (one adapter each, below) and turns it
 *    into { necessary, preferences, analytics, marketing, decided },
 *  - runs content blocked with Cookie Connect's own markup
 *    (type="text/plain" data-cookie-connect="analytics") once it's allowed,
 *  - sends Google Consent Mode updates where the platform doesn't,
 *  - shows and hides placeholders, opens the preferences from any
 *    "Cookie settings" link, and gives site code window.CookieConnect.
 */
(function (window, document) {
  'use strict';

  if (window.CookieConnect && window.CookieConnect.version) {
    return;
  }

  var config = window.CookieConnectConfig || {};
  var OPTIONAL = ['preferences', 'analytics', 'marketing'];
  var map = config.map || {};
  var me = document.currentScript;
  var nonce = me ? me.nonce || me.getAttribute('nonce') : null;
  var listeners = [];
  var openers = [];
  var known = false;
  var resolveReady;
  var ready = new Promise(function (resolve) { resolveReady = resolve; });
  var everything = config.state === 'run';
  var state = {
    necessary: true,
    preferences: everything,
    analytics: everything,
    marketing: everything,
    decided: everything,
    provider: config.provider || null
  };

  function log() {
    if (config.debug && window.console) {
      window.console.log.apply(window.console, ['[Cookie Connect]'].concat([].slice.call(arguments)));
    }
  }

  function on(target, name, handler) {
    target.addEventListener(name, handler, false);
  }

  /** Calls then() once test() is true, checking every 50 ms for 15 s. */
  function when(test, then) {
    var tries = 0;
    (function check() {
      var ok = false;
      try { ok = !!test(); } catch (e) { ok = false; }
      if (ok) { then(); return; }
      if (++tries < 300) { setTimeout(check, 50); }
    })();
  }

  /** The platform's ids for one of our categories (a mapping may list several, all needed). */
  function ids(category) {
    var value = map[category];
    return value === null || value === undefined || value === '' ? [] : String(value).split(/\s*,\s*/);
  }

  /** Our three optional categories, each granted when test() is true for all its ids. */
  function byId(test) {
    var result = {};
    OPTIONAL.forEach(function (category) {
      var list = ids(category);
      result[category] = list.length > 0 && list.every(function (id) { return !!test(id); });
    });
    return result;
  }

  function snapshot() {
    return {
      necessary: true,
      preferences: state.preferences,
      analytics: state.analytics,
      marketing: state.marketing,
      decided: state.decided,
      provider: state.provider
    };
  }

  function split(categories) {
    return (Array.isArray(categories) ? categories : String(categories || '').split(/[\s,]+/)).filter(Boolean);
  }

  function allows(categories) {
    return split(categories).every(function (category) {
      return category === 'necessary' || state[category] === true;
    });
  }

  /** Takes a new reading from the platform. decided: true, false, or null for "can't tell". */
  function update(next, decided) {
    var changed = !known;
    var previous = snapshot();

    OPTIONAL.forEach(function (category) {
      var value = !!(next && next[category]);
      if (value !== state[category]) { changed = true; }
      state[category] = value;
    });

    if (decided === true || decided === false) {
      if (decided !== state.decided) { changed = true; }
      state.decided = decided;
    }

    if (!changed) {
      return;
    }

    log('consent', snapshot(), 'was', previous);
    settle();
  }

  function settle() {
    var first = !known;
    known = true;
    var detail = snapshot();

    if (config.consentMode && config.consentMode.update && config.state === 'live') {
      consentModeUpdate();
    }

    release();
    placeholders();

    (window.dataLayer = window.dataLayer || []).push({ event: 'cookie_connect_update', cookie_connect: detail });

    if (first) {
      resolveReady(detail);
      dispatch('cookieconnect:ready', detail);
    }

    dispatch('cookieconnect:change', detail);

    listeners.slice().forEach(function (listener) {
      try { listener(snapshot()); } catch (e) { if (window.console) { window.console.error(e); } }
    });
  }

  function dispatch(name, detail) {
    var event;
    try {
      event = new CustomEvent(name, { detail: detail });
    } catch (e) {
      event = document.createEvent('CustomEvent');
      event.initCustomEvent(name, false, false, detail);
    }
    document.dispatchEvent(event);
  }

  function consentModeUpdate() {
    var types = (config.consentMode && config.consentMode.types) || {};
    var values = {};

    Object.keys(types).forEach(function (category) {
      (types[category] || []).forEach(function (type) {
        values[type] = category === 'necessary' || state[category] ? 'granted' : 'denied';
      });
    });

    function gtag() { (window.dataLayer = window.dataLayer || []).push(arguments); }
    (typeof window.gtag === 'function' ? window.gtag : gtag)('consent', 'update', values);
  }

  /* Running content blocked with Cookie Connect's markup ------------------ */

  var queue = [];
  var running = false;

  function release() {
    if (!document.querySelectorAll) { return; }

    var found = document.querySelectorAll('[data-cookie-connect]');

    for (var i = 0; i < found.length; i++) {
      var element = found[i];
      if (allows(element.getAttribute('data-cookie-connect'))) {
        element.removeAttribute('data-cookie-connect');
        queue.push(element);
      }
    }

    next();
  }

  /** Runs queued elements in page order; a script with a src that isn't async holds the queue until it loads. */
  function next() {
    if (running) { return; }

    while (queue.length) {
      var element = queue.shift();

      if (element.tagName === 'SCRIPT') {
        var script = activate(element);
        if (script && script.src && !script.hasAttribute('async')) {
          running = true;
          var done = function () { running = false; next(); };
          script.onload = done;
          script.onerror = done;
          return;
        }
      } else {
        move(element, 'data-cookie-connect-src', 'src');
        move(element, 'data-cookie-connect-srcset', 'srcset');
      }
    }
  }

  function move(element, from, to) {
    if (element.hasAttribute(from)) {
      element.setAttribute(to, element.getAttribute(from));
      element.removeAttribute(from);
    }
  }

  function activate(blocked) {
    if (!blocked.parentNode) { return null; }

    var script = document.createElement('script');
    var attributes = blocked.attributes;

    for (var i = 0; i < attributes.length; i++) {
      var name = attributes[i].name;
      if (name === 'type' || name.indexOf('data-cookie-connect') === 0) { continue; }
      script.setAttribute(name, attributes[i].value);
    }

    var type = blocked.getAttribute('data-cookie-connect-type');
    if (type) { script.setAttribute('type', type); }

    var src = blocked.getAttribute('data-cookie-connect-src');
    if (src) {
      script.async = blocked.hasAttribute('async');
      script.src = src;
    } else {
      script.text = blocked.text || blocked.textContent || '';
    }

    var own = blocked.nonce || blocked.getAttribute('nonce') || nonce;
    if (own) { script.nonce = own; script.setAttribute('nonce', own); }

    blocked.parentNode.replaceChild(script, blocked);

    return script;
  }

  function placeholders() {
    if (!document.querySelectorAll) { return; }

    var found = document.querySelectorAll('[data-cookie-connect-placeholder]');

    for (var i = 0; i < found.length; i++) {
      found[i].hidden = allows(found[i].getAttribute('data-cookie-connect-placeholder'));
    }
  }

  /* Cookie settings links -------------------------------------------------- */

  function openPreferences() {
    if (openers.length) {
      openers.forEach(function (opener) { try { opener(); } catch (e) { log('open failed', e); } });
      return true;
    }

    var adapter = adapters[config.provider];

    if (adapter && adapter.open) {
      try { adapter.open(); return true; } catch (e) { log('open failed', e); }
    }

    log('No consent platform to open.');
    return false;
  }

  on(document, 'click', function (event) {
    var target = event.target;
    var link = target && target.closest ? target.closest('[data-cookie-connect-open], a[href$="#cookie-settings"]') : null;

    if (!link) { return; }

    // The platform opens its own preferences from elements with its class.
    if (config.nativeClass && link.classList && link.classList.contains(config.nativeClass)) { return; }

    event.preventDefault();
    openPreferences();
  });

  /* Platforms -------------------------------------------------------------- */

  var adapters = {
    cookiebot: {
      start: function (emit) {
        function read() {
          var cookiebot = window.Cookiebot;
          if (!cookiebot || !cookiebot.consent) { return; }
          emit(byId(function (id) { return cookiebot.consent[id]; }), !!cookiebot.hasResponse);
        }
        ['CookiebotOnConsentReady', 'CookiebotOnAccept', 'CookiebotOnDecline'].forEach(function (name) { on(window, name, read); });
      },
      open: function () { window.Cookiebot.renew(); }
    },

    usercentrics: {
      start: function (emit) {
        function read(details) {
          var categories = (details && details.categories) || {};
          if (config.debug) {
            OPTIONAL.forEach(function (category) {
              ids(category).forEach(function (id) {
                if (!categories[id]) { log('Usercentrics has no category "' + id + '" (for ' + category + '). Its categories: ' + Object.keys(categories).join(', ')); }
              });
            });
          }
          var fromUser = details && details.consent && details.consent.fromUserAction;
          emit(byId(function (id) { return categories[id] && categories[id].state === 'ALL_ACCEPTED'; }), fromUser ? true : null);
        }
        on(window, 'UC_CONSENT', function (event) { read(event.detail); });
        on(window, 'UC_UI_INITIALIZED', function () {
          var cmp = window.__ucCmp;
          if (cmp && cmp.getConsentDetails) { cmp.getConsentDetails().then(read, function () {}); }
        });
      },
      open: function () { window.__ucCmp.showSecondLayer(); }
    },

    onetrust: {
      start: function (emit) {
        function read(groups) {
          var active = Array.isArray(groups) ? groups : String(window.OnetrustActiveGroups || '').split(',').filter(Boolean);
          var ot = window.OneTrust;
          emit(byId(function (id) { return active.indexOf(id) !== -1; }), ot && ot.IsAlertBoxClosed ? !!ot.IsAlertBoxClosed() : null);
        }
        on(window, 'OneTrustGroupsUpdated', function (event) { read(event.detail); });
      },
      open: function () { window.OneTrust.ToggleInfoDisplay(); }
    },

    cookieyes: {
      start: function (emit) {
        function read() {
          if (typeof window.getCkyConsent !== 'function') { return; }
          var consent = window.getCkyConsent() || {};
          var categories = consent.categories || {};
          emit(byId(function (id) { return categories[id]; }), !!consent.isUserActionCompleted);
        }
        on(document, 'cookieyes_banner_load', read);
        on(document, 'cookieyes_consent_update', read);
      },
      open: function () {
        if (typeof window.revisitCkyConsent === 'function') { window.revisitCkyConsent(); return; }
        var element = document.querySelector('.cky-banner-element');
        if (element) { element.click(); }
      }
    },

    iubenda: {
      start: function (emit) {
        var iub = window._iub = window._iub || [];
        var configuration = iub.csConfiguration = iub.csConfiguration || {};
        var callbacks = configuration.callback = configuration.callback || {};

        function read() {
          var api = window._iub && window._iub.cs && window._iub.cs.api;
          if (!api || typeof api.getPreferences !== 'function') { return; }
          var preferences = api.getPreferences() || {};
          var purposes = preferences.purposes || {};
          var perPurpose = Object.keys(purposes).length > 0;
          emit(byId(function (id) { return perPurpose ? purposes[id] === true : preferences.consent === true; }), null);
        }

        ['onReady', 'onPreferenceExpressed', 'onConsentGiven', 'onConsentRejected'].forEach(function (name) {
          var theirs = callbacks[name];
          callbacks[name] = function () {
            if (typeof theirs === 'function') { theirs.apply(this, arguments); }
            read();
          };
        });

        // With the one-line embed iubenda may not call the callbacks above,
        // so also read once its API is there and whenever its cookie changes.
        when(function () { return window._iub.cs && window._iub.cs.api && window._iub.cs.api.getPreferences; }, function () {
          read();
          var last = cookies();
          setInterval(function () {
            var now = cookies();
            if (now !== last) { last = now; read(); }
          }, 1000);
        });

        function cookies() {
          return (document.cookie.match(/_iub_cs[^;]*/g) || []).join(';');
        }
      },
      open: function () { window._iub.cs.api.openPreferences(); }
    },

    termly: {
      start: function (emit) {
        when(function () { return window.Termly && typeof window.Termly.on === 'function'; }, function () {
          var termly = window.Termly;
          function read(decided) {
            var consent = typeof termly.getConsentState === 'function' ? termly.getConsentState() : null;
            if (!consent) { return; }
            emit(byId(function (id) { return consent[id] === true; }), decided);
          }
          termly.on('initialized', function () { read(null); });
          termly.on('consent', function () { read(true); });
          read(null);
        });
      },
      open: function () {
        if (typeof window.displayPreferenceModal === 'function') { window.displayPreferenceModal(); return; }
        var element = document.querySelector('.termly-display-preferences');
        if (element) { element.click(); }
      }
    },

    cookiefirst: {
      start: function (emit) {
        function read(consent) {
          consent = consent || (window.CookieFirst && window.CookieFirst.consent);
          if (!consent) { emit({}, false); return; }
          emit(byId(function (id) { return consent[id] === true; }), true);
        }
        on(window, 'cf_init', function () { read(); });
        on(window, 'cf_consent_loaded', function (event) { read(event.detail); });
        on(window, 'cf_consent', function (event) { read(event.detail); });
      },
      open: function () {
        try { window.CookieFirst.openPanel(); } catch (e) {
          if (typeof window.cookiefirst_show_settings === 'function') { window.cookiefirst_show_settings(); }
        }
      }
    },

    'cookie-script': {
      start: function (emit) {
        function read() {
          var instance = window.CookieScript && window.CookieScript.instance;
          if (!instance || typeof instance.currentState !== 'function') { return; }
          var current = instance.currentState() || {};
          var categories = current.categories || [];
          emit(byId(function (id) { return categories.indexOf(id) !== -1; }), !!current.action);
        }
        ['CookieScriptLoaded', 'CookieScriptAccept', 'CookieScriptAcceptAll', 'CookieScriptReject'].forEach(function (name) { on(window, name, read); });
      },
      open: function () { window.CookieScript.instance.show(); }
    },

    custom: {
      start: function () {}
    }
  };

  /* The API ----------------------------------------------------------------- */

  window.CookieConnect = {
    version: config.version || '1',
    provider: config.provider || null,

    /** Resolves with the first reading of the visitor's choice. */
    ready: ready,

    /** The current choice: { necessary, preferences, analytics, marketing, decided, provider }. */
    get: snapshot,

    /** Whether every category given ("analytics", "analytics marketing" or a list) is allowed. */
    has: allows,

    /** Calls listener now (once known) and on every change. Returns a function that stops it. */
    on: function (event, listener) {
      if (event !== 'change' || typeof listener !== 'function') { return function () {}; }
      listeners.push(listener);
      if (known) { listener(snapshot()); }
      return function () {
        var index = listeners.indexOf(listener);
        if (index !== -1) { listeners.splice(index, 1); }
      };
    },

    /** Opens the consent platform's preferences. */
    openPreferences: openPreferences,

    /** For the Custom platform: what opens its preferences. */
    onOpen: function (opener) {
      if (typeof opener === 'function') { openers.push(opener); }
    },

    /** For the Custom platform: tells Cookie Connect what the visitor chose. */
    set: function (choice) {
      update(choice || {}, true);
    },

    /** Looks again for blocked content, e.g. after a page loaded over AJAX. */
    refresh: function () {
      release();
      placeholders();
    }
  };

  var adapter = config.state === 'live' ? adapters[config.provider] : null;

  if (adapter) {
    adapter.start(update);
    // If the platform never answers (blocked by an ad blocker, say), say so
    // after five seconds rather than leaving ready() hanging.
    setTimeout(function () { if (!known) { log('No answer from ' + config.provider + '.'); settle(); } }, 5000);
  } else if (config.provider !== 'custom') {
    settle();
  }

  function onDom() {
    release();
    placeholders();

    if (window.MutationObserver) {
      var pending = false;
      new window.MutationObserver(function (records) {
        if (pending) { return; }
        for (var i = 0; i < records.length; i++) {
          if (records[i].addedNodes.length) {
            pending = true;
            setTimeout(function () { pending = false; window.CookieConnect.refresh(); }, 0);
            return;
          }
        }
      }).observe(document.documentElement, { childList: true, subtree: true });
    }

    if (window.location.hash === '#cookie-settings') {
      ready.then(function () { setTimeout(openPreferences, 300); });
    }
  }

  if (document.readyState === 'loading') {
    on(document, 'DOMContentLoaded', onDom);
  } else {
    onDom();
  }
})(window, document);

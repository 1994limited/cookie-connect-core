// Builds a page in jsdom with Cookie Connect's runtime in its head, the way
// the PHP renderer prints it.
import { JSDOM } from 'jsdom';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const runtime = readFileSync(fileURLToPath(new URL('../../resources/runtime/cookie-connect.js', import.meta.url)), 'utf8');

export const TYPES = {
  necessary: ['security_storage'],
  preferences: ['functionality_storage', 'personalization_storage'],
  analytics: ['analytics_storage'],
  marketing: ['ad_storage', 'ad_user_data', 'ad_personalization'],
};

/**
 * @param {object} options
 * @param {object} [options.config]  window.CookieConnectConfig
 * @param {string} [options.body]    HTML for the body
 * @param {(window) => void} [options.before]  sets up the platform's globals before the runtime runs
 */
const open = [];

/** Closes every page made so far, so their timers don't keep Node running. */
export function closeAll() {
  while (open.length) open.pop().close();
}

export function page({ config = {}, body = '', before } = {}) {
  const dom = new JSDOM(`<!doctype html><html><head></head><body>${body}</body></html>`, {
    runScripts: 'dangerously',
    url: 'https://example.com/',
    pretendToBeVisual: true,
  });
  const { window } = dom;
  open.push(window);

  window.CookieConnectConfig = {
    version: '1.0.0',
    provider: null,
    state: 'block',
    map: {},
    nativeClass: null,
    consentMode: { update: false, types: TYPES },
    debug: false,
    ...config,
  };

  if (before) before(window);

  const script = window.document.createElement('script');
  script.setAttribute('nonce', 'abc123');
  script.textContent = runtime;
  window.document.head.appendChild(script);

  return window;
}

export function fire(target, name, detail) {
  const win = target.defaultView || target;
  target.dispatchEvent(new win.CustomEvent(name, { detail }));
}

/** A plain copy of an object made inside the page, for deepEqual. */
export const plain = (value) => JSON.parse(JSON.stringify(value));

export const tick = (ms = 0) => new Promise((resolve) => setTimeout(resolve, ms));

/** The consent commands pushed to the dataLayer, as arrays. */
export function consentCommands(window) {
  return (window.dataLayer || [])
    .filter((entry) => entry && typeof entry === 'object' && entry.length !== undefined && entry[0] === 'consent')
    .map((entry) => Array.from(entry));
}

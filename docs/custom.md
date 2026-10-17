# Custom platforms

Use Custom for a consent platform Cookie Connect doesn't list.

1. Paste the platform's head code into **Consent platform code**. It's printed first, as it is.
2. In **Tell Cookie Connect about consent**, call `CookieConnect.set({ preferences, analytics, marketing })` from the platform's consent callback or event, on page load and whenever the visitor changes their mind.
3. In **Open the preferences**, the code that opens the platform's cookie settings.
4. Say whether the platform sets Google Consent Mode defaults or sends updates itself. If it doesn't, Cookie Connect does, from what you pass to `set()`.

Content marked with the consent helper uses Cookie Connect's own blocking, so it runs as soon as `set()` allows it.

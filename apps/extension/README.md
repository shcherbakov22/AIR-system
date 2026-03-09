# Browser Extension

This is now a real client skeleton for the platform, not just a placeholder folder.

Current scope:

- Manifest V3 extension shell
- background service worker
- periodic heartbeat to the platform API
- local storage based dev configuration

Current non-goals:

- no monitoring or `Ivan` behavior
- no whitelist enforcement
- no attention tracking

Key files:

- `manifest.json`
- `src/background.js`

Local dev notes:

- load this folder as an unpacked extension
- configure `chrome.storage.local` if you need a different platform URL or token
- the platform heartbeat endpoint is `POST /api/edge-clients/heartbeat`

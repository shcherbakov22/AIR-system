# Hardware Bridge

This is now a real client skeleton for the platform, not just a placeholder folder.

Current scope:

- Node-based client shell
- platform heartbeat request
- environment-driven configuration

Current non-goals:

- no device control yet
- no queue execution yet
- no consequence automation yet

Local dev:

- set `PLATFORM_BASE_URL` if the platform is not running on `http://127.0.0.1:8000`
- set `EDGE_CLIENT_SHARED_TOKEN` if you changed the platform token
- run `npm run heartbeat`

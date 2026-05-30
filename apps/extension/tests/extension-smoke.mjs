import http from 'node:http';
import { mkdtemp, rm } from 'node:fs/promises';
import { existsSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn } from 'node:child_process';

const extensionDir = resolve(new URL('..', import.meta.url).pathname);
const contentRequests = [];
const visits = [];
const accessRequests = [];
const activityEvents = [];

const browserPolicy = {
  mode: 'blacklist',
  default_unblock_scope: 'domain_tree',
  log_full_url: true,
  rules: [
    {
      id: 1,
      effect: 'block',
      match_type: 'domain_tree',
      value: 'blocked.test',
      expires_at: null,
    },
  ],
  updated_at: new Date().toISOString(),
};

main().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});

async function main() {
  assertManifestHasPopup();

  const backend = await listen((req, res) => {
    collectJson(req).then((body) => {
      if (req.url === '/api/companion/browser/policy' && req.method === 'GET') {
        assertAuth(req);
        return sendJson(res, 200, { accepted: true, policy: browserPolicy });
      }

      if (req.url === '/api/companion/browser/visits' && req.method === 'POST') {
        assertAuth(req);
        visits.push(body);
        return sendJson(res, 200, {
          accepted: true,
          visit: {
            id: visits.length,
            decision: body?.meta?.decision || 'unknown',
            host: new URL(body.url).hostname,
          },
        });
      }

      if (req.url === '/api/companion/browser/access-requests' && req.method === 'POST') {
        assertAuth(req);
        accessRequests.push(body);
        return sendJson(res, 201, {
          accepted: true,
          request: {
            id: accessRequests.length,
            status: 'pending',
            host: new URL(body.url).hostname,
            registrable_domain: 'blocked.test',
          },
        });
      }

      if (req.url === '/api/companion/activity' && req.method === 'POST') {
        assertAuth(req);
        activityEvents.push(body);
        return sendJson(res, 200, {
          accepted: true,
          event: {
            id: activityEvents.length,
            event_type: body?.event_type || 'unknown',
            observed_at: new Date().toISOString(),
          },
        });
      }

      sendJson(res, 404, { error: 'not_found' });
    }).catch((error) => {
      sendJson(res, 500, { error: error.message });
    });
  });

  const content = await listen((req, res) => {
    contentRequests.push({ host: req.headers.host, url: req.url });
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(`<title>${req.headers.host}</title><h1>${req.headers.host}</h1>`);
  });

  const userDataDir = await mkdtemp(join(tmpdir(), 'air-extension-profile-'));
  const chromium = spawn('chromium', [
    '--headless=new',
    '--no-sandbox',
    '--disable-gpu',
    '--use-fake-device-for-media-stream',
    '--use-fake-ui-for-media-stream',
    '--remote-debugging-port=0',
    `--user-data-dir=${userDataDir}`,
    `--disable-extensions-except=${extensionDir}`,
    `--load-extension=${extensionDir}`,
    `--host-resolver-rules=MAP allowed.test 127.0.0.1,MAP blocked.test 127.0.0.1,MAP sub.blocked.test 127.0.0.1`,
    'about:blank',
  ], {
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  let stderr = '';
  chromium.stderr.on('data', (chunk) => {
    stderr += chunk.toString();
  });

  try {
    const port = await waitForDevToolsPort(userDataDir, () => stderr);
  const client = await CdpClient.connect(`http://127.0.0.1:${port}`);
  const extensionTarget = await waitForExtensionTarget(client);
  const extensionId = new URL(extensionTarget.url).host;
  const optionsSession = await openExtensionPage(client, `chrome-extension://${extensionId}/src/options.html`);

  await evaluateOrThrow(client, {
    expression: `chrome.storage.local.set(${JSON.stringify({
      platformUrl: `http://127.0.0.1:${backend.port}`,
      deviceToken: 'test-device-token',
    })}).then(() => chrome.runtime.sendMessage({ type: 'sync_policy' })).then((response) => {
      if (!response || !response.ok) {
        throw new Error(response?.error || 'policy sync failed');
      }
      return response;
    })`,
    awaitPromise: true,
  }, optionsSession);
  await waitFor(
    () => activityEvents.some((event) => event.event_type === 'extension_status' && event.payload?.status === 'policy_sync_ok' && event.payload?.version === '0.1.15'),
    5000,
    'extension status activity was not posted after policy sync',
  );

  const popupSession = await openExtensionPage(client, `chrome-extension://${extensionId}/src/popup.html`);
  await waitFor(async () => {
    const result = await evaluateOrThrow(client, {
      expression: `({
        start: document.getElementById('startbg')?.textContent,
        recalibrate: document.getElementById('recalibrate')?.textContent,
        status: document.getElementById('status')?.textContent,
      })`,
      returnByValue: true,
    }, popupSession);
    const value = result.result.value;

    return value.start === 'Start background detection'
      && value.recalibrate === 'Recalibrate baseline'
      && String(value.status || '').includes('Server:');
  }, 5000, 'popup did not show the attention tracking interface');

  await evaluateOrThrow(client, {
    expression: `document.getElementById('startbg').click()`,
  }, popupSession);
  await waitFor(async () => {
    const result = await evaluateOrThrow(client, {
      expression: `document.getElementById('status')?.textContent || ''`,
      returnByValue: true,
    }, popupSession);

    return /Starting|Calibrating|Looking|face|Camera/.test(result.result.value || '');
  }, 10000, 'attention popup start button did not update tracker status');
  await waitFor(async () => {
    const result = await evaluateOrThrow(client, {
      expression: `chrome.storage.local.get(['last_state']).then((stored) => stored.last_state || null)`,
      awaitPromise: true,
      returnByValue: true,
    }, popupSession);
    const state = result.result.value;

    return state?.mode && state.mode !== 'starting' && state.mode !== 'error';
  }, 30000, 'attention tracker did not reach a non-error runtime state');

  await navigateAndWait(client, `http://allowed.test:${content.port}/allowed`);
  await waitFor(
    () => visits.some((visit) => visit.url.includes('allowed.test') && visit.meta?.decision === 'allowed'),
    5000,
    'allowed visit was not logged',
  );
  assert(contentRequests.some((request) => request.host?.startsWith(`allowed.test:${content.port}`)), 'allowed site did not reach content server');

  const blockedPage = await navigateAndWait(client, `http://blocked.test:${content.port}/blocked`);
  assert(blockedPage.href.includes(`chrome-extension://${extensionId}/src/blocked.html`), `blocked navigation did not land on extension page: ${blockedPage.href}`);
  await waitFor(
    () => visits.some((visit) => visit.url.includes('blocked.test') && visit.meta?.decision === 'blocked' && visit.meta?.source === 'explicit_navigation'),
    5000,
    'blocked visit was not logged',
  );
  assert(!contentRequests.some((request) => request.host?.startsWith(`blocked.test:${content.port}`)), 'blocked site reached content server');

  await evaluateOrThrow(client, {
    expression: `
      document.getElementById('reason').value = 'Need it for class';
      document.getElementById('requestButton').click();
    `,
  }, blockedPage.sessionId);

  await waitFor(() => accessRequests.length === 1, 5000, 'access request was not posted');
  assert(accessRequests[0].url.includes('blocked.test'), 'access request did not include blocked URL');
  assert(accessRequests[0].reason === 'Need it for class', 'access request reason was not submitted');

  console.log('extension smoke test passed');
  } finally {
    terminateProcess(chromium);
    backend.server.close();
    content.server.close();
    await rmRetry(userDataDir);
  }
}

function assertManifestHasPopup() {
  const manifest = JSON.parse(readFileSync(join(extensionDir, 'manifest.json'), 'utf8'));

  assert(manifest.action?.default_popup === 'src/popup.html', 'manifest does not define the toolbar popup');
  assert(manifest.version === '0.1.15', 'manifest version was not bumped');
  assert(manifest.permissions?.includes('offscreen'), 'manifest does not allow offscreen attention detection');
  assert(manifest.permissions?.includes('videoCapture'), 'manifest does not request extension camera capture permission');
  assert(manifest.storage?.managed_schema === 'src/managed-schema.json', 'manifest does not declare managed storage schema');
  assert(readFileSync(join(extensionDir, 'src/managed-schema.json'), 'utf8').includes('deviceToken'), 'managed schema does not include device token');
}

function assertAuth(req) {
  assert(req.headers.authorization === 'Bearer test-device-token', 'missing extension bearer token');
}

function assert(condition, message) {
  if (!condition) {
    throw new Error(message);
  }
}

function terminateProcess(process) {
  if (process.exitCode !== null || process.signalCode !== null) {
    return;
  }

  process.kill('SIGTERM');
  const timer = setTimeout(() => {
    if (process.exitCode === null && process.signalCode === null) {
      process.kill('SIGKILL');
    }
  }, 1000);
  timer.unref();
}

async function rmRetry(path) {
  for (let attempt = 0; attempt < 10; attempt += 1) {
    try {
      await rm(path, { recursive: true, force: true });
      return;
    } catch (error) {
      if (attempt === 9) {
        throw error;
      }

      await new Promise((resolveDelay) => setTimeout(resolveDelay, 200));
    }
  }
}

async function listen(handler) {
  const server = http.createServer(handler);
  await new Promise((resolveListen) => server.listen(0, '127.0.0.1', resolveListen));

  return {
    server,
    port: server.address().port,
  };
}

async function collectJson(req) {
  if (req.method === 'GET') {
    return null;
  }

  const chunks = [];

  for await (const chunk of req) {
    chunks.push(chunk);
  }

  const raw = Buffer.concat(chunks).toString();

  return raw ? JSON.parse(raw) : null;
}

function sendJson(res, status, body) {
  res.writeHead(status, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(body));
}

async function waitForDevToolsPort(userDataDir, stderr) {
  const activePortPath = join(userDataDir, 'DevToolsActivePort');

  await waitFor(() => existsSync(activePortPath), 10000, `Chromium did not expose DevTools. stderr:\n${stderr()}`);

  return readFileSync(activePortPath, 'utf8').split('\n')[0].trim();
}

async function waitForExtensionTarget(client) {
  let target = null;

  await waitFor(async () => {
    const targets = await client.send('Target.getTargets');
    target = targets.targetInfos.find((info) => (
      info.type === 'service_worker'
      && info.url.startsWith('chrome-extension://')
      && info.url.endsWith('/src/background.js')
    ));

    return Boolean(target);
  }, 10000, 'extension service worker did not start');

  return target;
}

async function navigateAndWait(client, url) {
  const { targetId } = await client.send('Target.createTarget', { url: 'about:blank' });
  const sessionId = await client.attach(targetId);

  await client.send('Page.enable', {}, sessionId);
  await client.send('Page.navigate', { url }, sessionId);

  let href = '';
  await waitFor(async () => {
    const result = await client.send('Runtime.evaluate', {
      expression: 'location.href',
      returnByValue: true,
    }, sessionId);
    href = result.result.value || '';

    return href.includes(url) || href.startsWith('chrome-extension://');
  }, 10000, `navigation did not complete for ${url}`);

  await new Promise((resolveDelay) => setTimeout(resolveDelay, 500));

  const result = await client.send('Runtime.evaluate', {
    expression: 'location.href',
    returnByValue: true,
  }, sessionId);

  return {
    href: result.result.value || href,
    sessionId,
  };
}

async function openExtensionPage(client, url) {
  const { targetId } = await client.send('Target.createTarget', { url });
  const sessionId = await client.attach(targetId);

  await waitFor(async () => {
    const result = await client.send('Runtime.evaluate', {
      expression: 'document.readyState',
      returnByValue: true,
    }, sessionId);

    return result.result.value === 'complete' || result.result.value === 'interactive';
  }, 5000, `extension page did not load: ${url}`);

  return sessionId;
}

async function evaluateOrThrow(client, params, sessionId) {
  const result = await client.send('Runtime.evaluate', params, sessionId);

  if (result.exceptionDetails) {
    throw new Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
  }

  return result;
}

async function waitFor(predicate, timeoutMs, message) {
  const deadline = Date.now() + timeoutMs;

  while (Date.now() < deadline) {
    if (await predicate()) {
      return;
    }

    await new Promise((resolveDelay) => setTimeout(resolveDelay, 100));
  }

  throw new Error(message);
}

class CdpClient {
  constructor(ws) {
    this.ws = ws;
    this.id = 0;
    this.pending = new Map();

    ws.addEventListener('message', (event) => {
      const message = JSON.parse(event.data);

      if (!message.id || !this.pending.has(message.id)) {
        return;
      }

      const { resolve, reject } = this.pending.get(message.id);
      this.pending.delete(message.id);

      if (message.error) {
        reject(new Error(message.error.message));
      } else {
        resolve(message.result || {});
      }
    });
  }

  static async connect(baseUrl) {
    const version = await fetch(`${baseUrl}/json/version`).then((response) => response.json());
    const ws = new WebSocket(version.webSocketDebuggerUrl);

    await new Promise((resolveOpen, rejectOpen) => {
      ws.addEventListener('open', resolveOpen, { once: true });
      ws.addEventListener('error', rejectOpen, { once: true });
    });

    return new CdpClient(ws);
  }

  async attach(targetId) {
    const result = await this.send('Target.attachToTarget', {
      targetId,
      flatten: true,
    });

    return result.sessionId;
  }

  send(method, params = {}, sessionId = null) {
    const id = ++this.id;
    const message = { id, method, params };

    if (sessionId) {
      message.sessionId = sessionId;
    }

    this.ws.send(JSON.stringify(message));

    return new Promise((resolve, reject) => {
      this.pending.set(id, { resolve, reject });
      setTimeout(() => {
        if (!this.pending.has(id)) {
          return;
        }

        this.pending.delete(id);
        reject(new Error(`CDP command timed out: ${method}`));
      }, 10000);
    });
  }
}

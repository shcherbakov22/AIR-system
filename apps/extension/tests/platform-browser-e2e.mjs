import http from 'node:http';
import { mkdtemp, rm, writeFile, readFile } from 'node:fs/promises';
import { existsSync, readFileSync, closeSync, openSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn } from 'node:child_process';

const repoRoot = resolve(new URL('../../..', import.meta.url).pathname);
const platformDir = join(repoRoot, 'apps/platform');
const extensionDir = resolve(new URL('..', import.meta.url).pathname);
const contentRequests = [];

main().then(() => {
  process.exit(0);
}).catch((error) => {
  console.error(error);
  process.exit(1);
});

async function main() {
  const workDir = await mkdtemp(join(tmpdir(), 'air-platform-browser-e2e-'));
  const dbPath = join(workDir, 'database.sqlite');
  const seedPath = join(workDir, 'seed.php');
  const seedOutputPath = join(workDir, 'seed.json');
  closeSync(openSync(dbPath, 'w'));

  const platformPort = await freePort();
  const platformOrigin = `http://platform.test:${platformPort}`;
  const content = await listen((req, res) => {
    contentRequests.push({ host: req.headers.host, url: req.url });
    res.writeHead(200, { 'Content-Type': 'text/html' });
    res.end(`<title>${req.headers.host}</title><h1>${req.headers.host}</h1>`);
  });

  const env = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    APP_URL: platformOrigin,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: dbPath,
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    BCRYPT_ROUNDS: '4',
  };

  let server = null;
  let chromium = null;
  let client = null;
  let userDataDir = null;

  try {
    await run('php', ['artisan', 'migrate:fresh', '--force'], { cwd: platformDir, env });
    await writeFile(seedPath, seedPhp(seedOutputPath), 'utf8');
    await run('php', ['artisan', 'tinker', `--execute=require ${JSON.stringify(seedPath)};`], { cwd: platformDir, env });

    const seed = JSON.parse(await readFile(seedOutputPath, 'utf8'));

    server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${platformPort}`], {
      cwd: platformDir,
      env,
      stdio: ['ignore', 'pipe', 'pipe'],
    });

    await waitForHttp(`http://127.0.0.1:${platformPort}/up`, 15000, 'Laravel server did not start');

    userDataDir = await mkdtemp(join(tmpdir(), 'air-extension-platform-profile-'));
    chromium = spawn('chromium', [
      '--headless=new',
      '--no-sandbox',
      '--disable-gpu',
      '--remote-debugging-port=0',
      `--user-data-dir=${userDataDir}`,
      `--disable-extensions-except=${extensionDir}`,
      `--load-extension=${extensionDir}`,
      `--host-resolver-rules=MAP platform.test 127.0.0.1,MAP coding.test 127.0.0.1,MAP docs.coding.test 127.0.0.1,MAP blocked-site.test 127.0.0.1`,
      'about:blank',
    ], {
      stdio: ['ignore', 'pipe', 'pipe'],
    });

    let chromiumStderr = '';
    chromium.stderr.on('data', (chunk) => {
      chromiumStderr += chunk.toString();
    });

    const port = await waitForDevToolsPort(userDataDir, () => chromiumStderr);
    client = await CdpClient.connect(`http://127.0.0.1:${port}`);
    const extensionTarget = await waitForExtensionTarget(client);
    const extensionId = new URL(extensionTarget.url).host;
    const optionsSession = await openPage(client, `chrome-extension://${extensionId}/src/options.html`);

    await evaluateOrThrow(client, {
      expression: `chrome.storage.local.set(${JSON.stringify({
        platformUrl: platformOrigin,
        deviceToken: seed.device_token,
      })}).then(() => chrome.runtime.sendMessage({ type: 'sync_policy' })).then((response) => {
        if (!response || !response.ok) {
          throw new Error(response?.error || 'policy sync failed');
        }
        return response;
      })`,
      awaitPromise: true,
    }, optionsSession);

    const tennisBlocked = await navigateAndWait(client, `http://coding.test:${content.port}/tennis`);
    assert(tennisBlocked.href.includes(`chrome-extension://${extensionId}/src/blocked.html`), 'Tennis task did not block coding.test');
    assert(!contentRequests.some((request) => request.host === `coding.test:${content.port}`), 'Tennis block allowed coding.test to reach content server');

    await evaluateOrThrow(client, {
      expression: `
        document.getElementById('reason').value = 'Need docs for this task';
        document.getElementById('requestButton').click();
      `,
    }, tennisBlocked.sessionId);

    await waitForDb(env, "App\\Models\\BrowserAccessRequest::query()->where('status', 'pending')->count() === 1", 10000, 'access request was not stored');

    const adminSession = await openPage(client, `${platformOrigin}/login`);
    await waitForSelector(client, adminSession, '#username', 10000);
    await evaluateOrThrow(client, {
      expression: `
        document.getElementById('username').value = 'mentor_e2e';
        document.getElementById('username').dispatchEvent(new Event('input', { bubbles: true }));
        document.getElementById('password').value = 'password';
        document.getElementById('password').dispatchEvent(new Event('input', { bubbles: true }));
        document.querySelector('form').requestSubmit();
      `,
    }, adminSession);

    await waitForLocation(client, adminSession, '/admin/dashboard', 10000);
    await navigateExisting(client, adminSession, `${platformOrigin}/admin/students/${seed.student_id}/devices`);
    await waitForSelector(client, adminSession, 'button', 10000);
    await waitForText(client, adminSession, 'coding.test and subdomains', 10000);

    await evaluateOrThrow(client, {
      expression: `
        const allowButton = [...document.querySelectorAll('button')]
          .find((button) => button.textContent.trim() === 'Allow');
        if (!allowButton) {
          throw new Error('Allow button not found');
        }
        allowButton.click();
      `,
    }, adminSession);

    await waitForDb(env, `App\\Models\\BrowserPolicyRule::query()->where('task_template_id', ${seed.tennis_template_id})->where('value', 'coding.test')->exists()`, 10000, 'admin approval did not create tennis task rule');

    await evaluateOrThrow(client, {
      expression: `
        const retryButton = document.getElementById('retryButton');
        if (!retryButton) {
          throw new Error('Try again button not found');
        }
        retryButton.click();
      `,
    }, tennisBlocked.sessionId);
    await waitForLocation(client, tennisBlocked.sessionId, `http://coding.test:${content.port}/tennis`, 10000);
    assert(contentRequests.some((request) => request.host === `coding.test:${content.port}` && request.url === '/tennis'), 'approved Tennis domain did not reach content server after Try again');

    await switchActiveTask(env, seed.tennis_session_id, seed.coding_template_id, seed.student_id, seed.student_user_id);
    await syncPolicyFromOptions(client, optionsSession);

    const codingAllowed = await navigateAndWait(client, `http://docs.coding.test:${content.port}/coding`);
    assert(codingAllowed.href.includes('docs.coding.test'), 'Coding task did not allow docs.coding.test via coding.test domain-tree rule');
    assert(contentRequests.some((request) => request.host === `docs.coding.test:${content.port}` && request.url === '/coding'), 'Coding allowed site did not reach content server');

    const codingBlocked = await navigateAndWait(client, `http://blocked-site.test:${content.port}/coding`);
    assert(codingBlocked.href.includes(`chrome-extension://${extensionId}/src/blocked.html`), 'Coding task did not block unknown site');
    assert(!contentRequests.some((request) => request.host === `blocked-site.test:${content.port}`), 'Coding unknown blocked site reached content server');

    console.log('platform browser e2e passed');
  } finally {
    if (client) {
      client.close();
    }

    if (chromium) {
      terminateProcess(chromium);
    }

    if (server) {
      terminateProcess(server);
    }

    closeServer(content.server);
    if (userDataDir) {
      rmRetry(userDataDir).catch(() => {});
    }
    rmRetry(workDir).catch(() => {});
  }
}

function seedPhp(outputPath) {
  return `<?php

use App\\Enums\\UserRole;
use App\\Models\\BrowserPolicyRule;
use App\\Models\\Student;
use App\\Models\\StudentDevice;
use App\\Models\\TaskSession;
use App\\Models\\TaskTemplate;
use App\\Models\\User;

$admin = User::create([
    'username' => 'mentor_e2e',
    'name' => 'Mentor E2E',
    'email' => 'mentor-e2e@example.test',
    'role' => UserRole::Admin,
    'is_active' => true,
    'password' => 'password',
]);

$studentUser = User::create([
    'username' => 'student_e2e',
    'name' => 'Student E2E',
    'email' => 'student-e2e@example.test',
    'role' => UserRole::Student,
    'is_active' => true,
    'password' => 'password',
]);

$student = Student::create([
    'user_id' => $studentUser->id,
    'display_name' => 'Student E2E',
    'status' => 'active',
    'notes' => null,
]);

$device = StudentDevice::create([
    'student_id' => $student->id,
    'device_key' => 'browser-e2e-device',
    'label' => 'Browser E2E',
    'platform' => 'chrome',
    'internet_access_mode' => 'whitelist',
]);
$token = $device->issueToken();

$tennis = TaskTemplate::create([
    'title' => 'Tennis',
    'summary' => null,
    'instructions' => 'Practice tennis.',
    'default_duration_minutes' => 30,
    'requires_internet' => false,
    'created_by_user_id' => $admin->id,
]);

$coding = TaskTemplate::create([
    'title' => 'Coding',
    'summary' => null,
    'instructions' => 'Write code.',
    'default_duration_minutes' => 45,
    'requires_internet' => true,
    'created_by_user_id' => $admin->id,
]);

BrowserPolicyRule::create([
    'student_id' => null,
    'task_template_id' => $coding->id,
    'created_by_user_id' => $admin->id,
    'effect' => 'allow',
    'match_type' => 'domain_tree',
    'value' => 'coding.test',
]);

$tennisSession = TaskSession::create([
    'student_id' => $student->id,
    'task_template_id' => $tennis->id,
    'status' => 'active',
    'task_title_snapshot' => 'Tennis',
    'planned_duration_minutes' => 30,
    'started_at' => now()->subMinutes(5),
    'duration_seconds' => 300,
    'started_by_user_id' => $studentUser->id,
]);

file_put_contents(${JSON.stringify(outputPath)}, json_encode([
    'admin_id' => $admin->id,
    'student_user_id' => $studentUser->id,
    'student_id' => $student->id,
    'device_id' => $device->id,
    'device_token' => $token,
    'tennis_template_id' => $tennis->id,
    'coding_template_id' => $coding->id,
    'tennis_session_id' => $tennisSession->id,
], JSON_THROW_ON_ERROR));
`;
}

async function switchActiveTask(env, tennisSessionId, codingTemplateId, studentId, studentUserId) {
  const code = `
App\\Models\\TaskSession::query()->whereKey(${tennisSessionId})->update(['status' => 'completed', 'ended_at' => now()]);
App\\Models\\TaskSession::create([
    'student_id' => ${studentId},
    'task_template_id' => ${codingTemplateId},
    'status' => 'active',
    'task_title_snapshot' => 'Coding',
    'planned_duration_minutes' => 45,
    'started_at' => now(),
    'duration_seconds' => 0,
    'started_by_user_id' => ${studentUserId},
]);
`;
  await run('php', ['artisan', 'tinker', `--execute=${code}`], { cwd: platformDir, env });
}

async function syncPolicyFromOptions(client, optionsSession) {
  await evaluateOrThrow(client, {
    expression: `chrome.runtime.sendMessage({ type: 'sync_policy' }).then((response) => {
      if (!response || !response.ok) {
        throw new Error(response?.error || 'policy sync failed');
      }
      return response;
    })`,
    awaitPromise: true,
  }, optionsSession);
}

async function waitForDb(env, expression, timeoutMs, message) {
  await waitFor(async () => {
    const result = await run('php', ['artisan', 'tinker', `--execute=echo (${expression}) ? 'yes' : 'no';`], {
      cwd: platformDir,
      env,
      quiet: true,
    });

    return result.stdout.includes('yes');
  }, timeoutMs, message);
}

async function run(command, args, options = {}) {
  return new Promise((resolveRun, rejectRun) => {
    const child = spawn(command, args, {
      cwd: options.cwd,
      env: options.env,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    let stdout = '';
    let stderr = '';
    child.stdout.on('data', (chunk) => {
      stdout += chunk.toString();
      if (!options.quiet) process.stdout.write(chunk);
    });
    child.stderr.on('data', (chunk) => {
      stderr += chunk.toString();
      if (!options.quiet) process.stderr.write(chunk);
    });
    child.on('exit', (code) => {
      if (code === 0) {
        resolveRun({ stdout, stderr });
      } else {
        rejectRun(new Error(`${command} ${args.join(' ')} exited ${code}\n${stdout}\n${stderr}`));
      }
    });
  });
}

async function listen(handler) {
  const server = http.createServer(handler);
  await new Promise((resolveListen) => server.listen(0, '127.0.0.1', resolveListen));

  return {
    server,
    port: server.address().port,
  };
}

function closeServer(server) {
  server.closeAllConnections?.();
  server.close();
}

async function freePort() {
  const server = http.createServer((_req, res) => res.end('ok'));
  await new Promise((resolveListen) => server.listen(0, '127.0.0.1', resolveListen));
  const port = server.address().port;
  await new Promise((resolveClose) => server.close(resolveClose));
  return port;
}

async function waitForHttp(url, timeoutMs, message) {
  await waitFor(async () => {
    try {
      const response = await fetch(url);
      return response.ok;
    } catch (_error) {
      return false;
    }
  }, timeoutMs, message);
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

async function openPage(client, url) {
  const { targetId } = await client.send('Target.createTarget', { url });
  const sessionId = await client.attach(targetId);

  await waitFor(async () => {
    const result = await client.send('Runtime.evaluate', {
      expression: 'document.readyState',
      returnByValue: true,
    }, sessionId);

    return result.result.value === 'complete' || result.result.value === 'interactive';
  }, 10000, `page did not load: ${url}`);

  if (url.startsWith('chrome-extension://')) {
    await waitFor(async () => {
      const result = await client.send('Runtime.evaluate', {
        expression: 'Boolean(globalThis.chrome?.storage?.local)',
        returnByValue: true,
      }, sessionId);

      return result.result.value === true;
    }, 10000, `extension APIs did not load: ${url}`);
  }

  return sessionId;
}

async function navigateExisting(client, sessionId, url) {
  await client.send('Page.enable', {}, sessionId);
  await client.send('Page.navigate', { url }, sessionId);
  await waitForLocation(client, sessionId, url, 10000);
}

async function navigateAndWait(client, url) {
  const sessionId = await openPage(client, 'about:blank');
  await client.send('Page.enable', {}, sessionId);
  await client.send('Page.navigate', { url }, sessionId);

  let href = '';
  try {
    await waitFor(async () => {
      const result = await client.send('Runtime.evaluate', {
        expression: 'location.href',
        returnByValue: true,
      }, sessionId);
      href = result.result.value || '';

      return href.includes(url) || href.startsWith('chrome-extension://');
    }, 10000, `navigation did not complete for ${url}`);
  } catch (error) {
    const debug = await client.send('Runtime.evaluate', {
      expression: `({ href: location.href, text: document.body?.innerText?.slice(0, 500) })`,
      returnByValue: true,
    }, sessionId).catch(() => ({ result: { value: null } }));
    throw new Error(`${error.message}. Page: ${JSON.stringify(debug.result.value)}`);
  }

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

async function waitForLocation(client, sessionId, expected, timeoutMs) {
  await waitFor(async () => {
    const result = await client.send('Runtime.evaluate', {
      expression: 'location.href',
      returnByValue: true,
    }, sessionId);

    return String(result.result.value || '').includes(expected);
  }, timeoutMs, `location did not include ${expected}`);
}

async function waitForText(client, sessionId, text, timeoutMs) {
  await waitFor(async () => {
    const result = await client.send('Runtime.evaluate', {
      expression: `document.body?.innerText?.includes(${JSON.stringify(text)})`,
      returnByValue: true,
    }, sessionId);

    return result.result.value === true;
  }, timeoutMs, `page text not found: ${text}`);
}

async function waitForSelector(client, sessionId, selector, timeoutMs) {
  try {
    await waitFor(async () => {
    const result = await client.send('Runtime.evaluate', {
      expression: `Boolean(document.querySelector(${JSON.stringify(selector)}))`,
      returnByValue: true,
    }, sessionId);

    return result.result.value === true;
    }, timeoutMs, `selector not found: ${selector}`);
  } catch (error) {
    const debug = await client.send('Runtime.evaluate', {
      expression: `({ href: location.href, text: document.body?.innerText?.slice(0, 500) })`,
      returnByValue: true,
    }, sessionId).catch(() => ({ result: { value: null } }));
    throw new Error(`${error.message}. Page: ${JSON.stringify(debug.result.value)}`);
  }
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

      const { resolve, reject, timer } = this.pending.get(message.id);
      this.pending.delete(message.id);
      clearTimeout(timer);

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
      const timer = setTimeout(() => {
        if (!this.pending.has(id)) {
          return;
        }

        this.pending.delete(id);
        reject(new Error(`CDP command timed out: ${method}`));
      }, 10000);
      this.pending.set(id, { resolve, reject, timer });
    });
  }

  close() {
    this.ws.close();
  }
}

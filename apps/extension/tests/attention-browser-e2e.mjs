import { spawn } from 'node:child_process';
import { closeSync, existsSync, openSync, readFileSync } from 'node:fs';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';

const repoRoot = resolve(new URL('../../..', import.meta.url).pathname);
const platformDir = join(repoRoot, 'apps/platform');

main().then(() => {
  process.exit(0);
}).catch((error) => {
  console.error(error);
  process.exit(1);
});

async function main() {
  const workDir = await mkdtemp(join(tmpdir(), 'air-attention-browser-e2e-'));
  const dbPath = join(workDir, 'database.sqlite');
  const seedPath = join(workDir, 'seed.php');
  const seedOutputPath = join(workDir, 'seed.json');
  const fakeCameraPath = join(workDir, 'blank-camera.y4m');
  closeSync(openSync(dbPath, 'w'));

  const platformPort = await freePort();
  const platformOrigin = `http://127.0.0.1:${platformPort}`;
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
    await createBlankFakeCamera(fakeCameraPath);
    await run('php', ['artisan', 'migrate:fresh', '--force'], { cwd: platformDir, env });
    await writeFile(seedPath, seedPhp(seedOutputPath), 'utf8');
    await run('php', ['artisan', 'tinker', `--execute=require ${JSON.stringify(seedPath)};`], { cwd: platformDir, env });

    const seed = JSON.parse(await readFile(seedOutputPath, 'utf8'));

    server = spawn('php', ['artisan', 'serve', '--host=127.0.0.1', `--port=${platformPort}`], {
      cwd: platformDir,
      env,
      stdio: ['ignore', 'pipe', 'pipe'],
    });

    await waitForHttp(`${platformOrigin}/up`, 15000, 'Laravel server did not start');

    userDataDir = await mkdtemp(join(tmpdir(), 'air-attention-profile-'));
    chromium = spawn('chromium', [
      '--headless=new',
      '--no-sandbox',
      '--disable-gpu',
      '--remote-debugging-port=0',
      `--user-data-dir=${userDataDir}`,
      '--use-fake-device-for-media-stream',
      '--use-fake-ui-for-media-stream',
      `--use-file-for-fake-video-capture=${fakeCameraPath}`,
      `--unsafely-treat-insecure-origin-as-secure=${platformOrigin}`,
      `${platformOrigin}/login`,
    ], {
      stdio: ['ignore', 'pipe', 'pipe'],
    });

    let chromiumStderr = '';
    chromium.stderr.on('data', (chunk) => {
      chromiumStderr += chunk.toString();
    });

    const port = await waitForDevToolsPort(userDataDir, () => chromiumStderr);
    client = await CdpClient.connect(`http://127.0.0.1:${port}`);
    const pageTarget = await waitForPageTarget(client, `${platformOrigin}/login`);
    const sessionId = await client.attach(pageTarget.targetId);

    await waitForSelector(client, sessionId, '#username', 10000);
    await evaluateOrThrow(client, {
      expression: `
        document.getElementById('username').value = 'attention_student';
        document.getElementById('username').dispatchEvent(new Event('input', { bubbles: true }));
        document.getElementById('password').value = 'password';
        document.getElementById('password').dispatchEvent(new Event('input', { bubbles: true }));
        document.querySelector('form').requestSubmit();
      `,
    }, sessionId);

    await waitForLocation(client, sessionId, '/student/home', 10000);
    await waitForText(client, sessionId, 'Attention Focus Task', 10000);

    await waitForDb(
      env,
      `App\\Models\\Violation::query()->where('student_id', ${seed.student_id})->where('auto_generated_key', 'body-missing:task-session:${seed.task_session_id}')->exists()`,
      40000,
      'body-missing violation was not created from fake blank camera',
    );

    await waitForDb(
      env,
      `App\\Models\\TaskSession::query()->whereKey(${seed.task_session_id})->where('status', 'unfinished')->exists()`,
      10000,
      'body-missing violation did not mark the active task unfinished',
    );

    console.log('attention browser e2e passed');
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

    if (userDataDir) {
      rmRetry(userDataDir).catch(() => {});
    }
    rmRetry(workDir).catch(() => {});
  }
}

function seedPhp(outputPath) {
  return `<?php

use App\\Enums\\UserRole;
use App\\Models\\RuleDefinition;
use App\\Models\\Student;
use App\\Models\\StudentSetting;
use App\\Models\\TaskSession;
use App\\Models\\TaskTemplate;
use App\\Models\\User;

$mentor = User::create([
    'username' => 'attention_mentor',
    'name' => 'Attention Mentor',
    'email' => 'attention-mentor@example.test',
    'role' => UserRole::Admin,
    'is_active' => true,
    'password' => 'password',
]);

$studentUser = User::create([
    'username' => 'attention_student',
    'name' => 'Attention Student',
    'email' => 'attention-student@example.test',
    'role' => UserRole::Student,
    'is_active' => true,
    'password' => 'password',
]);

$student = Student::create([
    'user_id' => $studentUser->id,
    'display_name' => 'Attention Student',
    'status' => 'active',
    'notes' => null,
]);

StudentSetting::create([
    'student_id' => $student->id,
    'can_manage_own_schedule' => true,
    'can_use_ad_hoc_timer' => true,
    'push_up_counter' => 0,
    'look_away_event_count' => 0,
    'look_away_event_threshold' => 3,
    'preferred_timezone' => 'UTC',
]);

$rule = RuleDefinition::create([
    'title' => 'Left camera view',
    'description' => 'Student left the camera view during the active task.',
    'scope' => 'global',
    'student_id' => null,
    'default_penalty_units' => 0,
    'is_active' => true,
    'created_by_user_id' => $mentor->id,
]);

$taskTemplate = TaskTemplate::create([
    'title' => 'Attention Focus Task',
    'summary' => null,
    'instructions' => 'Stay visible.',
    'default_duration_minutes' => 30,
    'requires_internet' => false,
    'created_by_user_id' => $mentor->id,
]);

$taskSession = TaskSession::create([
    'student_id' => $student->id,
    'task_template_id' => $taskTemplate->id,
    'status' => 'active',
    'task_title_snapshot' => 'Attention Focus Task',
    'planned_duration_minutes' => 30,
    'started_at' => now()->subMinutes(2),
    'duration_seconds' => 120,
    'started_by_user_id' => $studentUser->id,
]);

file_put_contents(${JSON.stringify(outputPath)}, json_encode([
    'student_id' => $student->id,
    'student_user_id' => $studentUser->id,
    'rule_id' => $rule->id,
    'task_session_id' => $taskSession->id,
], JSON_THROW_ON_ERROR));
`;
}

async function createBlankFakeCamera(path) {
  await run('ffmpeg', [
    '-hide_banner',
    '-loglevel',
    'error',
    '-f',
    'lavfi',
    '-i',
    'color=c=black:s=640x480:r=10',
    '-t',
    '30',
    '-pix_fmt',
    'yuv420p',
    '-y',
    path,
  ], { quiet: true });
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

async function freePort() {
  const { createServer } = await import('node:http');
  const server = createServer((_req, res) => res.end('ok'));
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

async function waitForPageTarget(client, urlPart) {
  let target = null;

  await waitFor(async () => {
    const targets = await client.send('Target.getTargets');
    target = targets.targetInfos.find((info) => info.type === 'page' && info.url.includes(urlPart));

    return Boolean(target);
  }, 10000, `page target did not appear: ${urlPart}`);

  return target;
}

async function waitForSelector(client, sessionId, selector, timeoutMs) {
  await waitFor(async () => {
    const result = await client.send('Runtime.evaluate', {
      expression: `Boolean(document.querySelector(${JSON.stringify(selector)}))`,
      returnByValue: true,
    }, sessionId);

    return result.result.value === true;
  }, timeoutMs, `selector not found: ${selector}`);
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

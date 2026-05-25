const statusEl = document.getElementById('status');
const startBgBtn = document.getElementById('startbg');
const recalibrateBtn = document.getElementById('recalibrate');
const permVideo = document.getElementById('permVideo');
const DEFAULT_SERVER_ORIGIN = 'https://192.168.11.228';
const ALLOWED_SERVER_ORIGINS = new Set([DEFAULT_SERVER_ORIGIN]);

async function detectActiveOrigin() {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  const rawUrl = tab?.url || '';

  if (!rawUrl || rawUrl.startsWith('chrome://') || rawUrl.startsWith('vivaldi://') || rawUrl.startsWith('chrome-extension://')) {
    return null;
  }

  try {
    const origin = new URL(rawUrl).origin;
    return ALLOWED_SERVER_ORIGINS.has(origin) ? origin : null;
  } catch (_error) {
    return null;
  }
}

async function ensureServerOrigin() {
  const stored = await chrome.storage.local.get(['server_origin', 'platformUrl']);
  const existingOrigin = stored.server_origin || stored.platformUrl;

  if (ALLOWED_SERVER_ORIGINS.has(existingOrigin)) {
    await chrome.storage.local.set({ server_origin: existingOrigin });
    return existingOrigin;
  }

  await chrome.storage.local.set({ server_origin: DEFAULT_SERVER_ORIGIN });
  return DEFAULT_SERVER_ORIGIN;
}

function renderStatus(lastState) {
  if (!lastState) {
    statusEl.textContent = 'Status: Waiting for start...';
    return;
  }

  const lines = [
    `Status: ${lastState.status ?? 'Unknown'}`,
    `Mode: ${lastState.mode ?? '-'}`,
    `Face visible: ${lastState.faceVisible ? 'yes' : 'no'}`,
    `Baseline ready: ${lastState.baselineReady ? 'yes' : 'no'}`,
    `Eye tracking ready: ${lastState.eyeTrackingReady ? 'yes' : 'no'}`,
    `Score: ${lastState.score ?? '-'}`,
    `Head score: ${lastState.headScore ?? '-'}`,
    `Eye score: ${lastState.eyeScore ?? '-'}`,
    `Left eye score: ${lastState.leftEyeScore ?? '-'}`,
    `Right eye score: ${lastState.rightEyeScore ?? '-'}`,
    `Left eye open: ${lastState.leftEyeConfidence == null ? '-' : (lastState.leftEyeConfidence ? 'yes' : 'no')}`,
    `Right eye open: ${lastState.rightEyeConfidence == null ? '-' : (lastState.rightEyeConfidence ? 'yes' : 'no')}`,
    `Away duration: ${Number(lastState.awaySeconds || 0).toFixed(1)}s`,
    `Total look-aways: ${lastState.awayEventsLocal ?? 0}`,
  ];

  if (lastState.error) {
    lines.push(`Error: ${lastState.error}`);
  }

  statusEl.textContent = lines.join('\n');
}

function cameraErrorMessage(error) {
  const name = error?.name || 'CameraError';
  const message = error?.message || String(error || 'unknown camera error');

  return `${name}: ${message}`;
}

async function refresh() {
  const activeOrigin = await detectActiveOrigin();

  if (activeOrigin) {
    await chrome.storage.local.set({ server_origin: activeOrigin });
  } else {
    await ensureServerOrigin();
  }

  const {
    last_state: lastState,
    server_origin: serverOrigin,
    attention_upload_state: uploadState,
  } = await chrome.storage.local.get([
    'last_state',
    'server_origin',
    'attention_upload_state',
  ]);

  renderStatus(lastState);

  if (serverOrigin) {
    statusEl.textContent += `\nServer: ${serverOrigin}`;
  }

  if (uploadState) {
    statusEl.textContent += `\nUpload: ${uploadState.status || 'unknown'} (${uploadState.pending || 0} pending)`;

    if (uploadState.lastError) {
      statusEl.textContent += `\nUpload error: ${uploadState.lastError}`;
    }
  }
}

async function requestCameraPermission() {
  if (typeof navigator.mediaDevices?.getUserMedia !== 'function') {
    throw new Error('Camera API is not available to this extension page.');
  }

  const stream = await navigator.mediaDevices.getUserMedia({
    video: {
      width: { ideal: 640 },
      height: { ideal: 480 },
      frameRate: { ideal: 15, max: 30 },
    },
  });
  permVideo.srcObject = stream;
  await permVideo.play();
  stream.getTracks().forEach((track) => track.stop());
}

startBgBtn.addEventListener('click', async () => {
  try {
    const activeOrigin = await detectActiveOrigin();

    if (activeOrigin) {
      await chrome.storage.local.set({ server_origin: activeOrigin });
    } else {
      await ensureServerOrigin();
    }

    await requestCameraPermission();
    chrome.runtime.sendMessage({ type: 'START_BG' });
    statusEl.textContent = 'Status: Starting detection...';
  } catch (error) {
    const message = cameraErrorMessage(error);
    await chrome.storage.local.set({
      last_state: {
        status: 'Camera permission failed',
        mode: 'error',
        isLooking: false,
        faceVisible: false,
        baselineReady: false,
        eyeTrackingReady: false,
        score: null,
        headScore: null,
        eyeScore: null,
        leftEyeScore: null,
        rightEyeScore: null,
        awaySeconds: 0,
        awayEventsLocal: 0,
        error: message,
      },
    });
    statusEl.textContent = `Status: Camera error\nError: ${message}`;
  }
});

recalibrateBtn.addEventListener('click', () => {
  chrome.runtime.sendMessage({ type: 'RECALIBRATE' });
  statusEl.textContent = 'Status: Recalibrating...';
});

setInterval(refresh, 500);
refresh();

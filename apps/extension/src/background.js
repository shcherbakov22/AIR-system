const DEFAULT_POLICY = {
  mode: 'blacklist',
  default_unblock_scope: 'domain_tree',
  log_full_url: true,
  active_task: null,
  rules: [],
  updated_at: null,
};

const POLICY_REFRESH_ALARM = 'policy_refresh';
const EXTENSION_HEARTBEAT_ALARM = 'extension_heartbeat';
const EXTENSION_HEARTBEAT_IMMEDIATE_ALARM = 'extension_heartbeat_immediate';
const ATTENTION_RETRY_ALARM = 'attention_retry_uploads';
const ATTENTION_WATCHDOG_ALARM = 'attention_watchdog';
const POLICY_REFRESH_MINUTES = 5;
const EXTENSION_HEARTBEAT_MINUTES = 1;
const POLICY_REFRESH_MAX_AGE_MS = POLICY_REFRESH_MINUTES * 60 * 1000;
const IMMEDIATE_HEARTBEAT_DELAY_MS = 15_000;
const ATTENTION_RETRY_PERIOD_MINUTES = 0.5;
const ATTENTION_WATCHDOG_PERIOD_MINUTES = 0.5;
const ATTENTION_OFFSCREEN_STALE_MS = 20 * 1000;
const ATTENTION_EVENT_QUEUE_KEY = 'attention_event_queue';
const ATTENTION_UPLOAD_STATE_KEY = 'attention_upload_state';
const EXTENSION_STATUS_REPORT_KEY = 'last_extension_status_report_key';
const EXTENSION_STATUS_REPORT_AT_KEY = 'last_extension_status_report_at';
const ATTENTION_MAX_QUEUE_LENGTH = 200;
const ATTENTION_MAX_EVENT_AGE_MS = 24 * 60 * 60 * 1000;
const ATTENTION_DEFAULT_SERVER_ORIGIN = 'https://192.168.11.228';
const ATTENTION_ALLOWED_SERVER_ORIGINS = new Set([ATTENTION_DEFAULT_SERVER_ORIGIN]);
const RESTRICTED_NON_WEB_SCHEMES = new Set(['file:', 'ftp:', 'data:', 'blob:', 'filesystem:']);
const BROWSER_INTERNAL_SCHEMES = new Set(['about:', 'chrome:', 'chrome-extension:', 'devtools:', 'edge:', 'vivaldi:']);

const MULTI_LABEL_PUBLIC_SUFFIXES = new Set([
  'ac.uk',
  'co.jp',
  'co.uk',
  'com.au',
  'com.br',
  'com.mx',
  'com.tr',
  'com.tw',
  'com.ua',
  'edu.au',
  'gov.uk',
  'net.au',
  'org.au',
  'org.uk',
]);

const recentVisits = new Map();
let networkRuleUpdate = Promise.resolve();
let flushingAttentionQueue = false;

chrome.runtime.onInstalled.addListener((details) => {
  initializeBackground(`installed:${details.reason || 'unknown'}`, {
    install_reason: details.reason || null,
    previous_version: details.previousVersion || null,
  });
});

chrome.runtime.onStartup.addListener(() => {
  initializeBackground('browser_startup');
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === POLICY_REFRESH_ALARM) {
    syncPolicy().catch((error) => {
      reportExtensionStatus('policy_sync_failed', { error: error.message }).catch(() => {});
    });
    return;
  }

  if (alarm.name === EXTENSION_HEARTBEAT_ALARM || alarm.name === EXTENSION_HEARTBEAT_IMMEDIATE_ALARM) {
    heartbeatExtension().catch((error) => {
      reportExtensionStatus('heartbeat_failed', { error: error.message }).catch(() => {});
    });
    return;
  }

  if (alarm.name === ATTENTION_RETRY_ALARM) {
    flushQueuedAttentionEvents().catch(() => {});
    return;
  }

  if (alarm.name === ATTENTION_WATCHDOG_ALARM) {
    checkAttentionOffscreenWatchdog().catch(() => {});
  }
});

chrome.webNavigation.onBeforeNavigate.addListener(async (details) => {
  if (details.frameId !== 0) {
    return;
  }

  await handleNavigation(details.tabId, details.url);
});

chrome.tabs.onUpdated.addListener(async (tabId, changeInfo, tab) => {
  if (changeInfo.status !== 'complete' || !tab.url) {
    return;
  }

  queueImmediateHeartbeat();
  syncAttentionServerOriginFromTab(tab).catch(() => {});
  await logVisit(tab.url, tab.title || null);
});

chrome.tabs.onActivated.addListener((activeInfo) => {
  queueImmediateHeartbeat();
  chrome.tabs.get(activeInfo.tabId)
    .then((tab) => syncAttentionServerOriginFromTab(tab))
    .catch(() => {});
  Promise.all([getPolicy(), getSettings()])
    .then(([policy, settings]) => enforceOpenTabs(policy, settings.platformUrl))
    .catch(() => {});
});

chrome.runtime.onMessage.addListener((message, _sender, sendResponse) => {
  if (message?.type === 'request_access') {
    requestAccess(message.url, message.reason || null)
      .then((response) => sendResponse({ ok: true, response }))
      .catch((error) => sendResponse({ ok: false, error: error.message }));

    return true;
  }

  if (message?.type === 'sync_policy') {
    syncPolicy()
      .then((policy) => sendResponse({ ok: true, policy }))
      .catch((error) => {
        reportExtensionStatus('policy_sync_failed', { error: error.message }).catch(() => {});
        sendResponse({ ok: false, error: error.message });
      });

    return true;
  }

  if (message?.type === 'content_script_heartbeat') {
    contentScriptHeartbeat(message.url || '', message.title || null)
      .then((response) => sendResponse({ ok: true, response }))
      .catch((error) => sendResponse({ ok: false, error: error.message }));

    return true;
  }

  if (message?.type === 'evaluate_url_after_sync') {
    syncPolicy()
      .then((policy) => sendResponse({
        ok: true,
        policy,
        evaluation: evaluateUrl(policy, String(message.url || '')),
      }))
      .catch((error) => {
        reportExtensionStatus('policy_sync_failed', { error: error.message }).catch(() => {});
        sendResponse({ ok: false, error: error.message });
      });

    return true;
  }

  if (message?.type === 'get_status') {
    extensionStatus()
      .then((status) => sendResponse({ ok: true, status }))
      .catch((error) => sendResponse({ ok: false, error: error.message }));

    return true;
  }

  if (message?.type === 'START_BG') {
    bootstrapAttentionDetection().catch(() => {});
    return false;
  }

  if (message?.type === 'RECALIBRATE') {
    ensureAttentionOffscreen()
      .then(() => chrome.runtime.sendMessage({ type: 'RECALIBRATE_OFFSCREEN' }))
      .catch(() => {});
    return false;
  }

  if (message?.type === 'LOOK_STATE') {
    chrome.storage.local.set({
      last_state: message,
      last_look_state_received_at: Date.now(),
    });
    return false;
  }

  if (message?.type === 'LOOK_AWAY_EVENT') {
    queueLookAwayEvent(message)
      .then(() => flushQueuedAttentionEvents())
      .catch(() => {});
    return false;
  }

  return false;
});

initializeBackground('service_worker_start');

function initializeBackground(reason = 'startup', extraStatus = {}) {
  schedulePolicyRefreshAlarm();
  scheduleExtensionHeartbeatAlarm();
  queueImmediateHeartbeat();
  syncPolicy().catch((error) => {
    reportExtensionStatus('policy_sync_failed', { error: error.message }).catch(() => {});
  });
  reportExtensionStatus(reason, extraStatus).catch(() => {});
  bootstrapAttentionDetection().catch(() => {});
}

function schedulePolicyRefreshAlarm() {
  chrome.alarms.create(POLICY_REFRESH_ALARM, {
    delayInMinutes: POLICY_REFRESH_MINUTES,
    periodInMinutes: POLICY_REFRESH_MINUTES,
  });
}

function scheduleExtensionHeartbeatAlarm() {
  chrome.alarms.create(EXTENSION_HEARTBEAT_ALARM, {
    delayInMinutes: EXTENSION_HEARTBEAT_MINUTES,
    periodInMinutes: EXTENSION_HEARTBEAT_MINUTES,
  });
}

async function ensureAttentionAlarms() {
  await chrome.alarms.create(ATTENTION_RETRY_ALARM, {
    periodInMinutes: ATTENTION_RETRY_PERIOD_MINUTES,
  });
  await chrome.alarms.create(ATTENTION_WATCHDOG_ALARM, {
    periodInMinutes: ATTENTION_WATCHDOG_PERIOD_MINUTES,
  });
}

function queueImmediateHeartbeat() {
  chrome.alarms.create(EXTENSION_HEARTBEAT_IMMEDIATE_ALARM, {
    when: Date.now() + IMMEDIATE_HEARTBEAT_DELAY_MS,
  });
}

async function handleNavigation(tabId, url) {
  const settings = await getSettings();

  if (shouldIgnoreUrl(url, settings.platformUrl)) {
    return;
  }

  if (isRestrictedNonWebUrl(url)) {
    const evaluation = restrictedUrlEvaluation(url);
    const restrictedUrl = restrictedUrlReference(url);

    await logVisit(restrictedUrl, null, evaluation, {
      source: 'explicit_navigation',
      restricted_scheme: schemeFromUrl(url),
    });
    await blockTab(tabId, restrictedUrl, evaluation, { mode: 'restricted' });
    return;
  }

  if (!isHttpUrl(url)) {
    return;
  }

  const policy = await getPolicy();
  const evaluation = evaluateUrl(policy, url);

  await logVisit(url, null, evaluation, { source: 'explicit_navigation' });

  if (evaluation.allowed) {
    return;
  }

  await blockTab(tabId, url, evaluation, policy);
}

async function syncPolicy() {
  const settings = await getSettings();

  if (!settings.platformUrl || !settings.deviceToken) {
    return DEFAULT_POLICY;
  }

  const payload = await fetchRemotePolicy(settings);
  const policy = payload.policy || DEFAULT_POLICY;

  await applyNetworkRules(policy, settings.platformUrl);
  await enforceOpenTabs(policy, settings.platformUrl);

  await chrome.storage.local.set({
    policy,
    lastPolicySyncAt: new Date().toISOString(),
    lastExtensionHeartbeatAt: new Date().toISOString(),
  });
  await reportExtensionStatus('policy_sync_ok', {
    policy_updated_at: policy.updated_at || null,
  }).catch(() => {});

  return policy;
}

async function heartbeatExtension() {
  const settings = await getSettings();

  if (!settings.platformUrl || !settings.deviceToken) {
    return DEFAULT_POLICY;
  }

  const payload = await fetchRemotePolicy(settings);
  const remotePolicy = payload.policy || DEFAULT_POLICY;
  const stored = await chrome.storage.local.get(['policy']);
  const currentPolicy = stored.policy || DEFAULT_POLICY;
  const policyChanged = JSON.stringify(currentPolicy) !== JSON.stringify(remotePolicy);
  const heartbeatAt = new Date().toISOString();
  const nextState = {
    lastExtensionHeartbeatAt: heartbeatAt,
  };

  if (policyChanged) {
    await applyNetworkRules(remotePolicy, settings.platformUrl);
    await enforceOpenTabs(remotePolicy, settings.platformUrl);
    nextState.policy = remotePolicy;
    nextState.lastPolicySyncAt = heartbeatAt;
  }

  await chrome.storage.local.set(nextState);
  await reportExtensionStatus(policyChanged ? 'heartbeat_policy_changed' : 'heartbeat_ok', {
    policy_changed: policyChanged,
    policy_updated_at: remotePolicy.updated_at || null,
  }).catch(() => {});
  return policyChanged ? remotePolicy : currentPolicy;
}

async function fetchRemotePolicy(settings) {
  const response = await fetch(`${settings.platformUrl}/api/companion/browser/policy`, {
    cache: 'no-store',
    headers: authHeaders(settings.deviceToken),
  });

  if (!response.ok) {
    throw new Error(`Policy sync failed with ${response.status}`);
  }

  return response.json();
}

async function enforceOpenTabs(policy, platformUrl = '') {
  const tabs = await chrome.tabs.query({});

  await Promise.all(tabs.map(async (tab) => {
    if (!tab.id || shouldIgnoreUrl(tab.url, platformUrl)) {
      return;
    }

    if (isRestrictedNonWebUrl(tab.url)) {
      const evaluation = restrictedUrlEvaluation(tab.url);
      const restrictedUrl = restrictedUrlReference(tab.url);

      await logVisit(restrictedUrl, tab.title || null, evaluation, {
        source: 'policy_enforcement',
        restricted_scheme: schemeFromUrl(tab.url),
      });
      await blockTab(tab.id, restrictedUrl, evaluation, { mode: 'restricted' }).catch(() => {});
      return;
    }

    if (!isHttpUrl(tab.url)) {
      return;
    }

    const evaluation = evaluateUrl(policy, tab.url);

    if (evaluation.allowed) {
      return;
    }

    await logVisit(tab.url, tab.title || null, evaluation, { source: 'policy_enforcement' });
    await blockTab(tab.id, tab.url, evaluation, policy).catch(() => {});
  }));
}

async function blockTab(tabId, url, evaluation, policy) {
  const blockedUrl = chrome.runtime.getURL(
    `src/blocked.html?url=${encodeURIComponent(url)}&host=${encodeURIComponent(evaluation.host)}&domain=${encodeURIComponent(evaluation.registrableDomain)}&mode=${encodeURIComponent(policy?.mode || 'blacklist')}`,
  );

  await chrome.tabs.update(tabId, { url: blockedUrl });
}

async function applyNetworkRules(policy, platformUrl = '') {
  networkRuleUpdate = networkRuleUpdate
    .catch(() => {})
    .then(() => applyNetworkRulesNow(policy, platformUrl));

  return networkRuleUpdate;
}

async function applyNetworkRulesNow(policy, platformUrl = '') {
  const existing = await chrome.declarativeNetRequest.getDynamicRules();
  const managedRuleIds = Array.from({ length: 1000 }, (_value, index) => index + 1);
  const removeRuleIds = [...new Set([
    ...existing.map((rule) => rule.id),
    ...managedRuleIds,
  ])];
  const addRules = buildNetworkRules(policy, platformUrl);

  if (removeRuleIds.length > 0) {
    await chrome.declarativeNetRequest.updateDynamicRules({ removeRuleIds });
  }

  if (addRules.length > 0) {
    await chrome.declarativeNetRequest.updateDynamicRules({ addRules });
  }
}

function buildNetworkRules(policy, platformUrl = '') {
  const mode = policy?.mode === 'whitelist' ? 'whitelist' : 'blacklist';
  const rules = Array.isArray(policy?.rules) ? policy.rules : [];
  const redirectUrl = chrome.runtime.getURL('src/blocked.html?url=\\0');
  const platformHost = hostFromUrl(platformUrl);

  if (mode === 'whitelist') {
    const blockAllCondition = {
      regexFilter: '^https?://.*',
      resourceTypes: ['main_frame'],
    };

    if (platformHost) {
      blockAllCondition.excludedRequestDomains = [platformHost];
    }

    const allowRules = rules
      .filter((rule) => rule.effect === 'allow' && rule.match_type === 'domain_tree')
      .map((rule, index) => ({
        id: index + 10,
        priority: 2,
        action: { type: 'allow' },
        condition: {
          requestDomains: [rule.value],
          resourceTypes: ['main_frame'],
        },
      }));
    const platformAllowRule = platformHost ? [{
      id: 2,
      priority: 3,
      action: { type: 'allow' },
      condition: {
        requestDomains: [platformHost],
        resourceTypes: ['main_frame', 'xmlhttprequest'],
      },
    }] : [];

    return [
      {
        id: 1,
        priority: 1,
        action: {
          type: 'redirect',
          redirect: { regexSubstitution: redirectUrl },
        },
        condition: blockAllCondition,
      },
      ...platformAllowRule,
      ...allowRules,
    ];
  }

  return rules
    .filter((rule) => rule.effect === 'block' && rule.match_type === 'domain_tree')
    .map((rule, index) => ({
      id: index + 10,
      priority: 1,
      action: {
        type: 'redirect',
        redirect: { regexSubstitution: redirectUrl },
      },
      condition: {
        regexFilter: '^https?://.*',
        requestDomains: [rule.value],
        resourceTypes: ['main_frame'],
      },
    }));
}

async function getPolicy() {
  const stored = await chrome.storage.local.get(['policy', 'lastPolicySyncAt']);
  const lastSync = stored.lastPolicySyncAt ? Date.parse(stored.lastPolicySyncAt) : 0;

  if (!stored.policy || Date.now() - lastSync > POLICY_REFRESH_MAX_AGE_MS) {
    try {
      return await syncPolicy();
    } catch (_error) {
      return stored.policy || DEFAULT_POLICY;
    }
  }

  return stored.policy;
}

async function logVisit(url, pageTitle, knownEvaluation = null, extraMeta = {}) {
  if (!url || isBlockedPage(url) || isBrowserInternalUrl(url)) {
    return;
  }

  if (!isHttpUrl(url) && !isRestrictedNonWebUrl(url)) {
    return;
  }

  const key = `${extraMeta.source || 'chrome_extension'}:${url}:${pageTitle || ''}`;
  const lastSeen = recentVisits.get(key) || 0;

  if (Date.now() - lastSeen < 10_000) {
    return;
  }

  recentVisits.set(key, Date.now());

  const settings = await getSettings();

  if (!settings.platformUrl || !settings.deviceToken) {
    return;
  }

  if (isPlatformUrl(url, settings.platformUrl) && extraMeta.source !== 'content_script') {
    return;
  }

  const policy = knownEvaluation ? null : await getPolicy();
  const evaluation = knownEvaluation || (
    isRestrictedNonWebUrl(url) ? restrictedUrlEvaluation(url) : evaluateUrl(policy, url)
  );

  await fetch(`${settings.platformUrl}/api/companion/browser/visits`, {
    method: 'POST',
    headers: {
      ...authHeaders(settings.deviceToken),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      url,
      page_title: pageTitle,
      meta: {
        source: 'chrome_extension',
        decision: evaluation.allowed ? 'allowed' : 'blocked',
        ...extraMeta,
      },
    }),
  }).catch(() => {});
}

async function postActivity(eventType, payload = {}, fields = {}) {
  const settings = await getSettings();

  if (!settings.platformUrl || !settings.deviceToken) {
    return null;
  }

  const response = await fetch(`${settings.platformUrl}/api/companion/activity`, {
    method: 'POST',
    headers: {
      ...authHeaders(settings.deviceToken),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      event_type: eventType,
      ...fields,
      payload,
    }),
  });

  if (!response.ok) {
    throw new Error(`Activity report failed with ${response.status}`);
  }

  return response.json();
}

async function reportExtensionStatus(reason, extra = {}) {
  const status = await extensionStatus();
  const payload = {
    ...status,
    reason,
    status: reason,
    ...extra,
  };
  const reportKey = JSON.stringify({
    reason,
    configured: payload.configured,
    version: payload.version,
    policyMode: payload.policyMode,
    activeTask: payload.activeTask,
    platformUrl: payload.platformUrl,
    error: payload.error || null,
    install_reason: payload.install_reason || null,
    previous_version: payload.previous_version || null,
    policy_changed: payload.policy_changed ?? null,
  });
  const stored = await chrome.storage.local.get([EXTENSION_STATUS_REPORT_KEY]);

  if (stored[EXTENSION_STATUS_REPORT_KEY] === reportKey) {
    return null;
  }

  await chrome.storage.local.set({
    [EXTENSION_STATUS_REPORT_KEY]: reportKey,
    [EXTENSION_STATUS_REPORT_AT_KEY]: new Date().toISOString(),
  });

  return postActivity('extension_status', payload);
}

async function contentScriptHeartbeat(url, pageTitle) {
  await logVisit(String(url || ''), pageTitle ? String(pageTitle) : null, null, {
    source: 'content_script',
  });
}

async function requestAccess(url, reason) {
  const settings = await getSettings();

  if (!settings.platformUrl || !settings.deviceToken) {
    throw new Error('Open extension options and set the platform URL and device token first.');
  }

  const response = await fetch(`${settings.platformUrl}/api/companion/browser/access-requests`, {
    method: 'POST',
    headers: {
      ...authHeaders(settings.deviceToken),
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ url, reason }),
  });

  if (!response.ok) {
    throw new Error(`Access request failed with ${response.status}`);
  }

  return response.json();
}

async function getSettings() {
  const [managed, stored] = await Promise.all([
    readManagedSettings(),
    chrome.storage.local.get(['platformUrl', 'deviceToken']),
  ]);
  const platformUrl = String(managed.platformUrl || stored.platformUrl || 'https://192.168.11.228').replace(/\/+$/, '');

  return {
    platformUrl,
    deviceToken: managed.deviceToken || stored.deviceToken || '',
  };
}

async function readManagedSettings() {
  if (!chrome.storage.managed?.get) {
    return {};
  }

  try {
    const managed = await chrome.storage.managed.get(['platformUrl', 'deviceToken']);

    return {
      platformUrl: typeof managed.platformUrl === 'string' ? managed.platformUrl.trim() : '',
      deviceToken: typeof managed.deviceToken === 'string' ? managed.deviceToken.trim() : '',
    };
  } catch (_error) {
    return {};
  }
}

async function extensionStatus() {
  const [settings, stored, tabs] = await Promise.all([
    getSettings(),
    chrome.storage.local.get(['policy', 'lastPolicySyncAt', 'lastExtensionHeartbeatAt']),
    chrome.tabs.query({ active: true, currentWindow: true }),
  ]);
  const policy = stored.policy || DEFAULT_POLICY;
  const activeTabUrl = tabs[0]?.url || '';
  const evaluation = shouldIgnoreUrl(activeTabUrl, settings.platformUrl)
    ? null
    : (isRestrictedNonWebUrl(activeTabUrl)
        ? restrictedUrlEvaluation(activeTabUrl)
        : (isHttpUrl(activeTabUrl) ? evaluateUrl(policy, activeTabUrl) : null));

  return {
    configured: Boolean(settings.platformUrl && settings.deviceToken),
    version: chrome.runtime.getManifest().version,
    platformUrl: settings.platformUrl,
    currentSite: activeTabUrl ? displayHostForUrl(activeTabUrl) : '',
    currentSiteAllowed: evaluation ? evaluation.allowed : null,
    policyMode: policy.mode === 'whitelist' ? 'whitelist' : 'blacklist',
    activeTask: policy.active_task?.title || policy.active_task?.name || null,
    lastPolicySyncAt: stored.lastPolicySyncAt || null,
    lastExtensionHeartbeatAt: stored.lastExtensionHeartbeatAt || null,
  };
}

function attentionOriginFromUrl(rawUrl) {
  if (!rawUrl || rawUrl.startsWith('chrome://') || rawUrl.startsWith('vivaldi://') || rawUrl.startsWith('chrome-extension://')) {
    return null;
  }

  try {
    const origin = new URL(rawUrl).origin;
    return ATTENTION_ALLOWED_SERVER_ORIGINS.has(origin) ? origin : null;
  } catch (_error) {
    return null;
  }
}

async function ensureAttentionServerOrigin() {
  const stored = await chrome.storage.local.get(['server_origin', 'platformUrl']);
  const existingOrigin = stored.server_origin || stored.platformUrl;

  if (ATTENTION_ALLOWED_SERVER_ORIGINS.has(existingOrigin)) {
    await chrome.storage.local.set({ server_origin: existingOrigin });
    return existingOrigin;
  }

  await chrome.storage.local.set({ server_origin: ATTENTION_DEFAULT_SERVER_ORIGIN });
  return ATTENTION_DEFAULT_SERVER_ORIGIN;
}

async function syncAttentionServerOriginFromTab(tab) {
  const origin = attentionOriginFromUrl(tab?.url || '');

  if (!origin) {
    return null;
  }

  const stored = await chrome.storage.local.get(['server_origin']);

  if (stored.server_origin !== origin) {
    await chrome.storage.local.set({ server_origin: origin });
  }

  return origin;
}

async function syncAttentionServerOriginFromActiveTab() {
  const [tab] = await chrome.tabs.query({ active: true, lastFocusedWindow: true });

  return (await syncAttentionServerOriginFromTab(tab)) || ensureAttentionServerOrigin();
}

async function ensureAttentionOffscreen() {
  const hasDocument = chrome.offscreen?.hasDocument ? await chrome.offscreen.hasDocument() : false;

  if (hasDocument) {
    return;
  }

  await chrome.offscreen.createDocument({
    url: 'src/offscreen.html',
    reasons: ['USER_MEDIA'],
    justification: 'Run webcam look detection locally',
  });
}

async function restartAttentionOffscreen(reason) {
  try {
    if (chrome.offscreen?.hasDocument && await chrome.offscreen.hasDocument()) {
      await chrome.offscreen.closeDocument();
    }
  } catch (_error) {
    // A failed close should not prevent a fresh startup attempt.
  }

  await chrome.storage.local.set({
    last_state: {
      status: `Restarting attention tracker${reason ? `: ${reason}` : ''}`,
      mode: 'starting',
      isLooking: true,
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
    },
  });
  await ensureAttentionOffscreen();
}

async function bootstrapAttentionDetection() {
  await chrome.storage.local.set({
    last_state: {
      status: 'Starting detection...',
      mode: 'starting',
      isLooking: true,
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
    },
  });
  await syncAttentionServerOriginFromActiveTab().catch(() => ensureAttentionServerOrigin());
  await ensureAttentionAlarms();
  await ensureAttentionOffscreen();
}

function buildLookAwayEvent(message) {
  return {
    id: message.id || `${Date.now()}-${Math.random().toString(16).slice(2)}`,
    createdAt: Date.now(),
    attempts: message.attempts || 0,
    event_type: 'look_away',
    occurred_at: message.occurredAt || new Date().toISOString(),
    payload: {
      score: message.score ?? null,
      away_seconds: message.awaySeconds ?? null,
      reason: message.reason ?? 'look_away',
    },
  };
}

async function updateAttentionUploadState(patch) {
  await chrome.storage.local.set({
    [ATTENTION_UPLOAD_STATE_KEY]: {
      updatedAt: Date.now(),
      ...patch,
    },
  });
}

async function readAttentionQueue() {
  const stored = await chrome.storage.local.get([ATTENTION_EVENT_QUEUE_KEY]);

  return Array.isArray(stored[ATTENTION_EVENT_QUEUE_KEY]) ? stored[ATTENTION_EVENT_QUEUE_KEY] : [];
}

async function writeAttentionQueue(queue) {
  const now = Date.now();
  const kept = queue
    .filter((event) => event?.createdAt && (now - event.createdAt) <= ATTENTION_MAX_EVENT_AGE_MS)
    .slice(-ATTENTION_MAX_QUEUE_LENGTH);

  await chrome.storage.local.set({ [ATTENTION_EVENT_QUEUE_KEY]: kept });
  return kept;
}

async function queueLookAwayEvent(message) {
  const queue = await readAttentionQueue();
  queue.push(buildLookAwayEvent(message));
  const kept = await writeAttentionQueue(queue);

  await updateAttentionUploadState({
    status: 'queued',
    pending: kept.length,
    lastError: null,
  });
}

async function postLookAwayEvent(event) {
  const stored = await chrome.storage.local.get(['server_origin']);

  if (!ATTENTION_ALLOWED_SERVER_ORIGINS.has(stored.server_origin)) {
    await ensureAttentionServerOrigin();
    throw new Error('Missing AIR server origin');
  }

  const response = await fetch(`${stored.server_origin}/student/attention/events`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      event_type: event.event_type,
      occurred_at: event.occurred_at,
      payload: {
        ...event.payload,
        client_event_id: event.id,
      },
    }),
  });

  if (!response.ok) {
    const body = await response.text();

    if ([401, 403, 419].includes(response.status)) {
      await updateAttentionUploadState({
        status: 'auth_error',
        pending: (await readAttentionQueue()).length,
        lastError: `Login expired or unauthorized (${response.status})`,
      });
    }

    throw new Error(`Attention event upload failed (${response.status}): ${body}`);
  }
}

async function flushQueuedAttentionEvents() {
  if (flushingAttentionQueue) {
    return;
  }

  flushingAttentionQueue = true;

  try {
    let queue = await writeAttentionQueue(await readAttentionQueue());

    if (queue.length === 0) {
      await updateAttentionUploadState({ status: 'idle', pending: 0, lastError: null });
      return;
    }

    const remaining = [];

    for (const event of queue) {
      try {
        await postLookAwayEvent(event);
      } catch (error) {
        remaining.push({
          ...event,
          attempts: (event.attempts || 0) + 1,
          lastError: error?.message || String(error),
        });
        remaining.push(...queue.slice(queue.indexOf(event) + 1));
        break;
      }
    }

    queue = await writeAttentionQueue(remaining);
    await updateAttentionUploadState({
      status: queue.length ? 'retrying' : 'idle',
      pending: queue.length,
      lastError: queue[0]?.lastError || null,
    });
  } finally {
    flushingAttentionQueue = false;
  }
}

async function checkAttentionOffscreenWatchdog() {
  const stored = await chrome.storage.local.get(['last_look_state_received_at']);
  const lastSeen = Number(stored.last_look_state_received_at || 0);

  if (!lastSeen) {
    await restartAttentionOffscreen('watchdog missing state');
    return;
  }

  if (Date.now() - lastSeen > ATTENTION_OFFSCREEN_STALE_MS) {
    await restartAttentionOffscreen('watchdog stale');
  }
}

function authHeaders(deviceToken) {
  return {
    Accept: 'application/json',
    Authorization: `Bearer ${deviceToken}`,
  };
}

function evaluateUrl(policy, url) {
  const host = hostFromUrl(url);
  const registrableDomain = registrableDomainForHost(host);
  const rules = Array.isArray(policy?.rules) ? policy.rules : [];
  const mode = policy?.mode === 'whitelist' ? 'whitelist' : 'blacklist';
  const effect = mode === 'whitelist' ? 'allow' : 'block';
  const matchedRule = rules.find((rule) => rule.effect === effect && matchesDomainTree(rule, host, registrableDomain));

  return {
    allowed: mode === 'whitelist' ? Boolean(matchedRule) : !matchedRule,
    host,
    registrableDomain,
    matchedRuleId: matchedRule?.id || null,
  };
}

function matchesDomainTree(rule, host, registrableDomain) {
  if (rule.match_type !== 'domain_tree') {
    return false;
  }

  const value = String(rule.value || '').toLowerCase();

  return registrableDomain === value || host === value || host.endsWith(`.${value}`);
}

function hostFromUrl(url) {
  try {
    return new URL(url).hostname.toLowerCase().replace(/\.$/, '');
  } catch (_error) {
    return '';
  }
}

function registrableDomainForHost(host) {
  if (!host || /^\d{1,3}(?:\.\d{1,3}){3}$/.test(host)) {
    return host;
  }

  const labels = host.split('.').filter(Boolean);

  if (labels.length <= 2) {
    return labels.join('.');
  }

  const lastTwo = labels.slice(-2).join('.');

  return MULTI_LABEL_PUBLIC_SUFFIXES.has(lastTwo)
    ? labels.slice(-3).join('.')
    : lastTwo;
}

function isHttpUrl(url) {
  return /^https?:\/\//i.test(url);
}

function schemeFromUrl(url) {
  try {
    return new URL(url).protocol.toLowerCase();
  } catch (_error) {
    return '';
  }
}

function isBrowserInternalUrl(url) {
  const scheme = schemeFromUrl(url);

  return !scheme || BROWSER_INTERNAL_SCHEMES.has(scheme);
}

function isRestrictedNonWebUrl(url) {
  return RESTRICTED_NON_WEB_SCHEMES.has(schemeFromUrl(url));
}

function isPlatformUrl(url, platformUrl) {
  const platformHost = hostFromUrl(platformUrl);

  return Boolean(platformHost) && hostFromUrl(url) === platformHost;
}

function isBlockedPage(url) {
  return url.startsWith(chrome.runtime.getURL('src/blocked.html'));
}

function shouldIgnoreUrl(url, platformUrl = '') {
  return !url || isBlockedPage(url) || isPlatformUrl(url, platformUrl) || isBrowserInternalUrl(url);
}

function displayHostForUrl(url) {
  const host = hostFromUrl(url);

  if (host) {
    return host;
  }

  const scheme = schemeFromUrl(url).replace(/:$/, '');

  if (scheme === 'file') {
    return 'local file';
  }

  return scheme ? `${scheme} URL` : '';
}

function restrictedUrlEvaluation(url) {
  const displayHost = displayHostForUrl(url) || 'restricted URL';

  return {
    allowed: false,
    host: displayHost,
    registrableDomain: displayHost,
    matchedRuleId: null,
  };
}

function restrictedUrlReference(url) {
  const scheme = schemeFromUrl(url);

  if (scheme === 'data:') {
    return 'data:';
  }

  if (String(url).length > 2000) {
    return scheme || 'restricted:';
  }

  return url;
}

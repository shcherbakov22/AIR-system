const DEFAULT_POLICY = {
  mode: 'blacklist',
  default_unblock_scope: 'domain_tree',
  log_full_url: true,
  rules: [],
  updated_at: null,
};

const POLICY_REFRESH_ALARM = 'policy_refresh';
const EXTENSION_HEARTBEAT_ALARM = 'extension_heartbeat';
const EXTENSION_HEARTBEAT_IMMEDIATE_ALARM = 'extension_heartbeat_immediate';
const POLICY_REFRESH_MINUTES = 5;
const EXTENSION_HEARTBEAT_MINUTES = 1;
const POLICY_REFRESH_MAX_AGE_MS = POLICY_REFRESH_MINUTES * 60 * 1000;
const IMMEDIATE_HEARTBEAT_DELAY_MS = 15_000;

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

chrome.runtime.onInstalled.addListener(() => {
  initializeBackground();
});

chrome.runtime.onStartup.addListener(() => {
  initializeBackground();
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === POLICY_REFRESH_ALARM) {
    syncPolicy().catch(() => {});
    return;
  }

  if (alarm.name === EXTENSION_HEARTBEAT_ALARM || alarm.name === EXTENSION_HEARTBEAT_IMMEDIATE_ALARM) {
    heartbeatExtension().catch(() => {});
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
  await logVisit(tab.url, tab.title || null);
});

chrome.tabs.onActivated.addListener(() => {
  queueImmediateHeartbeat();
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
      .catch((error) => sendResponse({ ok: false, error: error.message }));

    return true;
  }

  return false;
});

initializeBackground();

function initializeBackground() {
  schedulePolicyRefreshAlarm();
  scheduleExtensionHeartbeatAlarm();
  queueImmediateHeartbeat();
  syncPolicy().catch(() => {});
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

function queueImmediateHeartbeat() {
  chrome.alarms.create(EXTENSION_HEARTBEAT_IMMEDIATE_ALARM, {
    when: Date.now() + IMMEDIATE_HEARTBEAT_DELAY_MS,
  });
}

async function handleNavigation(tabId, url) {
  if (!isHttpUrl(url) || isBlockedPage(url)) {
    return;
  }

  const settings = await getSettings();

  if (isPlatformUrl(url, settings.platformUrl)) {
    return;
  }

  const policy = await getPolicy();
  const evaluation = evaluateUrl(policy, url);

  await logVisit(url, null, evaluation);

  if (evaluation.allowed) {
    return;
  }

  const blockedUrl = chrome.runtime.getURL(
    `src/blocked.html?url=${encodeURIComponent(url)}&host=${encodeURIComponent(evaluation.host)}&domain=${encodeURIComponent(evaluation.registrableDomain)}&mode=${encodeURIComponent(policy.mode)}`,
  );

  await chrome.tabs.update(tabId, { url: blockedUrl });
}

async function syncPolicy() {
  const settings = await getSettings();

  if (!settings.platformUrl || !settings.deviceToken) {
    return DEFAULT_POLICY;
  }

  const payload = await fetchRemotePolicy(settings);
  const policy = payload.policy || DEFAULT_POLICY;

  await applyNetworkRules(policy, settings.platformUrl);

  await chrome.storage.local.set({
    policy,
    lastPolicySyncAt: new Date().toISOString(),
    lastExtensionHeartbeatAt: new Date().toISOString(),
  });

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
    nextState.policy = remotePolicy;
    nextState.lastPolicySyncAt = heartbeatAt;
  }

  await chrome.storage.local.set(nextState);
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

async function applyNetworkRules(policy, platformUrl = '') {
  networkRuleUpdate = networkRuleUpdate.then(() => applyNetworkRulesNow(policy, platformUrl));

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

async function logVisit(url, pageTitle, knownEvaluation = null) {
  if (!isHttpUrl(url) || isBlockedPage(url)) {
    return;
  }

  const key = `${url}:${pageTitle || ''}`;
  const lastSeen = recentVisits.get(key) || 0;

  if (Date.now() - lastSeen < 10_000) {
    return;
  }

  recentVisits.set(key, Date.now());

  const settings = await getSettings();

  if (!settings.platformUrl || !settings.deviceToken) {
    return;
  }

  if (isPlatformUrl(url, settings.platformUrl)) {
    return;
  }

  const policy = knownEvaluation ? null : await getPolicy();
  const evaluation = knownEvaluation || evaluateUrl(policy, url);

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
      },
    }),
  }).catch(() => {});
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
  const stored = await chrome.storage.local.get(['platformUrl', 'deviceToken']);
  const platformUrl = String(stored.platformUrl || 'https://192.168.11.228').replace(/\/+$/, '');

  return {
    platformUrl,
    deviceToken: stored.deviceToken || '',
  };
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

function isPlatformUrl(url, platformUrl) {
  const platformHost = hostFromUrl(platformUrl);

  return Boolean(platformHost) && hostFromUrl(url) === platformHost;
}

function isBlockedPage(url) {
  return url.startsWith(chrome.runtime.getURL('src/blocked.html'));
}

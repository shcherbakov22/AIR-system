const DEFAULT_CONFIG = {
    platformBaseUrl: 'http://127.0.0.1:8000',
    sharedToken: 'dev-edge-token',
    clientKey: 'school-system-extension-dev',
    label: 'School System Extension (Dev)',
    version: '0.1.0',
    heartbeatMinutes: 1,
    capabilities: ['heartbeat'],
};

async function getConfig() {
    const stored = await chrome.storage.local.get(Object.keys(DEFAULT_CONFIG));

    return {
        ...DEFAULT_CONFIG,
        ...stored,
    };
}

async function sendHeartbeat(reason) {
    const config = await getConfig();

    const response = await fetch(`${config.platformBaseUrl}/api/edge-clients/heartbeat`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Edge-Client-Token': config.sharedToken,
        },
        body: JSON.stringify({
            client_key: config.clientKey,
            client_type: 'browser_extension',
            label: config.label,
            version: config.version,
            capabilities: config.capabilities,
            meta: {
                reason,
                userAgent: navigator.userAgent,
            },
        }),
    });

    if (!response.ok) {
        throw new Error(`Heartbeat failed with ${response.status}`);
    }

    return response.json();
}

async function configureHeartbeatAlarm() {
    const config = await getConfig();

    await chrome.alarms.clear('edge-heartbeat');
    await chrome.alarms.create('edge-heartbeat', { periodInMinutes: config.heartbeatMinutes });
}

chrome.runtime.onInstalled.addListener(async () => {
    await configureHeartbeatAlarm();
    await sendHeartbeat('installed');
});

chrome.runtime.onStartup.addListener(async () => {
    await configureHeartbeatAlarm();
    await sendHeartbeat('startup');
});

chrome.alarms.onAlarm.addListener(async (alarm) => {
    if (alarm.name !== 'edge-heartbeat') {
        return;
    }

    await sendHeartbeat('alarm');
});

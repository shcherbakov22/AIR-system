const platformUrl = document.getElementById('platformUrl');
const deviceToken = document.getElementById('deviceToken');
const status = document.getElementById('status');

Promise.all([
  readManagedSettings(),
  chrome.storage.local.get(['platformUrl', 'deviceToken']),
]).then(([managed, stored]) => {
  platformUrl.value = managed.platformUrl || stored.platformUrl || 'https://192.168.11.228';
  deviceToken.value = managed.deviceToken || stored.deviceToken || '';

  if (managed.platformUrl || managed.deviceToken) {
    platformUrl.disabled = true;
    deviceToken.disabled = true;
    document.getElementById('saveButton').disabled = true;
    status.textContent = 'Settings are managed by browser policy.';
  }
});

document.getElementById('saveButton').addEventListener('click', async () => {
  await chrome.storage.local.set({
    platformUrl: platformUrl.value.trim().replace(/\/+$/, ''),
    deviceToken: deviceToken.value.trim(),
  });

  status.textContent = 'Settings saved.';
});

document.getElementById('syncButton').addEventListener('click', async () => {
  status.textContent = 'Syncing policy...';
  const response = await chrome.runtime.sendMessage({ type: 'sync_policy' });

  status.textContent = response?.ok
    ? 'Policy synced.'
    : response?.error || 'Policy sync failed.';
});

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

const platformUrl = document.getElementById('platformUrl');
const deviceToken = document.getElementById('deviceToken');
const status = document.getElementById('status');

chrome.storage.local.get(['platformUrl', 'deviceToken']).then((stored) => {
  platformUrl.value = stored.platformUrl || 'https://192.168.11.228';
  deviceToken.value = stored.deviceToken || '';
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

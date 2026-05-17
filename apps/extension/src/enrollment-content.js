window.addEventListener('message', async (event) => {
  if (event.source !== window || event.data?.type !== 'air_browser_extension_configure') {
    return;
  }

  const platformUrl = String(event.data.platformUrl || '').trim().replace(/\/+$/, '');
  const deviceToken = String(event.data.deviceToken || '').trim();

  if (!platformUrl || !deviceToken) {
    window.postMessage({
      type: 'air_browser_extension_configured',
      ok: false,
      error: 'Missing platform URL or device token.',
    }, window.location.origin);
    return;
  }

  try {
    await chrome.storage.local.set({ platformUrl, deviceToken });
    const response = await chrome.runtime.sendMessage({ type: 'sync_policy' });

    window.postMessage({
      type: 'air_browser_extension_configured',
      ok: Boolean(response?.ok),
      error: response?.ok ? null : (response?.error || 'Policy sync failed.'),
    }, window.location.origin);
  } catch (error) {
    window.postMessage({
      type: 'air_browser_extension_configured',
      ok: false,
      error: error instanceof Error ? error.message : 'Extension setup failed.',
    }, window.location.origin);
  }
});

sendContentScriptHeartbeat();
setInterval(sendContentScriptHeartbeat, 30_000);

function sendContentScriptHeartbeat() {
  chrome.runtime.sendMessage({
    type: 'content_script_heartbeat',
    url: location.href,
    title: document.title || null,
  }).catch(() => {});
}

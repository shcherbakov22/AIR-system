const params = new URLSearchParams(location.search);
const requestedUrl = params.get('url') || '';
const parsedUrl = parseUrl(requestedUrl);
const mode = params.get('mode') || '';
const host = params.get('host') || parsedUrl?.hostname || '';
const domain = params.get('domain') || registrableDomainForHost(host);
const displayHost = host || displayNameForUrl(parsedUrl);
const displayScope = domain || displayHost;
const isDomainScoped = Boolean(domain && host && mode !== 'restricted');

document.getElementById('host').textContent = displayHost || 'Restricted URL';
document.getElementById('scope').textContent = displayScope
  ? `${displayScope}${isDomainScoped ? ' and subdomains' : ''}`
  : 'This URL';

refreshPolicyAndRedirectIfAllowed(false);

document.getElementById('retryButton').addEventListener('click', async () => {
  await refreshPolicyAndRedirectIfAllowed(true);
});

document.getElementById('requestButton').addEventListener('click', async () => {
  const button = document.getElementById('requestButton');
  const status = document.getElementById('status');

  button.disabled = true;
  status.textContent = 'Sending request...';

  const response = await chrome.runtime.sendMessage({
    type: 'request_access',
    url: requestedUrl,
    reason: document.getElementById('reason').value,
  });

  if (response?.ok) {
    status.textContent = `Request sent for ${displayScope || 'this URL'}.`;
  } else {
    status.textContent = response?.error || 'Request failed.';
    button.disabled = false;
  }
});

function parseUrl(value) {
  try {
    return new URL(value);
  } catch (_error) {
    return null;
  }
}

function displayNameForUrl(url) {
  const scheme = url?.protocol?.replace(/:$/, '') || '';

  if (scheme === 'file') {
    return 'Local file';
  }

  return scheme ? `${scheme} URL` : '';
}

async function refreshPolicyAndRedirectIfAllowed(showStatus) {
  if (!requestedUrl) {
    return;
  }

  const status = document.getElementById('status');
  const retryButton = document.getElementById('retryButton');

  if (showStatus) {
    retryButton.disabled = true;
    status.textContent = 'Checking policy...';
  }

  try {
    const response = await chrome.runtime.sendMessage({
      type: 'evaluate_url_after_sync',
      url: requestedUrl,
    });

    if (response?.ok && response.evaluation?.allowed) {
      location.href = requestedUrl;
      return;
    }

    if (showStatus) {
      status.textContent = response?.ok
        ? 'Still blocked by the current policy.'
        : response?.error || 'Policy refresh failed.';
    }
  } catch (error) {
    if (showStatus) {
      status.textContent = error?.message || 'Policy refresh failed.';
    }
  } finally {
    if (showStatus) {
      retryButton.disabled = false;
    }
  }
}

function registrableDomainForHost(host) {
  const multiLabelPublicSuffixes = new Set([
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

  if (!host || /^\d{1,3}(?:\.\d{1,3}){3}$/.test(host)) {
    return host;
  }

  const labels = host.toLowerCase().split('.').filter(Boolean);

  if (labels.length <= 2) {
    return labels.join('.');
  }

  const lastTwo = labels.slice(-2).join('.');

  return multiLabelPublicSuffixes.has(lastTwo)
    ? labels.slice(-3).join('.')
    : lastTwo;
}

const params = new URLSearchParams(location.search);
const requestedUrl = params.get('url') || '';
const parsedUrl = parseUrl(requestedUrl);
const host = params.get('host') || parsedUrl?.hostname || '';
const domain = params.get('domain') || registrableDomainForHost(host);

document.getElementById('host').textContent = host;
document.getElementById('scope').textContent = `${domain} and subdomains`;

document.getElementById('retryButton').addEventListener('click', () => {
  if (requestedUrl) {
    location.href = requestedUrl;
  }
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
    status.textContent = `Request sent for ${domain} and subdomains.`;
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

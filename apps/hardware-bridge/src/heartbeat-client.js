export async function sendHeartbeat({
    platformBaseUrl,
    sharedToken,
    clientKey,
    label,
    version,
    capabilities,
    meta,
}) {
    const response = await fetch(`${platformBaseUrl}/api/edge-clients/heartbeat`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Edge-Client-Token': sharedToken,
        },
        body: JSON.stringify({
            client_key: clientKey,
            client_type: 'hardware_bridge',
            label,
            version,
            capabilities,
            meta,
        }),
    });

    if (!response.ok) {
        const body = await response.text();
        throw new Error(`Heartbeat failed: ${response.status} ${body}`);
    }

    return response.json();
}

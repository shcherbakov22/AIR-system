import { sendHeartbeat } from './heartbeat-client.js';

const config = {
    platformBaseUrl: process.env.PLATFORM_BASE_URL ?? 'http://127.0.0.1:8000',
    sharedToken: process.env.EDGE_CLIENT_SHARED_TOKEN ?? 'dev-edge-token',
    clientKey: process.env.EDGE_CLIENT_KEY ?? 'school-system-hardware-bridge-dev',
    label: process.env.EDGE_CLIENT_LABEL ?? 'School System Hardware Bridge (Dev)',
    version: '0.1.0',
    capabilities: ['heartbeat'],
    meta: {
        runtime: 'node',
        pid: process.pid,
    },
};

try {
    const response = await sendHeartbeat(config);
    console.log(JSON.stringify(response, null, 2));
} catch (error) {
    console.error(error instanceof Error ? error.message : String(error));
    process.exitCode = 1;
}

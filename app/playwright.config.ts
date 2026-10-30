import { defineConfig } from '@playwright/test';

const baseURL = process.env.E2E_BASE_URL || 'http://localhost:8002';
const target = new URL(baseURL);
if (
    !['localhost', '127.0.0.1'].includes(target.hostname) ||
    !['8002', '8101', '8102', '8103'].includes(target.port) ||
    process.env.E2E_DATABASE !== 'sas_san_test'
) {
    throw new Error(
        'Las pruebas de navegador requieren un puerto local de aceptación y E2E_DATABASE=sas_san_test.',
    );
}

export default defineConfig({
    testDir: './tests/browser',
    workers: 1,
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: 0,
    timeout: 60000,
    expect: { timeout: 15000 },
    outputDir: '../.runtime/browser-results',
    reporter: 'list',
    use: {
        baseURL,
        headless: true,
        channel: process.platform === 'win32' ? 'msedge' : undefined,
        screenshot: 'only-on-failure',
        trace: 'off',
    },
});

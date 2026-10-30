import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { createHmac } from 'node:crypto';

type Account = { email: string; password: string; totp: string };
const access = JSON.parse(
    readFileSync(
        process.env.E2E_ACCESS_FILE || '../.runtime/demo-access.json',
        'utf8',
    ),
) as {
    accounts: { admin: Account };
    companies: { moda: { slug: string }; calzado: { slug: string } };
};
const slug = access.companies.moda.slug;

function code(secret: string): string {
    let bits = '';
    for (const char of secret.toUpperCase().replace(/=+$/, ''))
        bits += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'
            .indexOf(char)
            .toString(2)
            .padStart(5, '0');
    const key = Buffer.from(
        (bits.match(/.{8}/g) || []).map((byte) => Number.parseInt(byte, 2)),
    );
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const digest = createHmac('sha1', key).update(counter).digest();
    const offset = digest[digest.length - 1] & 15;
    return ((digest.readUInt32BE(offset) & 0x7fffffff) % 1000000)
        .toString()
        .padStart(6, '0');
}

async function login(page: Page) {
    await page.goto('/login');
    await page.locator('#email').fill(access.accounts.admin.email);
    await page.locator('#password').fill(access.accounts.admin.password);
    await page.locator('[data-test="login-button"]').click();
    await expect(page).toHaveURL(/two-factor-challenge/);
    // Every login uses a new TOTP period; replay protection remains enabled.
    await page.waitForTimeout(31000 - (Date.now() % 30000));
    await page
        .locator('input[autocomplete="one-time-code"]')
        .fill(code(access.accounts.admin.totp));
    await page.getByRole('button', { name: 'Continuar', exact: true }).click();
    await expect(page).toHaveURL(/dashboard|gestion/);
}

test('tienda móvil: catálogo, carrito y cotización real sin desbordamiento', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/tienda/${slug}`);
    await page
        .getByRole('button', { name: /Añadir al carrito/ })
        .first()
        .click();
    await expect(page.locator('#carrito')).toContainText('Tu carrito');
    await page
        .getByRole('button', { name: /Calcular total|Aplicar cupón/ })
        .click();
    await expect(page.locator('#carrito')).toContainText('Total');
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth + 1,
        ),
    ).toBeTruthy();
});

test('propietario: contraseña, MFA, producto mínimo y gestor de fotos', async ({
    page,
}) => {
    await login(page);
    await page.goto(`/gestion/${slug}`);
    const name = `Producto navegador ${Date.now()}`;
    await page.getByLabel('Nombre del producto', { exact: false }).fill(name);
    await page
        .locator('#product-form')
        .getByLabel(/Precio/)
        .first()
        .fill('12.50');
    await page
        .getByRole('button', { name: 'Guardar producto', exact: true })
        .click();
    const product = page
        .locator('article')
        .filter({ has: page.getByRole('heading', { name, exact: true }) });
    await expect(product).toContainText('12.50');
    await product.getByRole('link', { name: 'Gestionar fotos' }).click();
    await expect(
        page.getByRole('heading', { name: `Fotos de ${name}` }),
    ).toBeVisible();
    await expect(
        page.getByText('Aún no hay fotos.', { exact: false }),
    ).toBeVisible();
});

test('propietario: las pantallas operativas y la búsqueda se cargan sin errores', async ({
    page,
}) => {
    await login(page);
    for (const section of [
        'inventario',
        'compras',
        'pos',
        'caja',
        'promociones',
    ]) {
        const response = await page.goto(
            `/gestion/${slug}/operacion/${section}`,
        );
        expect(response?.status()).toBe(200);
        await expect(page.locator('body')).not.toContainText(
            'Internal Server Error',
        );
    }
    await page.goto(`/gestion/${slug}/operacion/pos`);
    await page
        .getByPlaceholder('Nombre, SKU o código de barras')
        .fill('Producto navegador');
    await expect(
        page
            .locator('section')
            .filter({
                has: page.getByPlaceholder('Nombre, SKU o código de barras'),
            })
            .getByRole('button')
            .first(),
    ).toBeVisible();
});

test('offline: una venta desconectada conserva pendientes al recargar y sincroniza al volver', async ({
    page,
    context,
}) => {
    await login(page);
    await page.goto(`/gestion/${slug}/operacion/pos`);
    const cookies = await context.cookies();
    const xsrf = decodeURIComponent(
        cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value || '',
    );
    expect(xsrf).not.toBe('');
    const headers = { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json' };
    const pos = JSON.parse(
        (await page.locator('script[data-page]').textContent()) || '{}',
    ) as {
        props: {
            warehouses: { id: number }[];
            data: {
                cashSessions: {
                    id: number;
                    warehouse_id: number;
                    closed_at: string | null;
                }[];
                products: { id: number }[];
                stock: {
                    warehouse_id: number;
                    product_id: number;
                    physical: number;
                    reserved: number;
                    blocked: number;
                    offline: number;
                }[];
            };
        };
    };
    let cashRow = pos.props.data.cashSessions.find(
        (session) => !session.closed_at,
    );
    let cash = cashRow?.id;
    if (!cash) {
        const availablePosition = pos.props.data.stock.find(
            (row) =>
                row.physical - row.reserved - row.blocked - row.offline >= 1,
        );
        expect(availablePosition).toBeTruthy();
        const response = await page.request.post(`/gestion/${slug}/operacion`, {
            headers,
            data: {
                action: 'open_cash',
                key: crypto.randomUUID(),
                warehouse_id: availablePosition!.warehouse_id,
                opening_minor: 0,
            },
        });
        expect(response.ok()).toBeTruthy();
        await page.reload();
        const value = JSON.parse(
            (await page.locator('script[data-page]').textContent()) || '{}',
        ) as typeof pos;
        cashRow = value.props.data.cashSessions.find(
            (session) => !session.closed_at,
        );
        cash = cashRow?.id;
    }
    expect(cash).toBeTruthy();
    const stock = pos.props.data.stock.find(
        (row) =>
            Number(row.warehouse_id) === Number(cashRow?.warehouse_id) &&
            row.physical - row.reserved - row.blocked - row.offline >= 1,
    );
    expect(stock).toBeTruthy();
    const grantResponse = await page.request.post(
        `/gestion/${slug}/offline/asignar`,
        {
            headers,
            data: {
                key: crypto.randomUUID(),
                name: 'Navegador sintético',
                cash_session_id: cash,
                items: [{ product_id: stock!.product_id, quantity: 1 }],
            },
        },
    );
    const grant = await grantResponse.json();
    expect(
        grantResponse.ok(),
        String(grant.message || grant.errors?.operation || ''),
    ).toBeTruthy();
    await page.goto('/offline.html');
    await page.evaluate(async () => {
        await navigator.serviceWorker.ready;
    });
    // An installed worker controls the next navigation; reload online before disconnecting.
    await page.reload();
    await page.waitForFunction(() => !!navigator.serviceWorker.controller);
    await page.locator('#password').fill('ClaveLocalDePrueba2026');
    await page.locator('#import').setInputFiles({
        name: 'asignacion-sintetica.json',
        mimeType: 'application/json',
        buffer: Buffer.from(JSON.stringify(grant)),
    });
    await expect(page.locator('#workspace')).toBeVisible();
    await context.setOffline(true);
    await page.locator('#product').selectOption(String(stock!.product_id));
    await page.locator('#add').click();
    await page.locator('#sell').click();
    await expect(page.locator('#pending')).toContainText('Pendiente');
    await page.reload();
    await page.locator('#password').fill('ClaveLocalDePrueba2026');
    await page.locator('#unlock').click();
    await expect(page.locator('#pending')).toContainText('Pendiente');
    await context.setOffline(false);
    await page.locator('#sync').click();
    await expect(page.locator('#pending')).toContainText('Sincronizada');
    await page.locator('#sync').click();
    await expect(page.locator('#pending').locator('li')).toHaveCount(1);
});

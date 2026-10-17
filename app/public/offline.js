'use strict';
const $ = (id) => document.getElementById(id);
const enc = new TextEncoder();
const dec = new TextDecoder();
let key = null,
    state = null,
    salt = null,
    basket = [];
let dirty = false;
const money = (n) =>
    new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(n / 100);
function message(text) {
    $('message').textContent = text;
}
function db() {
    return new Promise((resolve, reject) => {
        const r = indexedDB.open('sas-san-offline', 1);
        r.onupgradeneeded = () =>
            r.result.createObjectStore('vault', { keyPath: 'id' });
        r.onsuccess = () => resolve(r.result);
        r.onerror = () => reject(r.error);
    });
}
async function stored() {
    const d = await db();
    return new Promise((resolve, reject) => {
        const tx = d.transaction('vault', 'readonly');
        const r = tx.objectStore('vault').get('state');
        r.onsuccess = () => resolve(r.result);
        r.onerror = () => reject(r.error);
        tx.oncomplete = () => d.close();
    });
}
async function write(record) {
    const d = await db();
    return new Promise((resolve, reject) => {
        const tx = d.transaction('vault', 'readwrite');
        tx.objectStore('vault').put(record);
        tx.oncomplete = () => {
            d.close();
            resolve();
        };
        tx.onerror = () => reject(tx.error);
    });
}
async function derive(password, s) {
    const base = await crypto.subtle.importKey(
        'raw',
        enc.encode(password),
        'PBKDF2',
        false,
        ['deriveKey'],
    );
    return crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt: s, iterations: 310000, hash: 'SHA-256' },
        base,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}
async function readState() {
    const record = await stored();
    if (!record) return null;
    const text = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: new Uint8Array(record.iv) },
        key,
        new Uint8Array(record.cipher),
    );
    return JSON.parse(dec.decode(text));
}
async function encryptedRecord() {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const cipher = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        enc.encode(JSON.stringify(state)),
    );
    return {
        id: 'state',
        salt: Array.from(salt),
        iv: Array.from(iv),
        cipher: Array.from(new Uint8Array(cipher)),
    };
}
async function save() {
    try {
        await write(await encryptedRecord());
        dirty = false;
    } catch (error) {
        dirty = true;
        throw new Error(
            'No se pudo guardar. No cierres esta pantalla: exporta un respaldo cifrado y reintenta el guardado. ' +
                error.message,
        );
    }
}
async function lockedMutation(callback) {
    if (!navigator.locks)
        throw new Error(
            'Este navegador no permite coordinar pestañas. Usa un navegador compatible antes de registrar ventas.',
        );
    return navigator.locks.request('sas-san-offline-vault', async () => {
        if (!key) throw new Error('Desbloquea el dispositivo.');
        if (dirty)
            throw new Error(
                'Hay una venta sin guardar. Exporta el respaldo y reintenta el guardado antes de continuar.',
            );
        const latest = await readState();
        if (latest) state = latest;
        const result = await callback();
        await save();
        return result;
    });
}
function show() {
    $('locked').hidden = !!state;
    $('workspace').hidden = !state;
    if (!state) return;
    const selected = $('device').value;
    $('device').replaceChildren();
    for (const d of state.devices) {
        const o = document.createElement('option');
        o.value = d.id;
        o.textContent = d.name + ' · ' + d.slug;
        $('device').append(o);
    }
    if (state.devices.some((d) => d.id === selected))
        $('device').value = selected;
    render();
}
function device() {
    return state?.devices.find((d) => d.id === $('device').value);
}
function loggedOut(d) {
    return (
        Number(localStorage.getItem('sas-offline-lock') || 0) >
        (d.imported_at || Date.parse(d.server_time))
    );
}
function remaining(d, p) {
    return (
        p.quantity -
        state.sales
            .filter((s) => s.device_id === d.id)
            .flatMap((s) => s.items)
            .filter((l) => l.product_id === p.product_id)
            .reduce((a, l) => a + l.quantity, 0)
    );
}
function render() {
    if (!state) return;
    const d = device();
    $('product').replaceChildren();
    if (!d) return;
    for (const p of d.items) {
        const o = document.createElement('option');
        o.value = p.product_id;
        o.textContent =
            p.name +
            ' · ' +
            money(p.unit_minor) +
            ' · ' +
            remaining(d, p) +
            ' disponibles';
        $('product').append(o);
    }
    $('balance').textContent =
        'Permiso hasta ' +
        new Date(d.expires_at).toLocaleString('es-PE') +
        '. Estado: ' +
        (Date.now() > Date.parse(d.expires_at)
            ? 'vencido, no se permiten nuevas ventas'
            : 'vigente');
    $('balance').textContent +=
        ' · Última secuencia: ' +
        state.sales
            .filter((s) => s.device_id === d.id)
            .reduce((max, s) => Math.max(max, s.sequence), 0) +
        (loggedOut(d)
            ? ' · Captura bloqueada por cierre de sesión; sincroniza los pendientes y solicita un permiso nuevo.'
            : '');
    $('basket').replaceChildren();
    for (const l of basket) {
        const li = document.createElement('li');
        li.textContent =
            l.quantity +
            ' × ' +
            d.items.find((p) => p.product_id === l.product_id).name +
            ' ';
        const b = document.createElement('button');
        b.textContent = 'Quitar';
        b.onclick = () => {
            basket = basket.filter((x) => x !== l);
            render();
        };
        li.append(b);
        $('basket').append(li);
    }
    $('total').textContent =
        'Total: ' +
        money(
            basket.reduce(
                (a, l) =>
                    a +
                    l.quantity *
                        d.items.find((p) => p.product_id === l.product_id)
                            .unit_minor,
                0,
            ),
        );
    $('sell').disabled =
        !basket.length ||
        dirty ||
        loggedOut(d) ||
        Date.now() > Date.parse(d.expires_at);
    $('pending').replaceChildren();
    for (const sale of state.sales) {
        const li = document.createElement('li');
        li.textContent =
            sale.sequence +
            ' · ' +
            money(sale.total_minor) +
            ' · ' +
            (sale.synced
                ? 'Sincronizada'
                : sale.error
                  ? 'En revisión: ' + sale.error
                  : 'Pendiente');
        $('pending').append(li);
    }
}
function password() {
    const p = $('password').value;
    if (p.length < 8)
        throw new Error('La clave local debe tener al menos 8 caracteres.');
    return p;
}
async function unlock() {
    const r = await stored();
    if (!r) throw new Error('Primero importa una asignación.');
    salt = new Uint8Array(r.salt);
    key = await derive(password(), salt);
    state = await readState();
    $('password').value = '';
    show();
}
async function importGrant(file) {
    if (file.size > 5242880) throw new Error('El archivo supera 5 MB.');
    const grant = JSON.parse(await file.text());
    if (
        !grant.id ||
        !grant.slug ||
        !Array.isArray(grant.items) ||
        !grant.items.length ||
        grant.items.length > 500
    )
        throw new Error('El archivo no es una asignación válida.');
    if (
        !Number.isFinite(Date.parse(grant.server_time)) ||
        !Number.isFinite(Date.parse(grant.expires_at)) ||
        Date.now() + 300000 < Date.parse(grant.server_time)
    )
        throw new Error(
            'Revisa el reloj del equipo y la asignación antes de continuar.',
        );
    grant.imported_at = Date.now();
    if (!state) {
        const existing = await stored();
        if (existing) {
            await unlock();
        } else {
            salt = crypto.getRandomValues(new Uint8Array(16));
            key = await derive(password(), salt);
            state = {
                version: 1,
                devices: [],
                sales: [],
                lastTime: Date.now(),
            };
        }
    }
    await lockedMutation(() => {
        if (state.devices.some((d) => d.id === grant.id))
            throw new Error('Este permiso ya está importado.');
        state.devices.push(grant);
    });
    $('password').value = '';
    show();
    if (navigator.storage?.persist) await navigator.storage.persist();
}
async function sell() {
    const id = $('device').value;
    await lockedMutation(() => {
        const d = state.devices.find((x) => x.id === id);
        if (loggedOut(d))
            throw new Error(
                'La captura se bloqueó al cerrar sesión. Conserva pendientes y solicita un permiso nuevo.',
            );
        if (Date.now() < state.lastTime - 300000)
            throw new Error(
                'El reloj del equipo retrocedió. Revisa la hora antes de vender.',
            );
        if (Date.now() > Date.parse(d.expires_at))
            throw new Error(
                'El permiso venció. Conserva tus pendientes y vuelve a gestión.',
            );
        for (const l of basket) {
            const p = d.items.find((p) => p.product_id === l.product_id);
            if (
                !p ||
                !Number.isInteger(l.quantity) ||
                l.quantity < 1 ||
                l.quantity > remaining(d, p)
            )
                throw new Error('Unidades insuficientes en la asignación.');
        }
        if (!basket.length) throw new Error('Añade un producto.');
        const sequence =
            Math.max(
                0,
                ...state.sales
                    .filter((s) => s.device_id === id)
                    .map((s) => s.sequence),
            ) + 1;
        const sale = {
            device_id: id,
            operation_id: crypto.randomUUID(),
            sequence,
            captured_at: new Date().toISOString(),
            items: basket.map((l) => ({ ...l })),
            total_minor: basket.reduce(
                (a, l) =>
                    a +
                    l.quantity *
                        d.items.find((p) => p.product_id === l.product_id)
                            .unit_minor,
                0,
            ),
        };
        state.sales.push(sale);
        state.lastTime = Date.now();
        $('ticket').textContent =
            'SAS SAN · TICKET INTERNO\n' +
            d.slug +
            '\nOperación ' +
            sale.operation_id +
            '\n' +
            sale.items
                .map(
                    (l) =>
                        l.quantity +
                        ' x ' +
                        d.items.find((p) => p.product_id === l.product_id).name,
                )
                .join('\n') +
            '\nTOTAL EFECTIVO ' +
            money(sale.total_minor) +
            '\nPendiente de sincronización.\nNo es comprobante aceptado por SUNAT.';
    });
    basket = [];
    render();
    message('Venta guardada en el dispositivo. Pendiente de sincronización.');
}
async function sync() {
    await lockedMutation(async () => {
        for (const sale of state.sales.filter((s) => !s.synced)) {
            const d = state.devices.find((d) => d.id === sale.device_id);
            const token =
                document.cookie
                    .split('; ')
                    .find((c) => c.startsWith('XSRF-TOKEN='))
                    ?.slice(11) || '';
            try {
                const { synced, error, ...payload } = sale;
                const r = await fetch(
                    '/gestion/' +
                        encodeURIComponent(d.slug) +
                        '/offline/sincronizar',
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-XSRF-TOKEN': decodeURIComponent(token),
                        },
                        body: JSON.stringify(payload),
                    },
                );
                const body = await r.json();
                if (!r.ok)
                    throw new Error(
                        body.message || 'Inicia sesión para sincronizar.',
                    );
                sale.synced = true;
                delete sale.error;
            } catch (e) {
                sale.error = e.message;
            }
        }
    });
    render();
    message('Sincronización revisada. Los conflictos permanecen guardados.');
}
function download(record, name) {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(
        new Blob([JSON.stringify(record)], { type: 'application/json' }),
    );
    a.download = name || 'sas-san-pendientes-cifrados-' + Date.now() + '.json';
    a.click();
    URL.revokeObjectURL(a.href);
}
function lock() {
    if (dirty) {
        render();
        message(
            'No cierres esta pantalla: exporta la venta sin guardar y reintenta el guardado.',
        );
        return;
    }
    key = null;
    state = null;
    basket = [];
    $('password').value = '';
    show();
}
function handle(fn) {
    return async () => {
        try {
            message('');
            await fn();
        } catch (e) {
            message(
                e.name === 'OperationError'
                    ? 'No se pudo descifrar. Revisa tu clave local.'
                    : e.message,
            );
            if (state) render();
        }
    };
}
$('unlock').onclick = handle(unlock);
$('import').onchange = handle(async () => {
    if ($('import').files[0]) await importGrant($('import').files[0]);
});
$('restore').onchange = handle(async () => {
    const r = JSON.parse(await $('restore').files[0].text());
    const current = await stored();
    if (current)
        throw new Error(
            'No se sobrescriben pendientes existentes. Restaura el respaldo en un perfil nuevo del navegador.',
        );
    if (!r.salt || !r.iv || !r.cipher) throw new Error('Respaldo inválido.');
    await write(r);
    lock();
    message('Respaldo restaurado. Ingresa su clave local para desbloquear.');
});
$('add').onclick = handle(() => {
    const product_id = Number($('product').value),
        quantity = Number($('quantity').value);
    if (!product_id || !Number.isInteger(quantity) || quantity < 1)
        throw new Error('Selecciona un producto y unidades enteras.');
    const old = basket.find((l) => l.product_id === product_id);
    if (old) old.quantity += quantity;
    else basket.push({ product_id, quantity });
    render();
});
$('sell').onclick = handle(sell);
$('sync').onclick = handle(sync);
$('export').onclick = handle(async () =>
    download(state && key ? await encryptedRecord() : await stored()),
);
$('retry-save').onclick = handle(async () => {
    await save();
    render();
    message('Pendientes guardados correctamente.');
});
$('review-export').onclick = handle(() => {
    const id = $('device').value;
    const operations = state.sales
        .filter((s) => s.device_id === id && !s.synced)
        .map((s) => {
            const { synced, error, ...payload } = s;
            return payload;
        });
    if (!operations.length) throw new Error('No hay pendientes para revisión.');
    download({ operations }, 'sas-san-revision-' + id + '.json');
});
$('lock').onclick = lock;
$('print').onclick = () => window.print();
$('device').onchange = () => {
    basket = [];
    render();
};
window.addEventListener('storage', (e) => {
    if (e.key === 'sas-offline-lock') lock();
});
window.addEventListener('beforeunload', (e) => {
    if (dirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});
if ('serviceWorker' in navigator)
    navigator.serviceWorker
        .register('/offline-sw.js')
        .catch(() =>
            message(
                'No se pudo preparar el modo sin conexión. Mantén esta pantalla abierta y verifica antes de desconectarte.',
            ),
        );

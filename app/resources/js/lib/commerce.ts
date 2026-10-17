export const money = (minor: number | string) =>
    new Intl.NumberFormat('es-PE', {
        style: 'currency',
        currency: 'PEN',
    }).format(Number(minor) / 100);
export function minor(value: string | number): number {
    const text = String(value).trim().replace(',', '.');
    if (!/^\d{1,7}(\.\d{1,2})?$/.test(text))
        throw new Error('Ingresa un importe válido con hasta dos decimales.');
    const [whole, fraction = ''] = text.split('.');
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
}
export const state = (
    value: string | number | boolean | null | undefined,
): string =>
    ({
        reserved: 'Reservado',
        confirmed: 'Venta confirmada',
        draft: 'Borrador',
        closed: 'Cerrado',
        purchase_reversal: 'Reverso de recepción',
        inventory_loss: 'Pérdida por conteo',
        inventory_gain: 'Aumento por conteo',
        cancelled: 'Cancelado',
        expired: 'Vencido',
        pending: 'Pendiente',
        verified: 'Verificado',
        rejected: 'Rechazado',
        preparing: 'En preparación',
        ready: 'Listo',
        delivered: 'Entregado',
        cash: 'Efectivo',
        yape: 'Yape',
        bcp: 'BCP',
        web: 'Tienda',
        pos: 'POS',
        receipt: 'Recepción',
        reserve: 'Reserva',
        sale: 'Venta',
        release: 'Liberación',
        return: 'Devolución',
        transfer_out: 'Traslado enviado',
        transfer_in: 'Traslado recibido',
        block: 'Bloqueo',
        unblock: 'Desbloqueo',
        damage: 'Daño',
    })[String(value)] || String(value ?? '—');
export const localDate = (value: string) =>
    new Intl.DateTimeFormat('es-PE', {
        dateStyle: 'short',
        timeStyle: 'short',
        timeZone: 'America/Lima',
    }).format(new Date(value));
export async function jsonPost(
    path: string,
    data: unknown,
): Promise<Record<string, unknown>> {
    const token =
        document.cookie
            .split('; ')
            .find((c) => c.startsWith('XSRF-TOKEN='))
            ?.slice(11) || '';
    const response = await fetch(path, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': decodeURIComponent(token),
        },
        body: JSON.stringify(data),
    });
    if (response.status === 401)
        throw new Error('Inicia sesión con tu cuenta antes de continuar.');
    if (response.status === 423)
        throw new Error(
            'Confirma tu contraseña en Seguridad antes de continuar.',
        );
    if (response.status === 419)
        throw new Error(
            'Tu sesión venció. Actualiza la página e inicia sesión nuevamente.',
        );
    if (!response.headers.get('content-type')?.includes('application/json'))
        throw new Error(
            'No se pudo completar la operación. Revisa la conexión y tu sesión.',
        );
    const result = (await response.json()) as Record<string, unknown>;
    if (!response.ok)
        throw new Error(
            (result.message as string) || 'No se pudo completar la operación.',
        );
    return result;
}

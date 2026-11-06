<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    BarChart3,
    CalendarDays,
    ChevronDown,
    ShoppingBag,
    Wallet,
} from '@lucide/vue';
import { money, state } from '@/lib/commerce';
import type { SalesReport } from '@/types/business';

const props = defineProps<{
    tenant: { name: string; slug: string };
    report: SalesReport;
}>();
const page = usePage();
const percentage = new Intl.NumberFormat('es-PE', {
    style: 'percent',
    maximumFractionDigits: 1,
});
const signedPercentage = new Intl.NumberFormat('es-PE', {
    style: 'percent',
    maximumFractionDigits: 1,
    signDisplay: 'exceptZero',
});
const number = new Intl.NumberFormat('es-PE');
const channelTotal = computed(() =>
    props.report.channel_mix.reduce((sum, item) => sum + item.amount_minor, 0),
);
const channelName = (channel: string) =>
    channel === 'pos' ? 'Caja presencial' : state(channel);
const dateFormat = new Intl.DateTimeFormat('es-PE', {
    day: 'numeric',
    month: 'short',
    timeZone: 'UTC',
});
const date = (day: string) => dateFormat.format(new Date(`${day}T12:00:00Z`));
const period = (from: string, to: string) =>
    from === to ? date(from) : `${date(from)} – ${date(to)}`;
const growth = computed(() =>
    props.report.previous.net_minor > 0
        ? (props.report.net_minor - props.report.previous.net_minor) /
          props.report.previous.net_minor
        : null,
);
const margin = computed(() =>
    props.report.gross_profit_minor !== null && props.report.net_minor > 0
        ? props.report.gross_profit_minor / props.report.net_minor
        : null,
);
const returnRate = computed(() =>
    props.report.gross_minor > 0
        ? props.report.returns_minor / props.report.gross_minor
        : null,
);
const chartPeak = computed(() =>
    Math.max(0, ...props.report.trend.map((point) => point.amount_minor)),
);
const activeBar = ref<number | null>(null);
const selectedPoint = computed(
    () =>
        props.report.trend[
            activeBar.value ??
                props.report.trend.findIndex(
                    (point) => point.amount_minor === chartPeak.value,
                )
        ],
);
const barLabel = (day: string) =>
    props.report.from.slice(0, 7) === props.report.to.slice(0, 7)
        ? day.slice(8)
        : `${day.slice(8)}/${day.slice(5, 7)}`;
const financialDetails = computed(
    () =>
        [
            ['Ventas brutas', props.report.gross_minor],
            ['Descuentos', props.report.discount_minor],
            ['Ingresos por entrega', props.report.delivery_minor],
            ['Devoluciones', props.report.returns_minor],
            ['Reembolsos', props.report.refunds_minor],
            ['Deuda actual', props.report.debt_minor],
            ['Costo de ventas', props.report.cost_minor],
            ['Utilidad bruta', props.report.gross_profit_minor],
            ['Gastos registrados', props.report.expenses_minor],
            ['Pérdidas de inventario', props.report.inventory_loss_minor],
            ['Resultado gerencial', props.report.management_result_minor],
        ] as const,
);
</script>

<template>
    <div class="sales-dashboard">
        <header class="sales-hero">
            <div>
                <p class="sales-eyebrow">
                    <BarChart3 :size="17" /> Resumen comercial
                </p>
                <h1>Ventas de tu negocio</h1>
                <p class="sales-hero-copy">
                    {{ tenant.name }} · Importes en soles
                </p>
                <Link
                    :href="`/gestion/${tenant.slug}/operacion/pedidos`"
                    class="sales-orders-link"
                >
                    Ver pedidos y ventas <ArrowRight :size="16" />
                </Link>
            </div>
            <form
                method="get"
                class="sales-filter"
                aria-label="Filtrar período de ventas"
            >
                <div class="sales-filter-title">
                    <CalendarDays :size="18" />
                    <strong>Período del informe</strong>
                </div>
                <label
                    >Desde<input
                        type="date"
                        name="from"
                        :value="report.from"
                        required
                /></label>
                <label
                    >Hasta<input
                        type="date"
                        name="to"
                        :value="report.to"
                        required
                /></label>
                <button type="submit">
                    Aplicar período <ArrowRight :size="15" />
                </button>
                <p
                    v-if="page.props.errors?.from || page.props.errors?.to"
                    role="alert"
                    class="sales-date-error"
                >
                    {{ page.props.errors.from || page.props.errors.to }}
                </p>
            </form>
        </header>

        <section class="sales-metrics" aria-label="Indicadores principales">
            <article class="sales-card sales-metric sales-metric-primary">
                <div class="sales-metric-heading">
                    <h2>Ventas netas</h2>
                    <span><BarChart3 :size="18" /></span>
                </div>
                <strong class="sales-figure">{{
                    money(report.net_minor)
                }}</strong>
                <p
                    v-if="growth !== null"
                    class="sales-change"
                    :class="{ 'sales-change-down': growth < 0 }"
                >
                    <ArrowDownRight v-if="growth < 0" :size="15" /><ArrowUpRight
                        v-else-if="growth > 0"
                        :size="15"
                    />{{ signedPercentage.format(growth) }} frente al período
                    anterior
                </p>
                <p v-else class="sales-caption">Sin porcentaje comparable</p>
                <p class="sales-caption">
                    {{ period(report.previous.from, report.previous.to) }} ·
                    {{ money(report.previous.net_minor) }}
                </p>
            </article>
            <article class="sales-card sales-metric">
                <div class="sales-metric-heading">
                    <h2>Cobros verificados</h2>
                    <span><Wallet :size="18" /></span>
                </div>
                <strong class="sales-figure">{{
                    money(report.collections_minor)
                }}</strong>
                <p class="sales-caption">
                    Pagos verificados durante el período
                </p>
            </article>
            <article class="sales-card sales-metric">
                <div class="sales-metric-heading">
                    <h2>Ventas confirmadas</h2>
                    <span><ShoppingBag :size="18" /></span>
                </div>
                <strong class="sales-figure">{{
                    number.format(report.sales_count)
                }}</strong>
                <p class="sales-caption">
                    Ticket promedio
                    {{
                        report.average_ticket_minor === null
                            ? '—'
                            : money(report.average_ticket_minor)
                    }}
                </p>
            </article>
        </section>

        <div class="sales-analysis">
            <section
                class="sales-card sales-chart-card"
                aria-labelledby="sales-trend-heading"
            >
                <div class="sales-chart-header">
                    <div>
                        <h2 id="sales-trend-heading">Evolución de ventas</h2>
                        <p>Importe confirmado antes de devoluciones</p>
                    </div>
                    <span class="sales-period">{{
                        period(report.from, report.to)
                    }}</span>
                </div>
                <template v-if="chartPeak > 0">
                    <div
                        class="sales-chart-selection"
                        v-if="selectedPoint"
                        aria-live="polite"
                    >
                        <strong>{{ money(selectedPoint.amount_minor) }}</strong
                        ><span>{{
                            period(selectedPoint.from, selectedPoint.to)
                        }}</span>
                    </div>
                    <div class="sales-chart">
                        <div class="sales-axis" aria-hidden="true">
                            <span>{{ money(chartPeak) }}</span
                            ><span>{{ money(Math.round(chartPeak / 2)) }}</span
                            ><span>{{ money(0) }}</span>
                        </div>
                        <div class="sales-bars">
                            <button
                                v-for="(point, index) in report.trend"
                                :key="point.from"
                                type="button"
                                class="sales-bar"
                                :aria-label="`${period(point.from, point.to)}, ${money(point.amount_minor)}`"
                                :aria-pressed="point === selectedPoint"
                                @click="activeBar = index"
                                @focus="activeBar = index"
                            >
                                <span
                                    class="sales-bar-fill"
                                    :style="{
                                        height: `${(point.amount_minor / chartPeak) * 100}%`,
                                    }"
                                ></span>
                                <span
                                    class="sales-bar-label"
                                    aria-hidden="true"
                                    >{{ barLabel(point.from) }}</span
                                >
                            </button>
                        </div>
                    </div>
                </template>
                <div v-else class="sales-empty">
                    <BarChart3 :size="26" /><strong
                        >Sin importes de ventas en este período</strong
                    >
                    <span>Elige otras fechas para consultar su actividad.</span>
                </div>
            </section>

            <section
                class="sales-card sales-ratios"
                aria-labelledby="sales-ratios-heading"
            >
                <h2 id="sales-ratios-heading">Porcentajes del período</h2>
                <div class="sales-ratio">
                    <div>
                        <h3>Margen bruto</h3>
                        <p>
                            {{
                                report.missing_cost_lines
                                    ? 'Hay movimientos con costo pendiente.'
                                    : 'Utilidad bruta sobre ventas netas.'
                            }}
                        </p>
                    </div>
                    <strong>{{
                        margin === null ? '—' : percentage.format(margin)
                    }}</strong>
                </div>
                <div class="sales-ratio">
                    <div>
                        <h3>Devoluciones</h3>
                        <p>Importe devuelto sobre ventas brutas del período.</p>
                    </div>
                    <strong>{{
                        returnRate === null
                            ? '—'
                            : percentage.format(returnRate)
                    }}</strong>
                </div>
                <div class="sales-payments">
                    <h3>Cobros por método</h3>
                    <p v-if="!report.payment_mix.length" class="sales-caption">
                        Aún no hay cobros verificados en este período.
                    </p>
                    <div
                        v-for="payment in report.payment_mix"
                        :key="payment.method"
                        class="sales-payment"
                    >
                        <div>
                            <span>{{ state(payment.method) }}</span
                            ><strong>{{
                                percentage.format(
                                    payment.amount_minor /
                                        report.collections_minor,
                                )
                            }}</strong>
                        </div>
                        <progress
                            :aria-label="`Proporción de cobros en ${state(payment.method)}`"
                            :value="payment.amount_minor"
                            :max="report.collections_minor"
                        ></progress>
                        <span class="sales-caption">{{
                            money(payment.amount_minor)
                        }}</span>
                    </div>
                </div>
            </section>
        </div>

        <div class="sales-breakdowns">
            <section
                class="sales-card sales-breakdown"
                aria-labelledby="sales-channels"
            >
                <h2 id="sales-channels">Ventas por canal</h2>
                <p class="sales-caption">
                    Importe confirmado antes de devoluciones, incluida la
                    entrega.
                </p>
                <p v-if="!report.channel_mix.length" class="sales-caption">
                    No hay ventas en este período.
                </p>
                <div v-else class="sales-payments">
                    <div
                        v-for="item in report.channel_mix"
                        :key="item.channel"
                        class="sales-payment"
                    >
                        <div>
                            <strong>{{ channelName(item.channel) }}</strong>
                            <strong>{{
                                channelTotal > 0
                                    ? percentage.format(
                                          item.amount_minor / channelTotal,
                                      )
                                    : '—'
                            }}</strong>
                        </div>
                        <progress
                            v-if="channelTotal > 0"
                            :aria-label="`Proporción de ventas de ${channelName(item.channel)}`"
                            :value="item.amount_minor"
                            :max="channelTotal"
                        />
                        <span class="sales-caption"
                            >{{ money(item.amount_minor) }} ·
                            {{ number.format(item.sales_count) }} ventas</span
                        >
                    </div>
                </div>
            </section>
            <section
                class="sales-card sales-breakdown"
                aria-labelledby="sales-products"
            >
                <h2 id="sales-products">Productos con más ventas</h2>
                <p class="sales-caption">
                    Los 10 primeros por importe después de descuentos, antes de
                    devoluciones y sin entrega.
                </p>
                <p v-if="!report.top_products.length" class="sales-caption">
                    No hay productos vendidos en este período.
                </p>
                <ol v-else class="sales-products">
                    <li
                        v-for="product in report.top_products"
                        :key="product.id"
                    >
                        <div>
                            <strong>{{ product.name }}</strong>
                            <span class="sales-caption"
                                >{{ product.sku }} ·
                                {{ number.format(product.units) }}
                                unidades</span
                            >
                        </div>
                        <div>
                            <strong>{{ money(product.amount_minor) }}</strong>
                            <span class="sales-caption">
                                {{
                                    product.profit_minor === null
                                        ? 'Costo incompleto'
                                        : product.amount_minor > 0
                                          ? `Margen ${percentage.format(product.profit_minor / product.amount_minor)}`
                                          : 'Sin margen porcentual'
                                }}
                            </span>
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <details class="sales-card sales-details">
            <summary>
                <span
                    ><strong>Detalle financiero</strong
                    ><small
                        >Costos, descuentos, deuda y resultado gerencial</small
                    ></span
                ><ChevronDown :size="20" />
            </summary>
            <dl>
                <div v-for="[label, value] in financialDetails" :key="label">
                    <dt>{{ label }}</dt>
                    <dd>
                        {{ value === null ? 'Costo incompleto' : money(value) }}
                    </dd>
                </div>
            </dl>
            <p>
                Las devoluciones y cobros se cuentan por fecha de registro. La
                deuda corresponde al saldo actual de todas las ventas. La
                utilidad bruta se calcula antes de gastos y ajustes tributarios.
            </p>
        </details>
    </div>
</template>

<style scoped>
.sales-dashboard {
    display: grid;
    gap: 18px;
    min-width: 0;
    color: #172f25;
}
.sales-hero {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(300px, 0.84fr);
    align-items: center;
    gap: 24px;
    padding: clamp(20px, 3vw, 36px);
    border-radius: 24px;
    background:
        radial-gradient(ellipse at 96% 0%, #3b6852 0%, transparent 56%),
        linear-gradient(125deg, #112e22, #214a38);
    color: #f8fcf8;
    overflow-wrap: anywhere;
}
.sales-eyebrow {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #cbe9bd;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
}
.sales-hero h1 {
    margin-top: 10px;
    font-size: clamp(2rem, 3.7vw, 3.25rem);
    line-height: 1.04;
}
.sales-hero-copy {
    margin-top: 12px;
    color: #daece1;
    font-size: 14px;
}
.sales-orders-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 18px;
    color: #fff;
    font-size: 13px;
    font-weight: 650;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.sales-filter {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 11px;
    padding: 16px;
    border: 1px solid #b4cdb9;
    border-radius: 16px;
    background: #f6faf4;
    color: #183327;
}
.sales-filter-title {
    display: flex;
    grid-column: 1 / -1;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}
.sales-filter label {
    display: grid;
    gap: 4px;
    min-width: 0;
    font-size: 12px;
    font-weight: 600;
}
.sales-filter input {
    width: 100%;
    min-width: 0;
    padding: 7px 8px;
    border: 1px solid #cddcce;
    border-radius: 7px;
    background: #fff;
    color: #172f25;
    font-size: 13px;
}
.sales-filter button {
    display: flex;
    grid-column: 1 / -1;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 9px 12px;
    border-radius: 8px;
    background: #214e39;
    color: #fff;
    font-size: 13px;
    font-weight: 650;
}
.sales-filter button:hover {
    background: #123d2b;
}
.sales-date-error {
    grid-column: 1 / -1;
    color: #922f24;
    font-size: 12px;
}
.sales-metrics {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
}
.sales-card {
    min-width: 0;
    border: 1px solid #dce6dc;
    border-radius: 17px;
    background: #fff;
    box-shadow: 0 4px 18px #1b39240a;
}
.sales-metric {
    display: grid;
    align-content: start;
    gap: 6px;
    padding: clamp(14px, 2vw, 22px);
}
.sales-metric-primary {
    border-color: #c6d9c4;
    background: #f6fbf1;
}
.sales-metric-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.sales-metric-heading h2 {
    color: #355847;
    font-size: 13px;
    font-weight: 650;
}
.sales-metric-heading > span {
    display: grid;
    width: 33px;
    height: 33px;
    flex: 0 0 33px;
    place-items: center;
    border-radius: 9px;
    background: #e8f2e5;
    color: #2c5c41;
}
.sales-figure {
    font-size: clamp(1.45rem, 2.8vw, 2.2rem);
    font-weight: 700;
    letter-spacing: -0.04em;
    overflow-wrap: anywhere;
    font-variant-numeric: tabular-nums;
}
.sales-caption {
    color: #52695e;
    font-size: 12px;
    line-height: 1.45;
}
.sales-change {
    display: flex;
    align-items: center;
    gap: 4px;
    color: #275638;
    font-size: 12px;
    font-weight: 700;
}
.sales-change svg {
    flex-shrink: 0;
}
.sales-change-down {
    color: #a03931;
}
.sales-analysis {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(250px, 1fr);
    gap: 14px;
}
.sales-chart-card,
.sales-ratios {
    padding: clamp(15px, 2.4vw, 24px);
}
.sales-chart-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.sales-chart-header h2,
.sales-ratios h2 {
    font-size: 17px;
    font-weight: 700;
}
.sales-chart-header p {
    margin-top: 4px;
    color: #52695e;
    font-size: 12px;
}
.sales-period {
    padding: 5px 9px;
    border-radius: 6px;
    background: #edf3ed;
    color: #355847;
    font-size: 11px;
    font-weight: 650;
    white-space: nowrap;
}
.sales-chart-selection {
    display: flex;
    align-items: baseline;
    flex-wrap: wrap;
    gap: 8px;
    margin-block: 14px 8px;
}
.sales-chart-selection strong {
    font-size: 21px;
    font-weight: 700;
}
.sales-chart-selection span {
    color: #52695e;
    font-size: 12px;
}
.sales-chart {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    gap: 8px;
    padding-bottom: 24px;
}
.sales-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: end;
    color: #52695e;
    font-size: 10px;
    white-space: nowrap;
}
.sales-bars {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: minmax(0, 1fr);
    gap: 7px;
    height: 145px;
    border-block: 1px solid #dce6dc;
    background: linear-gradient(#dce6dc, #dce6dc) center / 100% 1px no-repeat;
}
.sales-bar {
    position: relative;
    display: flex;
    min-width: 0;
    height: 100%;
    align-items: end;
    justify-content: center;
    color: #355847;
}
.sales-bar-fill {
    width: min(66%, 34px);
    min-height: 2px;
    border-radius: 6px 6px 0 0;
    background: #a7c8ab;
}
.sales-bar[aria-pressed='true'] .sales-bar-fill {
    background: #367651;
}
.sales-bar-label {
    position: absolute;
    top: calc(100% + 6px);
    max-width: 100%;
    overflow: hidden;
    color: #52695e;
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.sales-empty {
    display: grid;
    min-height: 210px;
    align-content: center;
    justify-items: center;
    gap: 7px;
    text-align: center;
    color: #52695e;
    font-size: 13px;
}
.sales-empty svg {
    color: #417757;
}
.sales-ratio {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding-block: 12px;
    border-bottom: 1px solid #e8eee8;
}
.sales-ratios h3 {
    font-size: 13px;
    font-weight: 700;
}
.sales-ratio p {
    margin-top: 3px;
    color: #52695e;
    font-size: 11px;
    line-height: 1.4;
}
.sales-ratio > strong {
    font-size: 18px;
    font-weight: 700;
    white-space: nowrap;
}
.sales-payments {
    display: grid;
    gap: 7px;
    margin-top: 13px;
}
.sales-payment > div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
}
.sales-payment progress {
    display: block;
    width: 100%;
    height: 7px;
    margin-block: 4px 2px;
    border: 0;
    border-radius: 8px;
    background: #e8f0e8;
    color: #367651;
}
.sales-payment progress::-webkit-progress-bar {
    border-radius: 8px;
    background: #e8f0e8;
}
.sales-payment progress::-webkit-progress-value {
    border-radius: 8px;
    background: #367651;
}
.sales-payment progress::-moz-progress-bar {
    border-radius: 8px;
    background: #367651;
}
.sales-breakdowns {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.5fr);
    gap: 18px;
}
.sales-breakdown {
    padding: 18px;
    overflow-wrap: anywhere;
}
.sales-breakdown h2 {
    margin-bottom: 4px;
    font-size: 17px;
    font-weight: 700;
}
.sales-products {
    margin-top: 12px;
}
.sales-products li {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(85px, auto);
    gap: 10px;
    padding-block: 9px;
    border-top: 1px solid #e8eee8;
    font-size: 13px;
}
.sales-products li > div:last-child {
    text-align: right;
}
.sales-products strong,
.sales-products .sales-caption {
    display: block;
}
.sales-details {
    padding: 0 18px;
}
.sales-details summary {
    display: flex;
    cursor: pointer;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding-block: 14px;
    list-style: none;
}
.sales-details summary::-webkit-details-marker {
    display: none;
}
.sales-details summary strong,
.sales-details summary small {
    display: block;
}
.sales-details summary small,
.sales-details > p {
    margin-top: 3px;
    color: #52695e;
    font-size: 12px;
}
.sales-details[open] summary svg {
    transform: rotate(180deg);
}
.sales-details dl {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 18px;
}
.sales-details dl > div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding-block: 7px;
    border-top: 1px solid #e8eee8;
    font-size: 13px;
}
.sales-details dd {
    flex-shrink: 0;
    font-weight: 650;
}
.sales-details > p {
    padding-block: 10px 14px;
}
.sales-dashboard :is(button, a, summary, input):focus-visible {
    outline: 2px solid #438454;
    outline-offset: 3px;
}
@media (max-width: 830px) {
    .sales-breakdowns {
        grid-template-columns: minmax(0, 1fr);
    }
    .sales-hero,
    .sales-analysis {
        grid-template-columns: 1fr;
    }
}
@media (max-width: 575px) {
    .sales-metrics,
    .sales-details dl {
        grid-template-columns: 1fr;
    }
    .sales-chart {
        gap: 5px;
    }
    .sales-bars {
        gap: 3px;
    }
}
@media (max-width: 370px) {
    .sales-filter {
        grid-template-columns: 1fr;
    }
}
</style>

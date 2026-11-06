export type BusinessAccess = {
    name: string;
    slug: string;
    role: string;
    storeUrl: string;
    manageUrl: string;
    requiresMfa: boolean;
};

export type SalesReport = {
    from: string;
    to: string;
    gross_minor: number;
    discount_minor: number;
    returns_minor: number;
    delivery_minor: number;
    net_minor: number;
    collections_minor: number;
    refunds_minor: number;
    debt_minor: number;
    cost_minor: number;
    gross_profit_minor: number | null;
    expenses_minor: number;
    inventory_loss_minor: number;
    management_result_minor: number | null;
    missing_cost_lines: number;
    sales_count: number;
    average_ticket_minor: number | null;
    previous: { from: string; to: string; net_minor: number };
    trend: { from: string; to: string; amount_minor: number }[];
    payment_mix: { method: string; amount_minor: number }[];
    channel_mix: {
        channel: string;
        amount_minor: number;
        sales_count: number;
    }[];
    top_products: {
        id: number;
        name: string;
        sku: string;
        units: number;
        amount_minor: number;
        profit_minor: number | null;
    }[];
};

export type PageLink = { url: string | null; label: string; active: boolean };

export type OrderFilters = Partial<
    Record<
        | 'from'
        | 'to'
        | 'channel'
        | 'order_status'
        | 'delivery_status'
        | 'payment_status',
        string | null
    >
>;

export type OrderListItem = {
    id: string;
    customer_name: string;
    created_at: string;
    channel: string;
    status: string;
    payment_status: string;
    delivery_status: string;
    total_minor: number;
};

<?php

namespace Chargefield\Store\Sales;

/**
 * A model whose sales Studio should know about (a store order, a wholesale order).
 */
interface ReportsToStudio
{
    /**
     * The sale as Studio takes it, or null when there's nothing to report (unpaid,
     * a test checkout, imported history unless $includeImported).
     *
     * Keys: ref, kind (store|wholesale), status (paid|refunded|cancelled),
     * occurred_on (Y-m-d), currency, amount_cents (received: tax and shipping
     * included, less refunds), tax_cents, country (ISO-2), party, description.
     * The store key is added by the reporter. Never include customer details.
     *
     * @return array<string, mixed>|null
     */
    public function studioSale(bool $includeImported = false): ?array;

    /** @return array<int, string> attributes whose change means the sale must be re-sent */
    public function studioSaleWatched(): array;
}

<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScheduledPrices
{
    public function promotions(Builder $query): void
    {
        $query->where(function ($query) {
            $query->where(fn ($q) => $q->whereNotNull('compare_at_minor')->whereColumn('compare_at_minor', '>', 'price_minor'))
                ->orWhereExists(function ($rules) {
                    $rules->selectRaw('1')->from('price_rules')->whereColumn('price_rules.tenant_id', 'products.tenant_id')->where('enabled', true)->where('starts_at', '<=', now())->where('ends_at', '>', now())
                        ->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', 'web'))
                        ->where(fn ($q) => $q->whereNull('product_id')->orWhereColumn('product_id', 'products.id'))
                        ->where(fn ($q) => $q->whereNull('price_rules.category')->orWhere('price_rules.category', '')->orWhereColumn('price_rules.category', 'products.category'));
                });
        });
    }

    /** @return Collection<int,\stdClass> */
    public function rules(int $tenant, string $channel): Collection
    {
        return DB::table('price_rules')->where('tenant_id', $tenant)->where('enabled', true)->where('starts_at', '<=', now())->where('ends_at', '>', now())->where(fn ($q) => $q->whereNull('channel')->orWhere('channel', $channel))->get();
    }

    /** @param Collection<int,\stdClass> $rules */
    public function price(\stdClass $product, Collection $rules): int
    {
        $price = (int) $product->price_minor;
        foreach ($rules as $rule) {
            if (($rule->product_id === null || (int) $rule->product_id === (int) $product->id) && (! $rule->category || $rule->category === ($product->category ?? ''))) {
                $price = min($price, (int) $product->price_minor - intdiv((int) $product->price_minor * $rule->percent + 50, 100));
            }
        }

        return $price;
    }

    /** @param Collection<int,\stdClass> $products */
    public function decorate(int $tenant, Collection $products, string $channel): void
    {
        $rules = $this->rules($tenant, $channel);
        foreach ($products as $product) {
            $price = $this->price($product, $rules);
            if ($price < $product->price_minor) {
                $product->compare_at_minor = max((int) ($product->compare_at_minor ?? 0), (int) $product->price_minor);
                $product->price_minor = $price;
            }
        }
    }
}

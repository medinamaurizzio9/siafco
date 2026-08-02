<?php

namespace App\Services\Store;

use App\Models\Affiliate;
use App\Models\StoreCoupon;
use App\Models\StoreCouponUsage;
use App\Services\StoreCouponCodeService;
use Illuminate\Validation\ValidationException;

class StoreCouponService
{
    public function findValidCoupon(?string $code, Affiliate $affiliate, int $subtotalCents, array $lines): ?array
    {
        if (! $code) {
            return null;
        }

        $codeService = app(StoreCouponCodeService::class);
        $hash = $codeService->hash($code);
        $coupon = StoreCoupon::query()
            ->with(['targets'])
            ->where('code_hash', $hash)
            ->lockForUpdate()
            ->first();

        if (! $coupon || ! $coupon->active) {
            throw ValidationException::withMessages(['coupon' => 'El cupón no está disponible.']);
        }

        $now = now();
        if (($coupon->starts_at && $coupon->starts_at->isAfter($now)) || ($coupon->ends_at && $coupon->ends_at->isBefore($now))) {
            throw ValidationException::withMessages(['coupon' => 'El cupón no está vigente.']);
        }

        if ($subtotalCents < StoreMoney::cents($coupon->minimum_amount)) {
            throw ValidationException::withMessages(['coupon' => 'El pedido no alcanza la compra mínima del cupón.']);
        }

        if ($coupon->global_limit && StoreCouponUsage::query()->where('store_coupon_id', $coupon->id)->count() >= $coupon->global_limit) {
            throw ValidationException::withMessages(['coupon' => 'El cupón alcanzó su límite global.']);
        }

        if ($coupon->per_affiliate_limit && StoreCouponUsage::query()
            ->where('store_coupon_id', $coupon->id)
            ->where('affiliate_id', $affiliate->id)
            ->count() >= $coupon->per_affiliate_limit) {
            throw ValidationException::withMessages(['coupon' => 'El cupón alcanzó el límite por afiliado.']);
        }

        $eligibleCents = $this->eligibleSubtotal($coupon, $lines);
        if ($eligibleCents <= 0) {
            throw ValidationException::withMessages(['coupon' => 'El cupón no aplica a los productos seleccionados.']);
        }

        $discountCents = $this->discount($coupon, $eligibleCents);

        return [
            'coupon' => $coupon,
            'discount_cents' => $discountCents,
            'eligible_cents' => $eligibleCents,
            'snapshot' => [
                'code_hint' => $coupon->code_hint,
                'type' => $coupon->type,
                'value' => $coupon->value,
                'discount' => StoreMoney::decimal($discountCents),
            ],
        ];
    }

    private function eligibleSubtotal(StoreCoupon $coupon, array $lines): int
    {
        if ($coupon->targets->isEmpty()) {
            return array_sum(array_column($lines, 'line_total_cents'));
        }

        $productIds = $coupon->targets->pluck('store_product_id')->filter()->map(fn ($id) => (int) $id)->all();
        $categoryIds = $coupon->targets->pluck('store_category_id')->filter()->map(fn ($id) => (int) $id)->all();

        return collect($lines)
            ->filter(fn ($line) => in_array((int) $line['product']->id, $productIds, true)
                || in_array((int) $line['product']->store_category_id, $categoryIds, true))
            ->sum('line_total_cents');
    }

    private function discount(StoreCoupon $coupon, int $eligibleCents): int
    {
        if ($coupon->type === StoreCoupon::TYPE_PERCENTAGE) {
            $basisPoints = StoreMoney::cents($coupon->value);

            return min($eligibleCents, intdiv($eligibleCents * $basisPoints, 10000));
        }

        return min($eligibleCents, StoreMoney::cents($coupon->value));
    }
}

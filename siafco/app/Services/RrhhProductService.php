<?php

namespace App\Services;

use App\Models\RrhhProduct;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RrhhProductService
{
    public function create(array $data, User $actor): RrhhProduct
    {
        return DB::transaction(function () use ($data, $actor) {
            $product = RrhhProduct::create([...$data, 'currency' => 'BOB', 'created_by' => $actor->id, 'updated_by' => $actor->id]);
            AuditService::record('rrhh_product_created', $product, ['product_code' => $product->code]);

            return $product;
        });
    }

    public function update(RrhhProduct $product, array $data, User $actor): RrhhProduct
    {
        return DB::transaction(function () use ($product, $data, $actor) {
            $product->update([...$data, 'currency' => 'BOB', 'updated_by' => $actor->id]);
            AuditService::record('rrhh_product_updated', $product, ['product_code' => $product->code]);

            return $product->fresh();
        });
    }

    public function toggle(RrhhProduct $product, User $actor): void
    {
        DB::transaction(function () use ($product, $actor) {
            $locked = RrhhProduct::query()->lockForUpdate()->findOrFail($product->id);
            $locked->update(['is_active' => ! $locked->is_active, 'updated_by' => $actor->id]);
            AuditService::record($locked->is_active ? 'rrhh_product_activated' : 'rrhh_product_deactivated', $locked, ['product_code' => $locked->code]);
        });
    }
}

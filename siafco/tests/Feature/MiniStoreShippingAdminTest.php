<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\StoreShippingRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiniStoreShippingAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipping_rates_can_be_created_updated_listed_and_soft_deleted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.store.shipping-rates.store'), [
            'scope' => StoreShippingRate::SCOPE_CITY,
            'department' => ' la paz ',
            'city' => ' el alto ',
            'zone' => 'zona ignorada',
            'amount' => '12.50',
            'currency' => 'USD',
            'active' => '1',
            'priority' => 5,
        ])->assertRedirect(route('admin.store.shipping-rates.index'));

        $rate = StoreShippingRate::firstOrFail();
        $this->assertSame(StoreShippingRate::SCOPE_CITY, $rate->scope);
        $this->assertSame('LA PAZ', $rate->department);
        $this->assertSame('EL ALTO', $rate->city);
        $this->assertNull($rate->zone);
        $this->assertSame('BOB', $rate->currency);
        $this->assertSame('12.50', $rate->amount);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mini_tienda.tarifa_envio_creada']);

        $this->actingAs($admin)->put(route('admin.store.shipping-rates.update', $rate), [
            'scope' => StoreShippingRate::SCOPE_ZONE,
            'department' => 'la paz',
            'city' => 'el alto',
            'zone' => 'rio seco',
            'amount' => '15.00',
            'active' => '0',
            'priority' => 8,
        ])->assertRedirect(route('admin.store.shipping-rates.index'));

        $rate->refresh();
        $this->assertSame(StoreShippingRate::SCOPE_ZONE, $rate->scope);
        $this->assertSame('RIO SECO', $rate->zone);
        $this->assertFalse($rate->active);

        $this->actingAs($admin)->get(route('admin.store.shipping-rates.index', ['search' => 'rio seco']))
            ->assertOk()
            ->assertSee('RIO SECO');

        $this->actingAs($admin)->delete(route('admin.store.shipping-rates.destroy', $rate))
            ->assertRedirect();
        $this->assertSoftDeleted($rate);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mini_tienda.tarifa_envio_desactivada']);
    }

    public function test_duplicate_active_equivalent_shipping_rates_are_rejected(): void
    {
        $admin = $this->admin();
        StoreShippingRate::create([
            'scope' => StoreShippingRate::SCOPE_DEPARTMENT,
            'department' => 'COCHABAMBA',
            'amount' => 10,
            'currency' => 'BOB',
            'active' => true,
            'priority' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.store.shipping-rates.store'), [
            'scope' => StoreShippingRate::SCOPE_DEPARTMENT,
            'department' => ' cochabamba ',
            'amount' => 12,
            'active' => '1',
            'priority' => 2,
        ])->assertSessionHasErrors('scope');

        $this->actingAs($admin)->post(route('admin.store.shipping-rates.store'), [
            'scope' => StoreShippingRate::SCOPE_DEPARTMENT,
            'department' => ' cochabamba ',
            'amount' => 12,
            'active' => '0',
            'priority' => 2,
        ])->assertRedirect();

        $this->assertSame(2, StoreShippingRate::withTrashed()->count());
    }

    public function test_probe_uses_most_specific_active_rate_and_consulta_cannot_manage(): void
    {
        $consulta = User::factory()->create(['role' => 'consulta', 'user_type' => 'internal']);
        $admin = $this->admin();
        StoreShippingRate::create(['scope' => StoreShippingRate::SCOPE_NATIONAL, 'amount' => 30, 'currency' => 'BOB', 'active' => true, 'priority' => 1]);
        StoreShippingRate::create(['scope' => StoreShippingRate::SCOPE_DEPARTMENT, 'department' => 'SANTA CRUZ', 'amount' => 20, 'currency' => 'BOB', 'active' => true, 'priority' => 1]);
        StoreShippingRate::create(['scope' => StoreShippingRate::SCOPE_CITY, 'department' => 'SANTA CRUZ', 'city' => 'SANTA CRUZ DE LA SIERRA', 'amount' => 15, 'currency' => 'BOB', 'active' => true, 'priority' => 1]);
        StoreShippingRate::create(['scope' => StoreShippingRate::SCOPE_ZONE, 'department' => 'SANTA CRUZ', 'city' => 'SANTA CRUZ DE LA SIERRA', 'zone' => 'EQUIPETROL', 'amount' => 8, 'currency' => 'BOB', 'active' => true, 'priority' => 1]);

        $this->actingAs($consulta)->get(route('admin.store.shipping-rates.index', [
            'probe_department' => 'santa cruz',
            'probe_city' => 'santa cruz de la sierra',
            'probe_zone' => 'equipetrol',
        ]))->assertOk()->assertSee('8.00');

        $this->actingAs($consulta)->get(route('admin.store.shipping-rates.create'))->assertForbidden();

        $metadata = json_encode(AuditLog::query()->pluck('metadata'));
        $this->assertStringNotContainsString('password', $metadata);
        $this->actingAs($admin)->get(route('admin.store.shipping-rates.index'))->assertOk()->assertSee('Nacional');
    }

    public function test_shipping_rate_requires_location_fields_for_specific_scopes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.store.shipping-rates.store'), [
            'scope' => StoreShippingRate::SCOPE_ZONE,
            'department' => 'La Paz',
            'city' => '',
            'zone' => '',
            'amount' => '10.00',
            'active' => '1',
            'priority' => 1,
        ])->assertSessionHasErrors(['city', 'zone']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrador', 'user_type' => 'internal']);
    }
}

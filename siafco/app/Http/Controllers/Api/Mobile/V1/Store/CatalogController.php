<?php

namespace App\Http\Controllers\Api\Mobile\V1\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Mobile\V1\StoreCatalogRequest;
use App\Http\Requests\Api\Mobile\V1\StoreQuoteRequest;
use App\Http\Resources\Api\Mobile\V1\StoreProductResource;
use App\Http\Resources\Api\Mobile\V1\StoreQuoteResource;
use App\Http\Responses\MobileApiResponse;
use App\Models\InstitutionalSetting;
use App\Models\StoreCategory;
use App\Models\StoreProduct;
use App\Models\StoreShippingRate;
use App\Models\StoreSetting;
use App\Services\Store\StorePricingService;
use App\Support\StoreAvailabilityStatus;
use Illuminate\Support\Facades\Storage;

class CatalogController extends Controller
{
    public function index(StoreCatalogRequest $request)
    {
        $filters = $request->validated();
        $products = $this->productQuery()
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            }))
            ->when($filters['category'] ?? null, fn ($query, $slug) => $query->whereHas('category', fn ($query) => $query->where('slug', $slug)))
            ->when(array_key_exists('featured', $filters) && $filters['featured'] !== null, fn ($query) => $query->where('featured', (bool) $filters['featured']))
            ->when($filters['availability'] ?? null, fn ($query, $status) => $query->where('availability_status', $status))
            ->orderByDesc('featured')
            ->orderBy('order')
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 15);

        $featured = $this->productQuery()
            ->where('featured', true)
            ->orderBy('order')
            ->orderBy('name')
            ->limit(6)
            ->get();

        return MobileApiResponse::success([
            'settings' => $this->settings(),
            'featured' => StoreProductResource::collection($featured)->resolve(),
            'categories' => StoreCategory::query()
                ->where('active', true)
                ->orderBy('order')->orderBy('name')
                ->get(['slug', 'name'])
                ->map(fn ($category) => ['slug' => $category->slug, 'name' => $category->name])
                ->all(),
            'products' => StoreProductResource::collection($products->getCollection())->resolve(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ], 'Catalogo de tienda disponible.');
    }

    public function show(string $publicCode)
    {
        $product = $this->productQuery()
            ->with(['variants' => fn ($query) => $query->active()->orderBy('order')->orderBy('name')])
            ->where('public_code', $publicCode)
            ->first();

        if (! $product) {
            return MobileApiResponse::error('Producto no encontrado.', 404);
        }

        return MobileApiResponse::success([
            'product' => StoreProductResource::make($product)->resolve(),
        ], 'Producto disponible.');
    }

    public function quote(StoreQuoteRequest $request, StorePricingService $pricing)
    {
        $payload = $request->quotePayload();
        $quote = $pricing->quote(
            $request->user()->affiliate,
            $payload['items'],
            $payload['delivery'],
            $payload['coupon_code'],
        );

        return MobileApiResponse::success([
            'quote' => StoreQuoteResource::make($quote)->resolve(),
        ], 'Cotizacion calculada.');
    }

    public function deliveryDestinations()
    {
        $rates = StoreShippingRate::query()
            ->active()
            ->where(function ($query): void {
                $query->whereNotNull('department')
                    ->orWhereNotNull('city')
                    ->orWhereNotNull('zone');
            })
            ->orderBy('department')
            ->orderBy('city')
            ->orderBy('zone')
            ->get(['department', 'city', 'zone']);

        $destinations = $rates
            ->groupBy('department')
            ->filter(fn ($rates, $department) => filled($department))
            ->map(function ($departmentRates, string $department): array {
                $cities = $departmentRates
                    ->filter(fn ($rate) => filled($rate->city))
                    ->groupBy('city')
                    ->map(function ($cityRates, string $city): array {
                        $zones = $cityRates
                            ->pluck('zone')
                            ->filter()
                            ->unique()
                            ->sort()
                            ->values()
                            ->map(fn (string $zone): array => ['zone' => $zone])
                            ->all();

                        return [
                            'city' => $city,
                            'zones' => $zones,
                        ];
                    })
                    ->sortKeys()
                    ->values()
                    ->all();

                return [
                    'department' => $department,
                    'cities' => $cities,
                ];
            })
            ->sortKeys()
            ->values()
            ->all();

        return MobileApiResponse::success($destinations);
    }

    private function productQuery()
    {
        return StoreProduct::query()
            ->with(['category', 'images' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('order')])
            ->visible()
            ->whereHas('category', fn ($query) => $query->where('active', true));
    }

    private function settings(): array
    {
        $store = StoreSetting::current();
        $institution = InstitutionalSetting::current();

        return [
            'currency' => $store->default_currency,
            'pickup_enabled' => (bool) $store->pickup_enabled,
            'shipping_enabled' => (bool) $store->shipping_enabled,
            'pickup_instructions' => $store->pickup_instructions,
            'shipping_instructions' => $store->shipping_instructions,
            'payment' => [
                'qr_url' => $institution->paymentQrUrl(),
                'bank' => $institution->payment_bank,
                'holder' => $institution->payment_holder,
                'account' => $institution->payment_account,
                'instructions' => $institution->payment_instructions,
            ],
            'whatsapp_enabled' => (bool) $store->whatsapp_enabled,
        ];
    }
}

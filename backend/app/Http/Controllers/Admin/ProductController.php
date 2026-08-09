<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncProviderProductsJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\Provider;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\PricingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly PricingService $pricing,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->only(['category_id', 'provider_id', 'brand', 'search', 'is_active']);

        return view('admin.products.index', [
            'products' => $this->products->paginate($filters, 30, ['category', 'provider']),
            'categories' => Category::active()->get(),
            'providers' => Provider::orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product->load(['category', 'provider']),
            'categories' => Category::active()->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['required', 'exists:categories,id'],
            'margin_type' => ['required', Rule::in(['fixed', 'percent'])],
            'margin_value' => ['required', 'numeric', 'min:0'],
            'admin_fee' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Harga jual selalu turunan dari harga modal + margin,
        // agar tidak pernah ada produk yang dijual di bawah modal.
        $validated['sell_price'] = $this->pricing->applyMargin(
            (float) $product->base_price,
            $validated['margin_type'],
            (float) $validated['margin_value'],
        );

        $product->update($validated);

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Produk {$product->name} diperbarui.");
    }

    /** Ubah margin banyak produk sekaligus (per kategori atau per brand). */
    public function bulkMargin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'brand' => ['nullable', 'string', 'max:60'],
            'margin_type' => ['required', Rule::in(['fixed', 'percent'])],
            'margin_value' => ['required', 'numeric', 'min:0'],
        ]);

        $query = Product::query()
            ->when($validated['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($validated['brand'] ?? null, fn ($q, $v) => $q->where('brand', $v));

        $affected = 0;
        $query->chunkById(200, function ($products) use ($validated, &$affected) {
            foreach ($products as $product) {
                $product->update([
                    'margin_type' => $validated['margin_type'],
                    'margin_value' => $validated['margin_value'],
                    'sell_price' => $this->pricing->applyMargin(
                        (float) $product->base_price,
                        $validated['margin_type'],
                        (float) $validated['margin_value'],
                    ),
                ]);
                $affected++;
            }
        });

        return back()->with('success', "Margin {$affected} produk berhasil diperbarui.");
    }

    public function toggle(Product $product): RedirectResponse
    {
        $product->update(['is_active' => ! $product->is_active]);

        return back()->with('success', $product->is_active
            ? "Produk {$product->name} diaktifkan."
            : "Produk {$product->name} dinonaktifkan.");
    }

    public function sync(Request $request): RedirectResponse
    {
        SyncProviderProductsJob::dispatch($request->input('provider_id'));

        return back()->with('success', 'Sinkronisasi produk dijalankan di latar belakang.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Banner;
use App\Models\Promo;
use App\Models\Provider;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => Setting::orderBy('group')->orderBy('key')->get()->groupBy('group'),
            'providers' => Provider::orderBy('priority')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate(['settings' => ['required', 'array']]);

        foreach ($request->input('settings') as $key => $value) {
            $setting = Setting::where('key', $key)->first();

            if ($setting !== null) {
                $setting->update(['value' => is_array($value) ? json_encode($value) : (string) $value]);
            }
        }

        return back()->with('success', 'Pengaturan disimpan.');
    }

    /** Simpan kredensial provider dalam bentuk terenkripsi. */
    public function updateProvider(Request $request, Provider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'base_url' => ['required', 'url'],
            'is_active' => ['boolean'],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
            'credentials' => ['array'],
            'credentials.*' => ['nullable', 'string', 'max:255'],
        ]);

        $provider->fill([
            'base_url' => $validated['base_url'],
            'is_active' => $request->boolean('is_active'),
            'priority' => $validated['priority'],
        ]);

        // Field kredensial yang dikosongkan berarti "jangan ubah",
        // sehingga admin tidak perlu mengetik ulang API key.
        $credentials = $provider->credentials();
        foreach ($validated['credentials'] ?? [] as $key => $value) {
            if (filled($value)) {
                $credentials[$key] = $value;
            }
        }
        $provider->setCredentials($credentials);
        $provider->save();

        ActivityLog::record('provider.updated', $provider, ['fields' => array_keys($validated['credentials'] ?? [])]);

        return back()->with('success', "Konfigurasi provider {$provider->name} disimpan.");
    }

    public function banners(): View
    {
        return view('admin.settings.banners', ['banners' => Banner::orderBy('sort_order')->get()]);
    }

    public function storeBanner(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'action_type' => ['required', Rule::in(['none', 'url', 'category', 'product', 'promo'])],
            'action_value' => ['nullable', 'string', 'max:191'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['image_path'] = $request->file('image')->store('banners', 'public');
        unset($validated['image']);

        Banner::create($validated + ['is_active' => true]);

        return back()->with('success', 'Banner ditambahkan.');
    }

    public function destroyBanner(Banner $banner): RedirectResponse
    {
        Storage::disk('public')->delete($banner->image_path);
        $banner->delete();

        return back()->with('success', 'Banner dihapus.');
    }

    public function promos(): View
    {
        return view('admin.settings.promos', ['promos' => Promo::latest('id')->paginate(20)]);
    }

    public function storePromo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:promos,code'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'discount_type' => ['required', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'min_transaction' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $validated['code'] = strtoupper($validated['code']);

        Promo::create($validated + ['is_active' => true]);

        return back()->with('success', 'Promo dibuat.');
    }

    public function activityLogs(Request $request): View
    {
        return view('admin.settings.activity-logs', [
            'logs' => ActivityLog::with('user')
                ->when($request->input('action'), fn ($q, $v) => $q->where('action', 'like', "%{$v}%"))
                ->when($request->input('user_id'), fn ($q, $v) => $q->where('user_id', $v))
                ->latest('id')
                ->paginate(50),
            'filters' => $request->only(['action', 'user_id']),
        ]);
    }
}

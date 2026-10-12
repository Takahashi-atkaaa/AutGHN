<?php

namespace App\Http\Controllers;

use App\Models\ShopGhn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopGhnController extends Controller
{
    /**
     * Display a listing of shops.
     */
    public function index(): View
    {
        $shops = ShopGhn::orderBy('created_at', 'desc')->get();
        return view('shop-ghn.index', compact('shops'));
    }

    /**
     * Show the form for creating a new shop.
     */
    public function create(): View
    {
        return view('shop-ghn.create');
    }

    /**
     * Store a newly created shop in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ten_shop' => 'required|string|max:255',
            'ma_shop' => 'required|string|unique:shop_ghn,ma_shop',
            'ma_bi_mat' => 'nullable|string',
            'ten_nguoi_gui' => 'nullable|string|max:255',
            'so_dien_thoai_nguoi_gui' => 'nullable|string|max:20',
            'dia_chi_lay_hang' => 'nullable|string',
            'ma_tinh_lay_hang' => 'nullable|string|max:10',
            'ma_quan_lay_hang' => 'nullable|string|max:10',
            'ma_phuong_lay_hang' => 'nullable|string|max:10',
            'kich_hoat' => 'boolean',
        ]);

        $validated['kich_hoat'] = $request->boolean('kich_hoat', true);

        ShopGhn::create($validated);

        return redirect()->route('shop-ghn.index')
            ->with('success', 'Tao shop thanh cong!');
    }

    /**
     * Display the specified shop.
     */
    public function show(ShopGhn $shopGhn): View
    {
        return view('shop-ghn.show', compact('shopGhn'));
    }

    /**
     * Show the form for editing the specified shop.
     */
    public function edit(ShopGhn $shopGhn): View
    {
        return view('shop-ghn.edit', compact('shopGhn'));
    }

    /**
     * Update the specified shop in storage.
     */
    public function update(Request $request, ShopGhn $shopGhn): RedirectResponse
    {
        $validated = $request->validate([
            'ten_shop' => 'required|string|max:255',
            'ma_shop' => 'required|string|unique:shop_ghn,ma_shop,' . $shopGhn->id,
            'ma_bi_mat' => 'nullable|string',
            'ten_nguoi_gui' => 'nullable|string|max:255',
            'so_dien_thoai_nguoi_gui' => 'nullable|string|max:20',
            'dia_chi_lay_hang' => 'nullable|string',
            'ma_tinh_lay_hang' => 'nullable|string|max:10',
            'ma_quan_lay_hang' => 'nullable|string|max:10',
            'ma_phuong_lay_hang' => 'nullable|string|max:10',
            'kich_hoat' => 'boolean',
        ]);

        $validated['kich_hoat'] = $request->boolean('kich_hoat', true);

        $shopGhn->update($validated);

        return redirect()->route('shop-ghn.index')
            ->with('success', 'Cap nhat shop thanh cong!');
    }

    /**
     * Remove the specified shop from storage.
     */
    public function destroy(ShopGhn $shopGhn): RedirectResponse
    {
        $shopGhn->delete();

        return redirect()->route('shop-ghn.index')
            ->with('success', 'Xoa shop thanh cong!');
    }
}

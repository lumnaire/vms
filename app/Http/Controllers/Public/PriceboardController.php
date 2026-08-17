<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\VendorInventory;
use App\Models\FishType;

class PriceboardController extends Controller
{
    public function index()
    {
        $fishTypes = FishType::where('is_active', true)->get();

        $prices = VendorInventory::with(['vendor.vendorProfile', 'fishType'])
            ->where('status', 'confirmed')
            ->whereDate('entry_date', today())
            ->get();

        return view('public.priceboard', compact('fishTypes', 'prices'));
    }
}
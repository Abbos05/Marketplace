<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompanyController extends Controller
{
  // Контроллер
public function switchToSeller(Request $request)
{
    $user = $request->user();
    $user->role = 'seller';
    $user->save();
    
    return response()->json(['success' => true]);
}
}
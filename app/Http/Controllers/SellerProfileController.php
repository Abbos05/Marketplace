<?php

namespace App\Http\Controllers;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerProfileController extends Controller
{
    /**
     * Store a newly created seller profile.
     */
public function store(Request $request)
{
    \Log::info('Store request:', $request->all());
    
    $request->validate([
        'inn' => 'required|string|min:10|max:12|unique:seller_profiles,inn',
        'shop_name' => 'required|string|max:120',
        'legal_address' => 'nullable|string|max:300',
        'pickup_address' => 'required|string|max:300',
        'description' => 'nullable|string',
        // working_hours НЕ валидируем здесь
    ]);

    $user = $request->user();

    // Проверяем, нет ли уже профиля у пользователя
    if (SellerProfile::where('user_id', $user->id)->exists()) {
        return back()->withErrors(['error' => 'У вас уже есть компания']);
    }

    DB::beginTransaction();

    try {
        // working_hours уже приходит как объект/массив
        $workingHours = $request->working_hours;
        
        // Если это строка - декодируем, если нет - оставляем как есть
        if (is_string($workingHours)) {
            $workingHours = json_decode($workingHours, true);
        }
        
        // Создаем профиль продавца
        $sellerProfile = SellerProfile::create([
            'user_id' => $user->id,
            'inn' => $request->inn,
            'shop_name' => $request->shop_name,
            'legal_address' => $request->legal_address,
            'pickup_address' => $request->pickup_address,
            'description' => $request->description,
            'working_hours' => $workingHours, // Сохраняем как есть (Eloquent преобразует в JSON)
            'rating' => 0,
            'total_sales' => 0,
        ]);

        // Меняем роль пользователя на seller
        $user->role = 'seller';
        $user->save();

        DB::commit();
        
        \Log::info('Seller profile created successfully');

        return redirect()->back()->with('success', 'Компания успешно добавлена! Теперь вы продавец.');

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error creating seller profile: ' . $e->getMessage());
        return back()->withErrors(['error' => 'Ошибка при создании компании: ' . $e->getMessage()]);
    }
}

    /**
     * Get seller profile for current user.
     */
    public function getProfile(Request $request)
    {
        $profile = SellerProfile::where('user_id', $request->user()->id)->first();
        
        return response()->json([
            'success' => true,
            'profile' => $profile
        ]);
    }

    /**
     * Update seller profile.
     */
    public function update(Request $request)
    {
        $profile = SellerProfile::where('user_id', $request->user()->id)->firstOrFail();

        $request->validate([
            'shop_name' => 'sometimes|string|max:120',
            'legal_address' => 'nullable|string|max:300',
            'pickup_address' => 'sometimes|string|max:300',
            'description' => 'nullable|string',
            'working_hours' => 'nullable|array',
        ]);

        $profile->update($request->only([
            'shop_name',
            'legal_address',
            'pickup_address',
            'description',
            'working_hours'
        ]));

        return redirect()->back()->with('success', 'Профиль обновлен');
    }
}
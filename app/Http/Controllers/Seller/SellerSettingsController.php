<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class SellerSettingsController extends Controller
{
    public function index()
    {
        $user    = Auth::user();
        $profile = $user->sellerProfile;

        // Normalise working_hours: ensure all 7 days are present
        $defaultHours = $this->defaultWorkingHours();
        $savedHours   = $profile?->working_hours ?? [];

        if (is_string($savedHours)) {
            $savedHours = json_decode($savedHours, true) ?? [];
        }

        $workingHours = array_merge($defaultHours, $savedHours);

        // Normalise old data saved with 'enabled' key (from buyer profile form) → 'open'
        foreach ($workingHours as $day => &$hours) {
            if (!isset($hours['open']) && isset($hours['enabled'])) {
                $hours['open'] = $hours['enabled'];
                unset($hours['enabled']);
            }
        }
        unset($hours);

        return Inertia::render('Seller/Settings/Index', [
            'user' => [
                'id'     => $user->id,
                'name'   => $user->name,
                'email'  => $user->email,
                'phone'  => $user->phone,
                'avatar' => $user->avatar,
            ],
            'sellerProfile' => $profile ? [
                'shop_name'      => $profile->shop_name,
                'description'    => $profile->description,
                'inn'            => $profile->inn,
                'legal_address'  => $profile->legal_address,
                'pickup_address' => $profile->pickup_address,
                'working_hours'  => $workingHours,
            ] : null,
            'flash' => session()->only(['success', 'error', 'tab']),
        ]);
    }

    public function updateShop(Request $request)
    {
        $user    = Auth::user();
        $profile = $user->sellerProfile;

        if (!$profile) {
            return back()->with('error', 'Профиль магазина не найден.');
        }

        $data = $request->validate([
            'shop_name'      => 'required|string|max:120',
            'description'    => 'nullable|string|max:2000',
            'legal_address'  => 'nullable|string|max:300',
            'pickup_address' => 'required|string|max:300',
            'inn'            => 'nullable|string|max:12|regex:/^\d{10,12}$/',
            'working_hours'  => 'nullable|array',
        ], [
            'inn.regex' => 'ИНН должен содержать 10 или 12 цифр.',
        ]);

        $updateData = [
            'shop_name'      => $data['shop_name'],
            'description'    => $data['description'] ?? null,
            'legal_address'  => $data['legal_address'] ?? null,
            'pickup_address' => $data['pickup_address'],
            'working_hours'  => $data['working_hours'] ?? null,
        ];

        // Allow setting INN only if not already set
        if (empty($profile->inn) && !empty($data['inn'])) {
            $updateData['inn'] = $data['inn'];
        }

        $profile->update($updateData);

        return back()->with(['success' => 'Данные магазина сохранены.', 'tab' => 'shop']);
    }

    public function updateAccount(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'   => 'required|string|max:80',
            'email'  => 'required|email|max:100|unique:users,email,' . $user->id,
            'phone'  => 'nullable|string|max:20|unique:users,phone,' . $user->id,
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ], [
            'email.unique' => 'Этот email уже занят другим пользователем.',
            'phone.unique' => 'Этот телефон уже занят другим пользователем.',
            'avatar.max'   => 'Размер аватара не должен превышать 2 МБ.',
        ]);

        $updateData = [
            'name'  => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ];

        if ($request->hasFile('avatar')) {
            $file     = $request->file('avatar');
            $dir      = public_path('img/avatars');

            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Remove old avatar if it was uploaded here
            if ($user->avatar && str_starts_with($user->avatar, '/img/avatars/')) {
                $oldPath = public_path(ltrim($user->avatar, '/'));
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $filename           = time() . '_' . $user->id . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $filename);
            $updateData['avatar'] = '/img/avatars/' . $filename;
        }

        $user->update($updateData);

        return back()->with(['success' => 'Данные профиля сохранены.', 'tab' => 'account']);
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Пароли не совпадают.',
            'password.min'       => 'Пароль должен содержать не менее 8 символов.',
        ]);

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return back()->withErrors(['current_password' => 'Текущий пароль введён неверно.'])->with('tab', 'security');
        }

        $user->update(['password' => Hash::make($request->input('password'))]);

        return back()->with(['success' => 'Пароль успешно изменён.', 'tab' => 'security']);
    }

    private function defaultWorkingHours(): array
    {
        $days = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $result = [];
        foreach ($days as $day) {
            $isWeekend = in_array($day, ['sat', 'sun']);
            $result[$day] = [
                'open' => !$isWeekend,
                'from' => $isWeekend ? '' : '09:00',
                'to'   => $isWeekend ? '' : '18:00',
            ];
        }
        return $result;
    }
}

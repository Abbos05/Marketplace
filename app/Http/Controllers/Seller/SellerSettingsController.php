<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\AccountDeletionService;
use App\Services\SellerProfileModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class SellerSettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $profile = $user->sellerProfile;

        // Normalise working_hours: ensure all 7 days are present
        $defaultHours = $this->defaultWorkingHours();
        $savedHours = $profile?->working_hours ?? [];

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

        $deletionService = app(AccountDeletionService::class);

        return Inertia::render('Seller/Settings/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar' => $user->avatar,
            ],
            'sellerProfile' => $profile ? [
                'shop_name' => $profile->shop_name,
                'description' => $profile->description,
                'inn' => $profile->inn,
                'legal_address' => $profile->legal_address,
                'pickup_address' => $profile->pickup_address,
                'working_hours' => $workingHours,
                'pending_shop_changes' => app(SellerProfileModerationService::class)->pendingPayload($profile),
            ] : null,
            'flash' => session()->only(['success', 'error', 'tab']),
            'accountDeletion' => $deletionService->accountDeletionInfo($user),
            'canCloseSellerCompany' => (bool) $profile,
        ]);
    }

    public function destroyCompany(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'confirmed' => 'required|accepted',
        ], [
            'confirmed.accepted' => 'Подтвердите удаление компании в диалоге.',
        ]);

        $user = $request->user();

        if ($user->is_blocked) {
            return back()->with('error', 'Аккаунт заблокирован.');
        }

        if (!$user->sellerProfile) {
            return back()->with('error', 'Компания продавца не найдена.');
        }

        app(AccountDeletionService::class)->closeSellerCompany($user, $user);

        return redirect()
            ->route('seller.settings')
            ->with('success', 'Компания продавца закрыта. Товары скрыты с витрины; заказы в пути будут доставлены.');
    }

    public function updateShop(Request $request)
    {
        $user = Auth::user();
        $profile = $user->sellerProfile;

        if (!$profile) {
            return back()->with('error', 'Профиль магазина не найден.');
        }

        $data = $request->validate([
            'shop_name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'legal_address' => 'nullable|string|max:300',
            'pickup_address' => 'required|string|max:300',
            'inn' => 'nullable|string|max:12|regex:/^\d{10,12}$/',
            'working_hours' => 'nullable|array',
        ], [
            'shop_name.required' => 'Необходимо указать название магазина.',
            'shop_name.string' => 'Название магазина должно быть текстом.',
            'shop_name.max' => 'Название магазина не должно превышать 120 символов.',
            'description.string' => 'Описание должно быть текстом.',
            'description.max' => 'Описание не должно превышать 2000 символов.',
            'legal_address.string' => 'Юридический адрес должен быть текстом.',
            'legal_address.max' => 'Юридический адрес не должен превышать 300 символов.',
            'pickup_address.required' => 'Необходимо указать адрес пункта выдачи.',
            'pickup_address.string' => 'Адрес пункта выдачи должен быть текстом.',
            'pickup_address.max' => 'Адрес пункта выдачи не должен превышать 300 символов.',
            'inn.string' => 'ИНН должен быть строкой.',
            'inn.max' => 'ИНН не должен превышать 12 символов.',
            'inn.regex' => 'ИНН должен содержать 10 или 12 цифр.',
            'working_hours.array' => 'График работы должен быть массивом.',
        ]);

        $moderation = app(SellerProfileModerationService::class);
        $changeResult = ['submitted' => false];

        try {
            $changeResult = $moderation->requestShopChanges(
                $profile,
                $data['shop_name'],
                $data['description'] ?? null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->with('tab', 'shop');
        }

        $updateData = [
            'legal_address' => $data['legal_address'] ?? null,
            'pickup_address' => $data['pickup_address'],
            'working_hours' => $data['working_hours'] ?? null,
        ];

        if (empty($profile->inn) && !empty($data['inn'])) {
            $updateData['inn'] = $data['inn'];
        }

        $profile->update($updateData);

        if ($changeResult['submitted']) {
            $parts = [];
            if ($changeResult['name'] ?? false) {
                $parts[] = 'название';
            }
            if ($changeResult['description'] ?? false) {
                $parts[] = 'описание';
            }

            return back()->with([
                'success' => 'Изменения (' . implode(' и ', $parts) . ') отправлены на модерацию. До одобрения на сайте отображаются прежние данные.',
                'tab' => 'shop',
            ]);
        }

        return back()->with(['success' => 'Данные магазина сохранены.', 'tab' => 'shop']);
    }

    public function updateAccount(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[\p{L}\s\-\']+$/u'],
            'last_name' => ['required', 'string', 'max:50', 'regex:/^[\p{L}\s\-\']+$/u'],
            'email' => 'required|email|max:100|unique:users,email,' . $user->id,
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
        ], [
            'name.required' => 'Необходимо указать имя.',
            'name.string' => 'Имя должно быть текстом.',
            'name.max' => 'Имя не должно превышать 50 символов.',
            'name.regex' => 'Имя может содержать только буквы, пробелы и дефис.',
            'last_name.required' => 'Необходимо указать фамилию.',
            'last_name.string' => 'Фамилия должна быть текстом.',
            'last_name.max' => 'Фамилия не должна превышать 50 символов.',
            'last_name.regex' => 'Фамилия может содержать только буквы, пробелы и дефис.',
            'email.required' => 'Необходимо указать email.',
            'email.email' => 'Введите корректный email адрес.',
            'email.max' => 'Email не должен превышать 100 символов.',
            'email.unique' => 'Этот email уже занят другим пользователем.',
            'avatar.image' => 'Аватар должен быть изображением.',
            'avatar.mimes' => 'Допустимые форматы: JPEG, JPG, PNG, WEBP.',
            'avatar.max' => 'Размер аватара не должен превышать 10 МБ.',
        ]);

        $updateData = [
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
        ];

        if ($request->hasFile('avatar')) {
            $request->validate([
                'avatar' => 'image|mimes:jpg,jpeg,png,gif|max:2048',
            ], [
                'avatar.image' => 'Аватар должен быть изображением.',
                'avatar.mimes' => 'Допустимые форматы: JPG, JPEG, PNG, GIF.',
                'avatar.max' => 'Размер аватара не должен превышать 2 МБ.',
            ]);

            $file = $request->file('avatar');

            // Папка пользователя
            $userDir = public_path("img/avatars/{$user->id}");

            if (!is_dir($userDir)) {
                mkdir($userDir, 0755, true);
            }

            // Удаляем старый аватар
            if ($user->avatar) {
                $oldPath = public_path(ltrim($user->avatar, '/'));
                if (file_exists($oldPath) && is_file($oldPath)) {
                    unlink($oldPath);

                    // Удаляем папку если она пустая
                    $oldDir = dirname($oldPath);
                    if (is_dir($oldDir) && count(scandir($oldDir)) === 2) {
                        rmdir($oldDir);
                    }
                }
            }

            // Генерируем безопасное имя файла
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . $user->id . '.' . $extension;

            // Перемещаем файл
            $file->move($userDir, $filename);

            // Сохраняем путь (рекомендую без начального слэша)
            $updateData['avatar'] = "/img/avatars/{$user->id}/{$filename}";
        }

        $user->update($updateData);

        return back()->with(['success' => 'Данные профиля сохранены.', 'tab' => 'account']);
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Пароли не совпадают.',
            'password.min' => 'Пароль должен содержать не менее 8 символов.',
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
                'to' => $isWeekend ? '' : '18:00',
            ];
        }
        return $result;
    }
}

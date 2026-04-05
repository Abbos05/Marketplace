<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\UserSession;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Nft;

class AdminController extends Controller
{
    public function index(Request $request)
    {

        $query = User::query();
        $search = request('search');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $query->orderBy('role', 'desc') // 1. Сначала по роли

            ->orderByRaw('(
                SELECT COUNT(*)
                FROM nfts
                WHERE nfts.user_id = users.id
                AND nfts.status = "moderation"
            ) DESC')
            ->orderBy('is_blocked', 'asc');
        $usersData = $query->get()->map(function ($user) {
            $userNfts = Nft::where('user_id', $user->id)->get();
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'is_blocked' => $user->is_blocked,
                'numbertel' => $user->numbertel ?? 'Нет номера',
                'avatar' => $user->avatar,
                'nft' => $userNfts, // добавляем NFT как массив
            ];
        });

        // Получаем активные сессии с данными пользователей
        $sessions = DB::table('sessions')
            ->join('users', 'sessions.user_id', '=', 'users.id')
            ->select(
                'sessions.id as session_id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.name as user_name',
                'users.email as user_email'
            )
            ->whereNotNull('sessions.user_id')
            ->orderBy('sessions.last_activity', 'desc')
            ->get()
            ->map(function ($session) {
                // Преобразуем last_activity (timestamp) в Carbon
                $session->last_activity = Carbon::createFromTimestamp($session->last_activity);
                return $session;
            });
        return Inertia::render('Admin/Index', [
            'users' => $usersData,
            'nft' => $usersData,
            'sessions' => $sessions,
            'currentSessionId' => $request->session()->getId(),
            'search' => $search,
        ]);
    }

    public function showUser(User $user)
    {
        $myNfts = Nft::where('user_id', $user->id)
            ->with(['category', 'user'])
            ->get();
        return Inertia::render('Admin/Show', [
            'nfts' => $myNfts,
            'user' => $user,
        ]);
        return inertia('Admin/Show', compact('user', 'nfts'));
    }

    public function nftbuy(Request $request)
    {
        $nftId = $request->nft['id'];
        $nft = Nft::where('id', $nftId)->first();
        $nft->update([
            'status' => 'relevant',
        ]);
        return redirect()->back();
    }
    public function nftstop(Request $request)
    {
        $nftId = $request->nft['id'];
        $nft = Nft::where('id', $nftId)->first();
        $nft->update([
            'status' => 'rejection',
        ]);
        return redirect()->back();
    }
    public function nftsold(Request $request)
    {
        $nftId = $request->nft['id'];
        $nft = Nft::where('id', $nftId)->first();
        $nft->update([
            'status' => 'sold',
        ]);
        return redirect()->back();
    }

    public function destroy(Request $request, string $sessionId)
    {
        if ($sessionId === $request->session()->getId()) {
            return back()->with('error', 'Нельзя завершить свою собственную сессию.');
        }
        DB::table('sessions')->where('id', $sessionId)->delete();

        return back()->with('success', 'Сессия завершена');
    }
}

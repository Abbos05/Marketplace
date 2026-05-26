<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoginHistoryRecorder
{
    public function record(Request $request, User $user, string $method = 'password'): void
    {
        $sessionId = $request->session()->getId();

        if ($sessionId) {
            DB::table('sessions')
                ->where('id', $sessionId)
                ->update([
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'last_activity' => time(),
                ]);
        }

        DB::table('account_login_events')->insert([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'login_method' => $method,
            'created_at' => now(),
        ]);
    }
}

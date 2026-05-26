<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionHeartbeatController
{
    public function __invoke(Request $request)
    {
        $sessionId = $request->session()->getId();
        if ($sessionId) {
            DB::table('sessions')
                ->where('id', $sessionId)
                ->update(['last_activity' => time()]);
        }

        return response()->noContent();
    }
}

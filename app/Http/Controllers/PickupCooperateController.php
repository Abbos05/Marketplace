<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class PickupCooperateController extends Controller
{
    public function show(): RedirectResponse
    {
        return redirect()->route('pickup.partner');
    }
}

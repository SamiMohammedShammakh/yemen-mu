<?php

namespace App\Http\Controllers;

use App\Models\Alert;

class AlertController extends Controller
{
    public function index()
    {
        $alerts = Alert::where('user_id', auth()->id())->latest()->paginate(40);

        return view('alerts.index', compact('alerts'));
    }

    public function open(Alert $alert)
    {
        abort_unless($alert->user_id === auth()->id(), 403);
        $alert->update(['read_at' => $alert->read_at ?? now()]);

        return redirect($alert->url ?: route('alerts.index'));
    }

    public function readAll()
    {
        Alert::where('user_id', auth()->id())->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }
}

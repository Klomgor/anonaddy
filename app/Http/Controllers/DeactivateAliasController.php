<?php

namespace App\Http\Controllers;

use App\Models\Alias;
use Illuminate\Support\Facades\Log;

class DeactivateAliasController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('signed');
        $this->middleware('throttle:6,1');
    }

    public function deactivate($id)
    {
        $alias = user()->aliases()->findOrFail($id);

        $alias->deactivate();

        Log::info('Email banner link deactivated alias: '.$alias->email.' ID: '.$id);

        return redirect()->route('aliases.index')
            ->with(['flash' => 'Alias '.$alias->email.' deactivated successfully!']);
    }

    public function deactivatePost($id)
    {
        $alias = Alias::findOrFail($id);

        $alias->deactivate();

        Log::info('One-Click Unsubscribe deactivated alias: '.$alias->email.' ID: '.$id);

        return response('');
    }
}

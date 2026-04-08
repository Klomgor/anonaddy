<?php

namespace App\Http\Controllers;

use App\Models\Alias;
use Illuminate\Support\Facades\Log;

class DeleteAliasController extends Controller
{
    public function __construct()
    {
        $this->middleware('signed');
        $this->middleware('throttle:6,1');
    }

    public function deletePost($id)
    {
        $alias = Alias::findOrFail($id);

        $alias->delete();

        Log::info('One-Click Unsubscribe deleted alias: '.$alias->email.' ID: '.$id);

        return response('');
    }
}

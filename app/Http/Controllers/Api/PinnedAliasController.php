<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AliasResource;
use Illuminate\Http\Request;

class PinnedAliasController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['id' => 'required|string']);

        $alias = user()->aliases()->withTrashed()->findOrFail($request->id);

        $alias->update(['pinned' => true]);

        return new AliasResource($alias->load('recipients'));
    }

    public function destroy($id)
    {
        $alias = user()->aliases()->withTrashed()->findOrFail($id);

        $alias->update(['pinned' => false]);

        return response('', 204);
    }
}

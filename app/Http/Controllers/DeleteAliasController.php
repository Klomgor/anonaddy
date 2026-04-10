<?php

namespace App\Http\Controllers;

use App\Models\Alias;
use App\Notifications\AliasDeletedByUnsubscribeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DeleteAliasController extends Controller
{
    public function __construct()
    {
        $this->middleware('signed');
        $this->middleware('throttle:6,1');
    }

    public function deletePost(Request $request, $id)
    {
        $alias = Alias::findOrFail($id);

        $wasNotDeleted = is_null($alias->deleted_at);

        $alias->delete();

        Log::info('One-Click Unsubscribe deleted alias: '.$alias->email.' ID: '.$id);

        if ($wasNotDeleted) {
            $cacheKey = "unsubscribe-delete-notify:{$alias->id}";

            if (! Cache::has($cacheKey)) {
                Cache::put($cacheKey, true, now()->addHour());

                $user = $alias->user;
                $user->notify(
                    (new AliasDeletedByUnsubscribeNotification(
                        $alias->email,
                        $request->ip(),
                        $request->userAgent(),
                        now()->format('F j, g:i A (T)'),
                    ))
                );
            }
        }

        return response('');
    }
}

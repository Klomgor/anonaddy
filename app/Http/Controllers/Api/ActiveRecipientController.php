<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipientResource;
use Illuminate\Http\Request;

class ActiveRecipientController extends Controller
{
    public function store(Request $request)
    {
        $request->validate(['id' => 'required|string']);

        $recipient = user()->recipients()->findOrFail($request->id);

        $recipient->activate();

        return new RecipientResource($recipient->loadCount('aliases'));
    }

    public function destroy($id)
    {
        if ($id === user()->default_recipient_id) {
            return response('You cannot deactivate your default recipient', 403);
        }

        $recipient = user()->recipients()->findOrFail($id);

        $recipient->deactivate();

        return response('', 204);
    }
}

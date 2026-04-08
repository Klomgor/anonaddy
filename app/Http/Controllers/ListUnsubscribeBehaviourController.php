<?php

namespace App\Http\Controllers;

use App\Enums\ListUnsubscribeBehaviour;
use App\Http\Requests\UpdateListUnsubscribeBehaviourRequest;

class ListUnsubscribeBehaviourController extends Controller
{
    public function update(UpdateListUnsubscribeBehaviourRequest $request)
    {
        user()->update([
            'list_unsubscribe_behaviour' => ListUnsubscribeBehaviour::from($request->list_unsubscribe_behaviour),
        ]);

        return back()->with(['flash' => 'List-Unsubscribe behaviour updated successfully']);
    }
}

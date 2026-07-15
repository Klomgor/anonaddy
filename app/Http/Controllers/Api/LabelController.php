<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexLabelRequest;
use App\Http\Requests\StoreLabelRequest;
use App\Http\Requests\UpdateLabelRequest;
use App\Http\Resources\LabelResource;

class LabelController extends Controller
{
    public function index(IndexLabelRequest $request)
    {
        $labels = user()->labels()
            ->when($request->input('filter.search'), function ($query, $searchTerm) {
                $query->where('name', 'like', '%'.strtolower($searchTerm).'%');
            })
            ->orderBy('name')
            ->withCount('aliases');

        return LabelResource::collection($labels->get());
    }

    public function show($id)
    {
        $label = user()->labels()->withCount('aliases')->findOrFail($id);

        return new LabelResource($label);
    }

    public function store(StoreLabelRequest $request)
    {
        if (user()->hasReachedLabelLimit()) {
            return response('You\'ve reached your label limit', 403);
        }

        $label = user()->labels()->create([
            'name' => $request->name,
            'colour' => $request->colour,
        ]);

        return (new LabelResource($label))->response()->setStatusCode(201);
    }

    public function update(UpdateLabelRequest $request, $id)
    {
        $label = user()->labels()->findOrFail($id);

        $label->update($request->validated());

        return new LabelResource($label->refresh()->loadCount('aliases'));
    }

    public function destroy($id)
    {
        $label = user()->labels()->findOrFail($id);

        $label->aliases()->detach();
        $label->delete();

        return response('', 204);
    }
}

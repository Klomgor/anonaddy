<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexFailedDeliveryRequest;
use App\Http\Resources\FailedDeliveryResource;

class FailedDeliveryController extends Controller
{
    public function index(IndexFailedDeliveryRequest $request)
    {
        $failedDeliveries = user()
            ->failedDeliveries()
            ->with(['recipient:id,email', 'alias:id,email'])
            ->when($request->input('filter.email_type'), function ($query, $value) {
                if ($value === 'inbound') {
                    return $query->where('email_type', 'IR');
                }

                if ($value === 'outbound') {
                    return $query->where('email_type', '!=', 'IR');
                }
            })
            ->latest()
            ->jsonPaginate();

        return FailedDeliveryResource::collection($failedDeliveries);
    }

    public function show($id)
    {
        $failedDelivery = user()->failedDeliveries()->findOrFail($id);

        return new FailedDeliveryResource($failedDelivery->load(['recipient:id,email', 'alias:id,email']));
    }

    public function destroy($id)
    {
        $failedDelivery = user()->failedDeliveries()->findOrFail($id);

        $failedDelivery->delete();

        return response('', 204);
    }
}

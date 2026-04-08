<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyBlocklistBulkRequest;
use App\Http\Requests\StoreBlockedSenderRequest;
use App\Http\Requests\StoreBlocklistBulkRequest;
use App\Http\Resources\BlocklistResource;
use App\Models\BlockedSender;
use Illuminate\Http\Request;

class BlocklistController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100|min:1',
        ]);

        $query = $request->user()
            ->blockedSenders()
            ->select(['id', 'user_id', 'type', 'value', 'blocked', 'last_blocked', 'updated_at', 'created_at'])
            ->latest();

        if (isset($validated['search'])) {
            $searchTerm = strtolower($validated['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(value) LIKE ?', ['%'.$searchTerm.'%'])
                    ->orWhereRaw('LOWER(type) LIKE ?', ['%'.$searchTerm.'%']);
            });
        }

        return BlocklistResource::collection($query->get());
    }

    public function store(StoreBlockedSenderRequest $request)
    {
        $blockedSender = $request->user()->blockedSenders()->create($request->validated());

        return new BlocklistResource($blockedSender->refresh());
    }

    public function storeBulk(StoreBlocklistBulkRequest $request)
    {
        $type = $request->input('type');
        $values = array_values(array_unique($request->input('values')));

        $existing = $request->user()
            ->blockedSenders()
            ->where('type', $type)
            ->whereIn('value', $values)
            ->pluck('value')
            ->all();

        $toCreate = array_values(array_diff($values, $existing));

        $rows = array_map(fn (string $value) => [
            'user_id' => $request->user()->id,
            'type' => $type,
            'value' => $value,
        ], $toCreate);

        $createdModels = $request->user()->blockedSenders()->createMany($rows);
        // Refresh attributes to get the latest data
        $createdModels = BlockedSender::whereIn('id', $createdModels->pluck('id'))->get();

        $count = count($createdModels);
        $skipped = count($values) - count($toCreate);

        $data = BlocklistResource::collection($createdModels)->resolve();

        return response()->json([
            'data' => $data,
            'message' => $count === 0
                ? ($skipped > 0 ? 'All entries were already on your blocklist.' : 'No entries added.')
                : ($count === 1
                    ? '1 entry added to blocklist.'
                    : "{$count} entries added to blocklist.").($skipped > 0 ? " {$skipped} already on blocklist." : ''),
            'skipped' => $skipped,
        ], 201);
    }

    public function destroy(Request $request, string $id)
    {
        $entry = $request->user()->blockedSenders()->findOrFail($id);

        $entry->delete();

        return response('', 204);
    }

    public function destroyBulk(DestroyBlocklistBulkRequest $request)
    {
        $ids = $request->user()
            ->blockedSenders()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return response()->json(['message' => 'No blocklist entries found'], 404);
        }

        $request->user()->blockedSenders()->whereIn('id', $ids)->delete();

        $count = $ids->count();

        return response()->json([
            'message' => $count === 1
                ? '1 entry removed from blocklist'
                : "{$count} entries removed from blocklist",
            'ids' => $ids,
        ], 200);
    }
}

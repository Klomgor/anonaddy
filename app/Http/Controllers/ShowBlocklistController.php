<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShowBlocklistController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100|min:1',
        ]);

        $query = user()
            ->blockedSenders()
            ->select(['id', 'user_id', 'type', 'value', 'blocked', 'last_blocked', 'created_at'])
            ->latest();

        if (isset($validated['search'])) {
            $searchTerm = strtolower($validated['search']);
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(value) LIKE ?', ['%'.$searchTerm.'%'])
                    ->orWhereRaw('LOWER(type) LIKE ?', ['%'.$searchTerm.'%']);
            });
        }

        $blockedSenders = $query->get();

        return Inertia::render('Blocklist/Index', [
            'initialRows' => $blockedSenders,
            'search' => $validated['search'] ?? null,
        ]);
    }
}

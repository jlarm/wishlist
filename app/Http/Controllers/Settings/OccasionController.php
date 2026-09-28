<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Occasion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OccasionController extends Controller
{
    /**
     * Show the user's occasions.
     */
    public function edit(Request $request): Response
    {
        $today = now();

        return Inertia::render('settings/Occasions', [
            'occasions' => $request->user()
                ->occasions()
                ->orderBy('date')
                ->get()
                ->map(fn (Occasion $occasion): array => [
                    'id' => $occasion->id,
                    'name' => $occasion->name,
                    'date' => $occasion->date->toDateString(),
                    'recurs_annually' => $occasion->recurs_annually,
                    'next_date' => $occasion->nextOccurrence($today)?->toDateString(),
                ]),
        ]);
    }

    /**
     * Add an occasion.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date'],
            'recurs_annually' => ['required', 'boolean'],
        ]);

        if ($request->user()->occasions()->count() >= 20) {
            throw ValidationException::withMessages(['name' => __('You can have up to 20 occasions.')]);
        }

        $request->user()->occasions()->create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Occasion added.')]);

        return to_route('occasions.edit');
    }

    /**
     * Remove an occasion.
     */
    public function destroy(Request $request, Occasion $occasion): RedirectResponse
    {
        abort_unless($occasion->user_id === $request->user()->id, 403);

        $occasion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Occasion removed.')]);

        return to_route('occasions.edit');
    }
}

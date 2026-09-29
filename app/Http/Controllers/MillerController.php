<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Miller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MillerController extends Controller
{
    /**
     * List every miller profile for the combobox suggestion dropdown.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            Miller::orderBy('name')->get([
                'id',
                'name',
                'category',
                'capacity_12h_bags',
            ]),
        );
    }

    /**
     * Miller management page (RMEC / Administrator settings).
     */
    public function manage(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');

        $query = Miller::withCount('millings')->orderBy('name');

        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        if (array_key_exists($category, Miller::CATEGORY_LABELS)) {
            $query->where('category', $category);
        } else {
            $category = '';
        }

        return view('settings.millers', [
            'millers' => $query->get(),
            'q' => $search,
            'category' => $category,
        ]);
    }

    /**
     * Show the edit form for a miller profile.
     */
    public function edit(Miller $miller): View
    {
        return view('settings.millers-edit', ['miller' => $miller]);
    }

    /**
     * Save a miller profile from the combobox "Add" popup or the settings page.
     *
     * An already-known name (case-insensitive on the database collation)
     * returns the existing profile untouched, so the dropdown can never
     * create near-duplicate millers.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'category' => ['required', 'string', 'in:nfa_owned,private'],
            'capacity_12h_bags' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $existing = Miller::whereRaw('LOWER(name) = ?', [
            mb_strtolower($validated['name']),
        ])->first();

        if ($existing) {
            if ($request->expectsJson()) {
                return response()->json($existing, 200);
            }

            return redirect()
                ->route('settings.millers')
                ->with('status', "A miller named \"{$existing->name}\" already exists.");
        }

        $miller = Miller::create([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'capacity_12h_bags' => $validated['capacity_12h_bags'],
        ]);

        AuditLog::record('MILLER_CREATED', $miller, [
            'name' => $miller->name,
            'category' => $miller->category,
            'capacity_12h_bags' => $miller->capacity_12h_bags,
        ], 'miller', "Miller profile '{$miller->name}' created");

        if ($request->expectsJson()) {
            return response()->json($miller, 201);
        }

        return redirect()
            ->route('settings.millers')
            ->with('status', "Miller profile '{$miller->name}' added.");
    }

    /**
     * Update a miller profile from the settings page.
     */
    public function update(Request $request, Miller $miller): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'category' => ['required', 'string', 'in:nfa_owned,private'],
            'capacity_12h_bags' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        $duplicate = Miller::whereRaw('LOWER(name) = ?', [
            mb_strtolower($validated['name']),
        ])->first();

        if ($duplicate && $duplicate->isNot($miller)) {
            return back()->withErrors([
                'name' => 'A miller with this name already exists.',
            ]);
        }

        $miller->update($validated);

        AuditLog::record('MILLER_UPDATED', $miller, [
            'name' => $miller->name,
            'category' => $miller->category,
            'capacity_12h_bags' => $miller->capacity_12h_bags,
        ], 'miller', "Miller profile '{$miller->name}' updated");

        return redirect()
            ->route('settings.millers')
            ->with('status', "Miller profile '{$miller->name}' updated.");
    }

    /**
     * Delete a miller profile from the settings page.
     *
     * Historic record names are plain strings and stay untouched; milling
     * assignments are unlinked first so no dangling profile id remains on
     * databases that do not enforce the foreign key.
     */
    public function destroy(Miller $miller): RedirectResponse
    {
        $millerName = $miller->name;

        AuditLog::record('MILLER_DELETED', $miller, [
            'name' => $miller->name,
            'category' => $miller->category,
            'capacity_12h_bags' => $miller->capacity_12h_bags,
        ], 'miller', "Miller profile '{$millerName}' deleted");

        $miller->millings()->update(['miller_id' => null]);
        $miller->delete();

        return redirect()
            ->route('settings.millers')
            ->with('status', "Miller profile '{$millerName}' deleted.");
    }
}

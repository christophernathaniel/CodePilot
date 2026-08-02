<?php

namespace App\Http\Controllers;

use App\Http\Requests\VariablePresets\StoreVariablePresetRequest;
use App\Http\Requests\VariablePresets\UpdateVariablePresetRequest;
use App\Models\Snippet;
use App\Models\VariablePreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class VariablePresetController extends Controller
{
    public function store(StoreVariablePresetRequest $request, Snippet $snippet): RedirectResponse
    {
        $snippet->variablePresets()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preset created.')]);

        return back();
    }

    public function update(
        UpdateVariablePresetRequest $request,
        Snippet $snippet,
        VariablePreset $variablePreset,
    ): RedirectResponse {
        $variablePreset->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preset updated.')]);

        return back();
    }

    public function destroy(Snippet $snippet, VariablePreset $variablePreset): RedirectResponse
    {
        Gate::authorize('delete', $variablePreset);

        $variablePreset->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Preset deleted.')]);

        return back();
    }
}

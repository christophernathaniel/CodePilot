<?php

namespace App\Http\Controllers;

use App\Http\Requests\Frameworks\StoreFrameworkRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class FrameworkController extends Controller
{
    public function store(StoreFrameworkRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->frameworks()->create([
            ...$request->validated(),
            'color' => '#64748b',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Framework created.')]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pins\TogglePinRequest;
use App\Models\Pin;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class PinController extends Controller
{
    public function __invoke(TogglePinRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $attributes = $request->safe()->only(['pinnable_type', 'pinnable_key']);

        if ($request->boolean('pinned')) {
            $user->pins()->firstOrCreate($attributes);
        } else {
            Pin::query()
                ->where('user_id', $user->id)
                ->where($attributes)
                ->delete();
        }

        return back();
    }
}

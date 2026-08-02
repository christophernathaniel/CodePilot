<?php

namespace App\Http\Controllers;

use App\Actions\Snippets\BuildWorkspace;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __invoke(Request $request, BuildWorkspace $buildWorkspace): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('snippets/workspace', $buildWorkspace->handle($user));
    }
}

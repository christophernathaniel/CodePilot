<?php

use App\Http\Controllers\FolderController;
use App\Http\Controllers\MoveFolderController;
use App\Http\Controllers\MoveSnippetController;
use App\Http\Controllers\PinController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SnippetController;
use App\Http\Controllers\SnippetFavouriteController;
use App\Http\Controllers\SnippetUsageController;
use App\Http\Controllers\SnippetVariationController;
use App\Http\Controllers\VariablePresetController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', WorkspaceController::class)->name('dashboard');

    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::post('projects/{project}/folders', [FolderController::class, 'store'])
        ->name('projects.folders.store');
    Route::patch('projects/{project}/folders/{folder}', [FolderController::class, 'update'])
        ->scopeBindings()
        ->name('projects.folders.update');
    Route::delete('projects/{project}/folders/{folder}', [FolderController::class, 'destroy'])
        ->scopeBindings()
        ->name('projects.folders.destroy');
    Route::patch('folders/{folder}/move', MoveFolderController::class)->name('folders.move');

    Route::post('snippets', [SnippetController::class, 'store'])->name('snippets.store');
    Route::post('projects/{project}/snippets', [SnippetController::class, 'storeInProject'])
        ->name('projects.snippets.store');
    Route::patch('snippets/{snippet}', [SnippetController::class, 'update'])->name('snippets.update');
    Route::patch('snippets/{snippet}/favourite', SnippetFavouriteController::class)
        ->name('snippets.favourite.update');
    Route::patch('snippets/{snippet}/move', MoveSnippetController::class)->name('snippets.move');
    Route::post('snippets/{snippet}/copies', SnippetUsageController::class)->name('snippets.copies.store');
    Route::delete('snippets/{snippet}', [SnippetController::class, 'destroy'])->name('snippets.destroy');

    Route::put('pins', PinController::class)->name('pins.update');

    Route::post('snippets/{snippet}/presets', [VariablePresetController::class, 'store'])
        ->name('snippets.presets.store');
    Route::patch('snippets/{snippet}/presets/{variablePreset}', [VariablePresetController::class, 'update'])
        ->scopeBindings()
        ->name('snippets.presets.update');
    Route::delete('snippets/{snippet}/presets/{variablePreset}', [VariablePresetController::class, 'destroy'])
        ->scopeBindings()
        ->name('snippets.presets.destroy');

    Route::post('snippets/{snippet}/variations', [SnippetVariationController::class, 'store'])
        ->name('snippets.variations.store');
    Route::patch('snippets/{snippet}/variations/{snippetVariation}', [SnippetVariationController::class, 'update'])
        ->scopeBindings()
        ->name('snippets.variations.update');
    Route::delete('snippets/{snippet}/variations/{snippetVariation}', [SnippetVariationController::class, 'destroy'])
        ->scopeBindings()
        ->name('snippets.variations.destroy');
    Route::patch('snippets/{snippet}/variations/{snippetVariation}/default', [SnippetVariationController::class, 'makeDefault'])
        ->scopeBindings()
        ->name('snippets.variations.default');
});

require __DIR__.'/settings.php';

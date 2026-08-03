<?php

use App\Http\Controllers\ClipboardActivationController;
use App\Http\Controllers\ClipboardClearController;
use App\Http\Controllers\ClipboardClipController;
use App\Http\Controllers\ClipboardFileController;
use App\Http\Controllers\ClipboardSessionController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\FrameworkController;
use App\Http\Controllers\LibraryCategoryController;
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

    Route::post('clipboards', [ClipboardSessionController::class, 'store'])->name('clipboards.store');
    Route::patch('clipboards/{clipboardSession}', [ClipboardSessionController::class, 'update'])
        ->name('clipboards.update');
    Route::delete('clipboards/{clipboardSession}', [ClipboardSessionController::class, 'destroy'])
        ->name('clipboards.destroy');
    Route::patch('clipboards/{clipboardSession}/activate', ClipboardActivationController::class)
        ->name('clipboards.activate');
    Route::delete('clipboards/{clipboardSession}/clips', ClipboardClearController::class)
        ->name('clipboards.clips.clear');
    Route::post('clipboards/{clipboardSession}/files', ClipboardFileController::class)
        ->name('clipboards.files.store');
    Route::post('clipboard-clips', [ClipboardClipController::class, 'store'])->name('clipboard-clips.store');
    Route::delete('clipboard-clips/{clipboardClip}', [ClipboardClipController::class, 'destroy'])
        ->name('clipboard-clips.destroy');

    Route::post('library-categories', [LibraryCategoryController::class, 'store'])
        ->name('library-categories.store');
    Route::patch('library-categories/{libraryCategory}', [LibraryCategoryController::class, 'update'])
        ->name('library-categories.update');
    Route::delete('library-categories/{libraryCategory}', [LibraryCategoryController::class, 'destroy'])
        ->name('library-categories.destroy');

    Route::post('frameworks', [FrameworkController::class, 'store'])->name('frameworks.store');

    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('projects/reorder', [ProjectController::class, 'reorder'])->name('projects.reorder');
    Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
    Route::patch('trash/projects/{project}/restore', [ProjectController::class, 'restore'])
        ->withTrashed()
        ->name('projects.restore');
    Route::delete('trash/projects/{project}', [ProjectController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('projects.force-destroy');

    Route::post('projects/{project}/folders', [FolderController::class, 'store'])
        ->name('projects.folders.store');
    Route::patch('projects/{project}/folders/{folder}', [FolderController::class, 'update'])
        ->scopeBindings()
        ->name('projects.folders.update');
    Route::delete('projects/{project}/folders/{folder}', [FolderController::class, 'destroy'])
        ->scopeBindings()
        ->name('projects.folders.destroy');
    Route::patch('trash/folders/{folder}/restore', [FolderController::class, 'restore'])
        ->withTrashed()
        ->name('folders.restore');
    Route::delete('trash/folders/{folder}', [FolderController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('folders.force-destroy');
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
    Route::patch('trash/snippets/{snippet}/restore', [SnippetController::class, 'restore'])
        ->withTrashed()
        ->name('snippets.restore');
    Route::delete('trash/snippets/{snippet}', [SnippetController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('snippets.force-destroy');

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

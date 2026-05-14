<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Divisions\StoreDivisionRequest;
use App\Http\Requests\Divisions\UpdateDivisionRequest;
use App\Http\Resources\DivisionResource;
use App\Models\Division;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DivisionsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Division::class);

        $divisions = Division::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('divisions/Index', [
            'divisions' => DivisionResource::collection($divisions)->toArray($request),
        ]);
    }

    public function store(StoreDivisionRequest $request): RedirectResponse
    {
        Division::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Division created.')]);

        return to_route('divisions.index');
    }

    public function update(UpdateDivisionRequest $request, Division $division): RedirectResponse
    {
        $division->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Division updated.')]);

        return to_route('divisions.index');
    }

    public function destroy(Division $division): RedirectResponse
    {
        $this->authorize('delete', $division);

        $division->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Division archived.')]);

        return to_route('divisions.index');
    }
}

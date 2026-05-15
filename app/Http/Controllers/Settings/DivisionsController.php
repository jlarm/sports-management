<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Divisions\ReorderDivisionsRequest;
use App\Http\Requests\Divisions\StoreDivisionRequest;
use App\Http\Requests\Divisions\UpdateDivisionRequest;
use App\Http\Resources\DivisionResource;
use App\Models\Division;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class DivisionsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Division::class);

        $showArchived = $request->boolean('archived');

        $divisions = Division::query()
            ->when($showArchived, fn (Builder $query): Builder => $query->onlyTrashed())
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('divisions/Index', [
            'divisions' => DivisionResource::collection($divisions)->toArray($request),
            'archived' => $showArchived,
            'archived_count' => Division::query()->onlyTrashed()->count(),
        ]);
    }

    public function store(StoreDivisionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (! array_key_exists('display_order', $data)) {
            $max = Division::query()->max('display_order');
            $data['display_order'] = is_numeric($max) ? ((int) $max) + 1 : 0;
        }

        Division::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Division created.')]);

        return to_route('divisions.index');
    }

    public function update(UpdateDivisionRequest $request, Division $division): RedirectResponse
    {
        $division->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Division updated.')]);

        return to_route('divisions.index');
    }

    public function reorder(ReorderDivisionsRequest $request): RedirectResponse
    {
        /** @var array<int, int> $ids */
        $ids = $request->validated('ids');

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $position => $id) {
                Division::query()->whereKey($id)->update(['display_order' => $position]);
            }
        });

        return to_route('divisions.index');
    }

    public function destroy(Division $division): RedirectResponse
    {
        $this->authorize('delete', $division);

        $division->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Division archived.')]);

        return to_route('divisions.index');
    }

    public function restore(Division $division): RedirectResponse
    {
        $this->authorize('restore', $division);

        $division->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Division restored.')]);

        return to_route('divisions.index');
    }
}

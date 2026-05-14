<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seasons\StoreSeasonRequest;
use App\Http\Requests\Seasons\UpdateSeasonRequest;
use App\Http\Resources\SeasonResource;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class SeasonsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Season::class);

        $seasons = Season::query()
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get();

        return Inertia::render('settings/seasons/Index', [
            'seasons' => SeasonResource::collection($seasons)->toArray($request),
        ]);
    }

    public function store(StoreSeasonRequest $request): RedirectResponse
    {
        Season::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Season created.')]);

        return to_route('seasons.index');
    }

    public function update(UpdateSeasonRequest $request, Season $season): RedirectResponse
    {
        $season->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Season updated.')]);

        return to_route('seasons.index');
    }

    public function destroy(Season $season): RedirectResponse
    {
        $this->authorize('delete', $season);

        $season->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Season archived.')]);

        return to_route('seasons.index');
    }

    public function activate(Season $season): RedirectResponse
    {
        $this->authorize('activate', $season);

        DB::transaction(function () use ($season): void {
            Season::query()->where('id', '!=', $season->id)->update(['is_active' => false]);
            $season->forceFill(['is_active' => true])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Season activated.')]);

        return to_route('seasons.index');
    }
}

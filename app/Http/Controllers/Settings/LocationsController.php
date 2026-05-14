<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Locations\StoreLocationRequest;
use App\Http\Requests\Locations\UpdateLocationRequest;
use App\Http\Resources\LocationResource;
use App\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class LocationsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Location::class);

        $locations = Location::query()
            ->orderBy('name')
            ->get();

        return Inertia::render('locations/Index', [
            'locations' => LocationResource::collection($locations)->toArray($request),
        ]);
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        Location::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location created.')]);

        return to_route('locations.index');
    }

    public function update(UpdateLocationRequest $request, Location $location): RedirectResponse
    {
        $location->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location updated.')]);

        return to_route('locations.index');
    }

    public function destroy(Location $location): RedirectResponse
    {
        $this->authorize('delete', $location);

        $location->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Location archived.')]);

        return to_route('locations.index');
    }
}

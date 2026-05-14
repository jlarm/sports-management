<?php

declare(strict_types=1);

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Http\Requests\Players\StorePlayerRequest;
use App\Http\Requests\Players\UpdatePlayerRequest;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PlayersController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Player::class);

        $players = Player::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return Inertia::render('players/Index', [
            'players' => PlayerResource::collection($players)->toArray($request),
        ]);
    }

    public function store(StorePlayerRequest $request): RedirectResponse
    {
        Player::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player created.')]);

        return to_route('players.index');
    }

    public function update(UpdatePlayerRequest $request, Player $player): RedirectResponse
    {
        $player->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player updated.')]);

        return to_route('players.index');
    }

    public function destroy(Player $player): RedirectResponse
    {
        $this->authorize('delete', $player);

        $player->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player archived.')]);

        return to_route('players.index');
    }
}

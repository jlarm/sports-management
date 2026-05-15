<?php

declare(strict_types=1);

namespace App\Http\Controllers\Players;

use App\Http\Controllers\Controller;
use App\Http\Requests\Players\StorePlayerRequest;
use App\Http\Requests\Players\UpdatePlayerRequest;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use App\Services\Audit\AuditLogger;
use App\Tenancy\CurrentTenant;
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

    public function store(StorePlayerRequest $request, AuditLogger $audit, CurrentTenant $tenant): RedirectResponse
    {
        $player = Player::create($request->validated());

        $audit->log(
            organizationId: $tenant->id(),
            action: 'player.created',
            subject: $player,
            payload: ['last_name' => $player->last_name, 'dob' => $player->dob->toDateString()],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player created.')]);

        return to_route('players.index');
    }

    public function update(UpdatePlayerRequest $request, Player $player, AuditLogger $audit): RedirectResponse
    {
        $player->update($request->validated());

        $audit->log(
            organizationId: $player->organization_id,
            action: 'player.updated',
            subject: $player,
            payload: ['changed' => array_keys($request->validated())],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player updated.')]);

        return to_route('players.index');
    }

    public function destroy(Player $player, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $player);

        $player->delete();

        $audit->log(
            organizationId: $player->organization_id,
            action: 'player.archived',
            subject: $player,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player archived.')]);

        return to_route('players.index');
    }
}

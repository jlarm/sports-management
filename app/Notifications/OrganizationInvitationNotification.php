<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Invitation $invitation,
        private readonly string $rawToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->invitation->loadMissing('organization', 'invitedBy');

        $organization = $this->invitation->organization;
        assert($organization instanceof Organization);

        $organizationName = $organization->name;
        $roleLabel = $this->invitation->role->value;
        $invitedBy = $this->invitation->invitedBy?->name;
        $acceptUrl = route('invitations.show', ['token' => $this->rawToken]);

        return (new MailMessage)
            ->subject("You've been invited to join {$organizationName}")
            ->greeting('Hi there,')
            ->line(
                $invitedBy
                    ? "{$invitedBy} has invited you to join {$organizationName} as {$roleLabel}."
                    : "You've been invited to join {$organizationName} as {$roleLabel}."
            )
            ->action('View invitation', $acceptUrl)
            ->line('This invitation expires on '.$this->invitation->expires_at->toDayDateTimeString().'.')
            ->line('If you weren\'t expecting this, you can ignore this email.');
    }
}

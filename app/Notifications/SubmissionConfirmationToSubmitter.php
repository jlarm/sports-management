<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Form;
use App\Models\Organization;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubmissionConfirmationToSubmitter extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Submission $submission,
        private readonly Form $form,
        private readonly Organization $organization,
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
        return (new MailMessage)
            ->subject("Thanks for registering with {$this->organization->name}")
            ->greeting('Thanks!')
            ->line("We've received your submission for {$this->form->title}.")
            ->line('Reference #'.$this->submission->id.' — we will reach out if we need anything else.')
            ->line("If you didn't submit this form, please reply to this email so we can sort it out.");
    }
}

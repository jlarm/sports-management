<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Form;
use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubmissionReceivedAdminAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Submission $submission,
        private readonly Form $form,
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
        $reviewUrl = route('forms.submissions.show', [$this->form->id, $this->submission->id]);

        return (new MailMessage)
            ->subject("New submission for {$this->form->title}")
            ->greeting('Heads up,')
            ->line("A new submission was received for {$this->form->title}.")
            ->line('Submission #'.$this->submission->id.' is awaiting review.')
            ->action('Review submission', $reviewUrl)
            ->line('You can match the entry to an existing player or create a new one from the review screen.');
    }
}

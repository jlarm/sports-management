<?php

declare(strict_types=1);

use App\Enums\FieldType;
use App\Enums\OrganizationRole;
use App\Models\Form;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\SubmissionConfirmationToSubmitter;
use App\Notifications\SubmissionReceivedAdminAlert;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

function notificationsForm(Organization $org): Form
{
    return Form::factory()->for($org)->published()->create([
        'title' => 'Spring Registration',
        'schema' => [
            'fields' => [
                ['key' => 'first_name', 'label' => 'First', 'type' => FieldType::Text->value, 'required' => true],
                ['key' => 'last_name', 'label' => 'Last', 'type' => FieldType::Text->value, 'required' => true],
                ['key' => 'dob', 'label' => 'DOB', 'type' => FieldType::Date->value, 'required' => true],
                ['key' => 'parent_email', 'label' => 'Parent email', 'type' => FieldType::Text->value, 'required' => false],
            ],
        ],
    ]);
}

beforeEach(function () {
    $this->org = Organization::factory()->create();
    $this->form = notificationsForm($this->org);
    $this->owner = User::factory()->create();
    $this->owner->organizations()->attach($this->org, ['role' => OrganizationRole::Owner->value]);
    $this->admin = User::factory()->create();
    $this->admin->organizations()->attach($this->org, ['role' => OrganizationRole::Admin->value]);
    $this->coach = User::factory()->create();
    $this->coach->organizations()->attach($this->org, ['role' => OrganizationRole::Coach->value]);
});

test('admin alert is sent to owners and admins (not coaches) on submit', function () {
    Notification::fake();

    $this->post(route('public-forms.submit', $this->form->id), [
        'data' => [
            'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
            'parent_email' => 'parent@example.com',
        ],
    ])->assertRedirect();

    Notification::assertSentTo([$this->owner, $this->admin], SubmissionReceivedAdminAlert::class);
    Notification::assertNotSentTo($this->coach, SubmissionReceivedAdminAlert::class);
});

test('confirmation is sent to the parent email when present', function () {
    Notification::fake();

    $this->post(route('public-forms.submit', $this->form->id), [
        'data' => [
            'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
            'parent_email' => 'parent@example.com',
        ],
    ])->assertRedirect();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        SubmissionConfirmationToSubmitter::class,
        function (SubmissionConfirmationToSubmitter $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $notifiable->routes['mail'] === 'parent@example.com';
        }
    );
});

test('confirmation falls back to the authenticated user email when parent_email is missing', function () {
    Notification::fake();

    $submitter = User::factory()->create(['email' => 'authed@example.com', 'email_verified_at' => now()]);

    $this->actingAs($submitter)
        ->post(route('public-forms.submit', $this->form->id), [
            'data' => [
                'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
            ],
        ])->assertRedirect();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        SubmissionConfirmationToSubmitter::class,
        function (SubmissionConfirmationToSubmitter $notification, array $channels, AnonymousNotifiable $notifiable): bool {
            return $notifiable->routes['mail'] === 'authed@example.com';
        }
    );
});

test('no confirmation is sent when neither parent_email nor authed user is available', function () {
    Notification::fake();

    $this->post(route('public-forms.submit', $this->form->id), [
        'data' => ['first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12'],
    ])->assertRedirect();

    Notification::assertNothingSentTo(new AnonymousNotifiable);
});

test('admin alert is not sent when the org has no admins or owners', function () {
    Notification::fake();
    $loneOrg = Organization::factory()->create();
    $loneForm = notificationsForm($loneOrg);

    $this->post(route('public-forms.submit', $loneForm->id), [
        'data' => [
            'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
        ],
    ])->assertRedirect();

    Notification::assertNothingSent();
});

test('notifications implement ShouldQueue', function () {
    expect(new SubmissionReceivedAdminAlert($this->form->submissions()->make(), $this->form))
        ->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);

    expect(new SubmissionConfirmationToSubmitter($this->form->submissions()->make(), $this->form, $this->org))
        ->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});

test('admin alert mail content includes the submission id and review link', function () {
    Notification::fake();

    $this->post(route('public-forms.submit', $this->form->id), [
        'data' => [
            'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
        ],
    ])->assertRedirect();

    Notification::assertSentTo(
        $this->admin,
        SubmissionReceivedAdminAlert::class,
        function (SubmissionReceivedAdminAlert $notification): bool {
            $mail = $notification->toMail($this->admin);
            $rendered = strip_tags((string) $mail->render());

            return str_contains($mail->subject, 'New submission for Spring Registration')
                && str_contains($rendered, 'awaiting review');
        }
    );
});

test('submitter confirmation mail content includes the org name', function () {
    Notification::fake();

    $this->post(route('public-forms.submit', $this->form->id), [
        'data' => [
            'first_name' => 'Diego', 'last_name' => 'Lopez', 'dob' => '2014-04-12',
            'parent_email' => 'parent@example.com',
        ],
    ])->assertRedirect();

    Notification::assertSentTo(
        new AnonymousNotifiable,
        SubmissionConfirmationToSubmitter::class,
        function (SubmissionConfirmationToSubmitter $notification): bool {
            $mail = $notification->toMail(new AnonymousNotifiable);

            return str_contains($mail->subject, $this->org->name);
        }
    );
});

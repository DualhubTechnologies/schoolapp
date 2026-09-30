<?php

namespace App\Filament\Pages;

use App\Jobs\SendMessageBatch;
use App\Models\MessageBatch;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\Messaging\BulkMessages;
use App\Services\Messaging\MessageRecipient;
use App\Support\Modules;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Bulk SMS from the school: to every family, one class or stream, the
 * families owing fees, or the staff. Shows how many will receive it and
 * how many SMS each takes before anything is sent; sending happens in the
 * background and the history below shows how it went.
 *
 * @property-read array{recipients: Collection<int, MessageRecipient>, no_phone: int} $audienceSummary
 */
class SendMessages extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|\UnitEnum|null $navigationGroup = 'Students';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'Messages';

    protected static ?string $navigationLabel = 'Messages (SMS)';

    protected string $view = 'filament.pages.send-messages';

    public string $audience = 'families';

    public ?int $classId = null;

    public ?int $sectionId = null;

    public string $body = '';

    public const MAX_LENGTH = 459;

    public static function canAccess(): bool
    {
        return Modules::allows('messages');
    }

    public function school(): ?School
    {
        return auth()->user()?->school;
    }

    public function updatedAudience(): void
    {
        $this->classId = $this->sectionId = null;
        unset($this->audienceSummary);
    }

    public function updatedClassId(): void
    {
        $this->sectionId = null;
        unset($this->audienceSummary);
    }

    public function updatedSectionId(): void
    {
        unset($this->audienceSummary);
    }

    public function updatedBody(): void
    {
        unset($this->audienceSummary);
    }

    /** @return Collection<int, string> */
    public function classOptions(): Collection
    {
        return SchoolClass::where('school_id', auth()->user()?->school_id)->orderBy('level')->orderBy('name')->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function sectionOptions(): Collection
    {
        return $this->classId && $this->classOptions()->has($this->classId)
            ? Section::where('school_class_id', $this->classId)->orderBy('name')->pluck('name', 'id')
            : collect();
    }

    /**
     * @return array{class_id: int|null, section_id: int|null}
     */
    public function filters(): array
    {
        $classId = $this->classId && $this->classOptions()->has($this->classId) ? $this->classId : null;

        return [
            'class_id' => $classId,
            'section_id' => $classId && $this->sectionId && $this->sectionOptions()->has($this->sectionId) ? $this->sectionId : null,
        ];
    }

    /**
     * @return array{recipients: Collection<int, MessageRecipient>, no_phone: int}
     */
    #[Computed]
    public function audienceSummary(): array
    {
        $school = $this->school();

        return $school && isset(MessageBatch::AUDIENCES[$this->audience])
            ? app(BulkMessages::class)->recipients($school, $this->audience, $this->filters(), $this->body)
            : ['recipients' => collect(), 'no_phone' => 0];
    }

    /**
     * The message as the first recipient will read it.
     */
    public function sample(): ?string
    {
        $school = $this->school();
        $first = $this->audienceSummary['recipients']->first();

        return $school && $first && trim($this->body) !== '' ? app(BulkMessages::class)->render($this->body, $school, $first) : null;
    }

    public function parts(): int
    {
        return BulkMessages::parts($this->sample() ?? $this->body);
    }

    /** @return Collection<int, MessageBatch> */
    public function history(): Collection
    {
        return MessageBatch::where('school_id', auth()->user()?->school_id)
            ->with('sender')
            ->latest()
            ->limit(20)
            ->get()
            ->toBase();
    }

    public function sendAction(): Action
    {
        return Action::make('send')
            ->label('Send')
            ->icon('heroicon-o-paper-airplane')
            ->requiresConfirmation()
            ->modalHeading('Send this message?')
            ->modalDescription(function (): string {
                $count = $this->audienceSummary['recipients']->count();

                return "{$count} ".str('SMS')->plural($count).' to '.app(BulkMessages::class)->label($this->audience, $this->filters())
                    .($this->parts() > 1 ? ', each '.$this->parts().' SMS long' : '').'. This cannot be undone.';
            })
            ->modalSubmitActionLabel('Send now')
            ->action(function (): void {
                $body = trim($this->body);
                $school = $this->school();

                if ($body === '' || mb_strlen($body) > self::MAX_LENGTH || ! $school) {
                    Notification::make()->title('Write the message first')->body('Up to '.self::MAX_LENGTH.' characters (three SMS).')->danger()->send();

                    return;
                }

                if ($this->audienceSummary['recipients']->isEmpty()) {
                    Notification::make()->title('Nobody to send to')->body('No one in this group has a phone number on record.')->warning()->send();

                    return;
                }

                $batch = MessageBatch::create([
                    'school_id' => $school->id,
                    'audience' => $this->audience,
                    'filters' => $this->filters(),
                    'audience_label' => app(BulkMessages::class)->label($this->audience, $this->filters()),
                    'body' => $body,
                    'status' => 'queued',
                    'recipients' => $this->audienceSummary['recipients']->count(),
                    'sent_by' => auth()->id(),
                ]);

                SendMessageBatch::dispatch($batch);

                $this->body = '';
                unset($this->audienceSummary);

                Notification::make()->title('Message on its way')->body('It is being sent in the background. The history below shows how it went.')->success()->send();
            });
    }
}

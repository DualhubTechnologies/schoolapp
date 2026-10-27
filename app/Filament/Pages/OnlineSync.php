<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PlacedInMenu;
use App\Services\Sync\SyncClient;
use App\Services\Sync\SyncReviews;
use App\Support\Edition;
use App\Support\Modules;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/**
 * The Windows app's link with the school's online account: link it with a
 * code made online, sync now (it also syncs on its own every few minutes
 * while online, sync:run), and decide on possible duplicates, similar
 * payments and deleted finance records (SyncReviews).
 */
class OnlineSync extends Page
{
    use PlacedInMenu;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPathRoundedSquare;

    protected static ?string $title = 'Sync with online';

    protected static ?string $slug = 'sync';

    protected string $view = 'filament.pages.online-sync';

    public string $code = '';

    public static function canAccess(): bool
    {
        return Edition::isDesktop() && auth()->user()?->school_id !== null
            && (Modules::hasFullAccess(auth()->user()) || Modules::allows('fees'));
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $open = DB::table('sync_reviews')->where('status', 'open')->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function client(): SyncClient
    {
        return app(SyncClient::class);
    }

    public function canManage(): bool
    {
        return Modules::hasFullAccess(auth()->user());
    }

    /** @return array{linked: bool, school: string|null, last: CarbonImmutable|null, result: array<string, int>} */
    public function status(): array
    {
        $client = $this->client();
        $last = $client->state(SyncClient::LAST);

        return [
            'linked' => $client->isLinked(),
            'school' => $client->linkedSchool(),
            'last' => $last ? CarbonImmutable::parse($last) : null,
            'result' => (array) json_decode((string) $client->state(SyncClient::RESULT, '{}'), true),
        ];
    }

    /** @return list<array{id: int, title: string, here: array<string, string>|null, online: array<string, string>|null, options: array<string, string>, kind: string}> */
    public function reviews(): array
    {
        $reviews = app(SyncReviews::class);

        return array_values(DB::table('sync_reviews')->where('status', 'open')->orderBy('id')->get()
            ->map(fn (stdClass $review): array => [
                'id' => (int) $review->id,
                'kind' => (string) $review->kind,
                'title' => $reviews->title($review),
                ...$reviews->compare($review),
                'options' => $reviews->options($review),
            ])
            ->all());
    }

    public function link(): void
    {
        abort_unless($this->canManage(), 403);

        if (strlen((string) preg_replace('/[^A-Za-z0-9]/', '', $this->code)) !== 8) {
            $this->addError('code', 'Type the 8-letter link code shown online, like ABCD-EF23.');

            return;
        }

        try {
            $school = $this->client()->link($this->code);
        } catch (ConnectionException) {
            $this->addError('code', 'No internet connection. Connect this computer to the internet (phone data is enough) and try again.');

            return;
        } catch (RuntimeException $e) {
            $this->addError('code', $e->getMessage());

            return;
        }

        $this->code = '';
        $this->resetErrorBag();
        Notification::make()->title("Linked to {$school} online")->body('Now syncing for the first time…')->success()->send();

        $this->syncNow();
    }

    public function syncNow(): void
    {
        try {
            $result = $this->client()->run();
        } catch (ConnectionException) {
            Notification::make()->title('No internet connection')->body('SchoolHub syncs on its own as soon as this computer is online.')->warning()->send();

            return;
        } catch (RuntimeException $e) {
            Notification::make()->title('Could not sync')->body($e->getMessage())->danger()->send();

            return;
        }

        $body = "{$result['received']} received from online, {$result['sent']} sent online.";

        if ($result['review'] > 0) {
            $body .= " {$result['review']} need your decision below.";
        }

        Notification::make()->title('Synced')->body($body)->success()->send();
    }

    public function decide(int $review, string $decision): void
    {
        try {
            app(SyncReviews::class)->decide($review, $decision, auth()->user()?->name);
        } catch (RuntimeException $e) {
            Notification::make()->title('Not done')->body($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('Done')->body('It goes online at the next sync.')->success()->send();
    }

    public function unlink(): void
    {
        abort_unless($this->canManage(), 403);

        $this->client()->unlink();
        Notification::make()->title('Unlinked')->body('This computer no longer syncs. Everything on it stays.')->success()->send();
    }
}

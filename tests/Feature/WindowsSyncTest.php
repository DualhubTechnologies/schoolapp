<?php

use App\Filament\Pages\OnlineSync;
use App\Filament\Pages\WindowsApp;
use App\Models\School;
use App\Models\SyncDevice;
use App\Models\User;
use App\Services\Sync\SyncClient;
use App\Services\Sync\SyncReviews;
use App\Services\Sync\SyncServer;
use App\Services\Sync\SyncTransport;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
 * The Windows app (this test's database) syncing with the online server
 * (a second database, "server"), through SyncServer directly.
 */

beforeEach(function () {
    $this->travelTo('2026-10-27 09:00:00');

    $template = storage_path('framework/testing/sync-server-template.sqlite');
    $file = storage_path('framework/testing/sync-server.sqlite');
    File::ensureDirectoryExists(dirname($file));

    config(['database.connections.server' => ['driver' => 'sqlite', 'database' => $template, 'prefix' => '', 'foreign_key_constraints' => true]]);

    if (! isset($GLOBALS['syncTemplateReady'])) {
        File::put($template, '');
        DB::purge('server');
        Artisan::call('migrate', ['--database' => 'server', '--force' => true]);
        DB::purge('server');
        $GLOBALS['syncTemplateReady'] = true;
    }

    File::copy($template, $file);
    config(['database.connections.server.database' => $file]);
    DB::purge('server');

    $this->online = DB::connection('server');
    $this->onlineSchool = $this->online->table('schools')->insertGetId(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'created_at' => now(), 'updated_at' => now()]);
    $this->here = DB::connection();
    $this->hereSchool = $this->here->table('schools')->insertGetId(['name' => 'Hope Secondary', 'slug' => 'hope', 'email' => 'hope@example.com', 'created_at' => now(), 'updated_at' => now()]);

    $server = new SyncServer('server');
    $this->server = $server;
    $this->client = new SyncClient(new class($server) implements SyncTransport
    {
        public function __construct(protected SyncServer $server) {}

        public function post(string $action, array $payload, ?string $token = null): array
        {
            $payload = json_decode((string) json_encode($payload), true);
            $device = fn () => $this->server->device($token) ?? throw new RuntimeException('not linked');

            $result = match ($action) {
                'link' => $this->server->link($payload['code'], $payload['name'] ?? null),
                'pull' => $this->server->pull($device(), (int) $payload['cursor'], (bool) $payload['scan'], $payload['fetch'] ?? []),
                'push' => ['results' => $this->server->push($device(), (int) $payload['cursor'], $payload['changes'])],
            };

            return json_decode((string) json_encode($result), true);
        }
    });

    $code = $server->makeLinkCode(School::on('server')->findOrFail($this->onlineSchool), 'Head teacher');
    expect($this->client->link(strtolower(str_replace('-', '', $code))))->toBe('Hope Secondary');
});

afterEach(function () {
    DB::purge('server');
    File::delete(storage_path('framework/testing/sync-server.sqlite'));
});

/** Adds a record on one side ($side: the online or this computer's connection). */
function add(Connection $side, string $table, array $values): int
{
    $school = $side->getName() === 'server' ? test()->onlineSchool : test()->hereSchool;
    $values = ['created_at' => now(), 'updated_at' => now(), ...$values];

    if (! in_array($table, ['marks', 'class_subject', 'student_subject', 'term_reports'], true)) {
        $values['school_id'] ??= $school;
    }

    return (int) $side->table($table)->insertGetId($values);
}

/** A class, a learner and a term on the online side. */
function onlineBasics(): array
{
    $online = test()->online;
    $class = add($online, 'school_classes', ['name' => 'S.1']);
    $year = add($online, 'academic_years', ['name' => '2026']);
    $term = add($online, 'terms', ['academic_year_id' => $year, 'name' => 'Term 3', 'sequence' => 3]);
    $learner = add($online, 'students', ['name' => 'Nakato Sarah', 'admission_no' => 'HS/2026/0001', 'school_class_id' => $class]);

    return compact('class', 'year', 'term', 'learner');
}

it('brings the online school onto a new computer, joining what both already have', function () {
    $online = onlineBasics();
    add($this->online, 'student_payments', ['student_id' => $online['learner'], 'term_id' => $online['term'], 'amount' => 350000, 'paid_on' => '2026-10-20', 'receipt_seq' => 1, 'receipt_no' => 'RCT-000001', 'method' => 'cash']);
    add($this->here, 'school_classes', ['name' => 'S.1', 'updated_at' => now()->subDay()]);

    $result = $this->client->run();

    expect($this->here->table('school_classes')->count())->toBe(1)
        ->and($this->here->table('students')->value('name'))->toBe('Nakato Sarah')
        ->and($this->here->table('students')->value('school_class_id'))->toBe($this->here->table('school_classes')->value('id'))
        ->and($this->here->table('student_payments')->value('receipt_no'))->toBe('RCT-000001')
        ->and($result['review'])->toBe(0)
        ->and($result['waiting'])->toBe(0);

    // Nothing changed since: nothing to send or fetch again.
    expect($this->client->run())->toMatchArray(['received' => 0, 'sent' => 0]);
});

it('sends what was done offline: new learners, payments and changes', function () {
    $online = onlineBasics();
    $this->client->run();

    $class = $this->here->table('school_classes')->value('id');
    $term = $this->here->table('terms')->value('id');
    $learner = add($this->here, 'students', ['name' => 'Okello Brian', 'admission_no' => 'HS/2026/0002', 'school_class_id' => $class]);
    add($this->here, 'student_payments', ['student_id' => $learner, 'term_id' => $term, 'amount' => 200000, 'paid_on' => '2026-10-26', 'receipt_seq' => 1, 'receipt_no' => 'RCT-W00001', 'method' => 'cash']);
    $this->here->table('students')->where('name', 'Nakato Sarah')->update(['phone' => '0772000111', 'updated_at' => now()]);

    expect($this->client->run()['sent'])->toBe(3);

    $brian = $this->online->table('students')->where('name', 'Okello Brian')->first();
    $payment = $this->online->table('student_payments')->where('student_id', $brian->id)->first();

    expect($brian->school_class_id)->toBe($online['class'])
        ->and($brian->school_id)->toBe($this->onlineSchool)
        ->and($payment->receipt_no)->toBe('RCT-W00001')
        ->and((float) $payment->amount)->toBe(200000.0)
        ->and($this->online->table('students')->where('id', $online['learner'])->value('phone'))->toBe('0772000111');

    // Receipt numbers that clash take the next free running number, keeping the printed one.
    add($this->online, 'student_payments', ['student_id' => $online['learner'], 'amount' => 5000, 'paid_on' => '2026-10-27', 'receipt_seq' => 2, 'receipt_no' => 'RCT-000002']);
    $this->client->run();
    expect($this->here->table('student_payments')->where('receipt_no', 'RCT-000002')->value('receipt_seq'))->toBe(2);
});

it('keeps the latest edit when a record was changed on both sides', function () {
    $online = onlineBasics();
    $this->client->run();

    $this->travel(1)->hours();
    $this->here->table('students')->update(['phone' => '0700000001', 'updated_at' => now()]);
    $this->travel(1)->hours();
    $this->online->table('students')->where('id', $online['learner'])->update(['phone' => '0700000002', 'updated_at' => now()]);

    $this->client->run();

    expect($this->here->table('students')->value('phone'))->toBe('0700000002')
        ->and($this->online->table('students')->where('id', $online['learner'])->value('phone'))->toBe('0700000002');

    // And the other way round.
    $this->travel(1)->hours();
    $this->online->table('students')->where('id', $online['learner'])->update(['phone' => '0700000003', 'updated_at' => now()]);
    $this->travel(1)->hours();
    $this->here->table('students')->update(['phone' => '0700000004', 'updated_at' => now()]);

    $this->client->run();

    expect($this->here->table('students')->value('phone'))->toBe('0700000004')
        ->and($this->online->table('students')->where('id', $online['learner'])->value('phone'))->toBe('0700000004');
});

it('joins marks entered on both sides for the same exam, keeping the latest', function () {
    $online = onlineBasics();
    $subject = add($this->online, 'subjects', ['name' => 'Mathematics', 'curriculum' => 'new']);
    $exam = add($this->online, 'assessments', ['term_id' => $online['term'], 'name' => 'Mid term', 'type' => 'exam']);
    $this->client->run();

    $markHere = ['assessment_id' => $this->here->table('assessments')->value('id'), 'student_id' => $this->here->table('students')->value('id'), 'subject_id' => $this->here->table('subjects')->value('id')];
    add($this->here, 'marks', [...$markHere, 'score' => 61]);
    $this->travel(5)->minutes();
    add($this->online, 'marks', ['assessment_id' => $exam, 'student_id' => $online['learner'], 'subject_id' => $subject, 'score' => 64]);

    expect($this->client->run()['review'])->toBe(0)
        ->and($this->here->table('marks')->count())->toBe(1)
        ->and((float) $this->here->table('marks')->value('score'))->toBe(64.0)
        ->and($this->online->table('marks')->count())->toBe(1);
});

it('lists a learner added on both sides for a decision, and joins them', function () {
    $online = onlineBasics();
    $this->client->run();

    $class = $this->here->table('school_classes')->value('id');
    $term = $this->here->table('terms')->value('id');
    $here = add($this->here, 'students', ['name' => 'Mugisha  Allan', 'admission_no' => 'HS/2026/0010', 'school_class_id' => $class, 'phone' => '0701111111']);
    add($this->here, 'student_payments', ['student_id' => $here, 'term_id' => $term, 'amount' => 150000, 'paid_on' => '2026-10-26', 'receipt_seq' => 5, 'receipt_no' => 'RCT-W00005']);
    add($this->online, 'students', ['name' => 'mugisha allan', 'admission_no' => 'HS/2026/0011', 'school_class_id' => $online['class'], 'phone' => '0702222222']);

    $result = $this->client->run();
    $review = $this->here->table('sync_reviews')->sole();

    expect($result['review'])->toBe(1)
        ->and($review->kind)->toBe('duplicate')
        ->and($review->local_id)->toBe($here)
        // Neither copy nor the payment for it goes anywhere until someone decides.
        ->and($this->online->table('students')->count())->toBe(2)
        ->and($this->online->table('student_payments')->count())->toBe(0);

    $reviews = new SyncReviews($this->client);
    $compare = $reviews->compare($review);
    expect($reviews->title($review))->toBe('Is this the same learner?')
        ->and($compare['here']['Admission no.'])->toBe('HS/2026/0010')
        ->and($compare['online']['Admission no.'])->toBe('HS/2026/0011')
        ->and($compare['online']['Class'])->toBe('S.1');

    $reviews->decide($review->id, 'online', 'Bursar');
    $this->client->run();

    $allan = $this->online->table('students')->where('admission_no', 'HS/2026/0011')->sole();

    expect($this->here->table('students')->count())->toBe(2)
        ->and($this->here->table('students')->where('id', $here)->value('phone'))->toBe('0702222222')
        ->and($this->online->table('students')->count())->toBe(2)
        ->and($this->online->table('student_payments')->sole()->student_id)->toBe($allan->id);
});

it('lists a payment of the same amount on the same date, even with other receipt numbers', function () {
    $online = onlineBasics();
    $this->client->run();

    $learner = $this->here->table('students')->value('id');
    add($this->here, 'student_payments', ['student_id' => $learner, 'amount' => 300000, 'paid_on' => '2026-10-27', 'receipt_seq' => 7, 'receipt_no' => 'RCT-W00007']);
    add($this->online, 'student_payments', ['student_id' => $online['learner'], 'amount' => 300000, 'paid_on' => '2026-10-27', 'receipt_seq' => 3, 'receipt_no' => 'RCT-000003']);

    $this->client->run();
    $review = $this->here->table('sync_reviews')->sole();

    expect($review->kind)->toBe('payment')
        ->and((new SyncReviews($this->client))->compare($review)['online']['Receipt'])->toBe('RCT-000003');

    (new SyncReviews($this->client))->decide($review->id, 'online');
    $this->client->run();

    expect($this->here->table('student_payments')->count())->toBe(1)
        ->and($this->here->table('student_payments')->value('receipt_no'))->toBe('RCT-000003')
        ->and($this->online->table('student_payments')->count())->toBe(1);

    // Two real payments the same day: kept both.
    add($this->here, 'student_payments', ['student_id' => $learner, 'amount' => 50000, 'paid_on' => '2026-10-27', 'receipt_seq' => 8, 'receipt_no' => 'RCT-W00008']);
    add($this->online, 'student_payments', ['student_id' => $online['learner'], 'amount' => 50000, 'paid_on' => '2026-10-27', 'receipt_seq' => 4, 'receipt_no' => 'RCT-000004']);
    $this->client->run();
    (new SyncReviews($this->client))->decide($this->here->table('sync_reviews')->where('status', 'open')->value('id'), 'both');
    $this->client->run();

    expect($this->here->table('student_payments')->count())->toBe(3)
        ->and($this->online->table('student_payments')->count())->toBe(3);
});

it('checks a payment recorded offline against one already synced from online', function () {
    $online = onlineBasics();
    add($this->online, 'student_payments', ['student_id' => $online['learner'], 'amount' => 80000, 'paid_on' => '2026-10-25', 'receipt_seq' => 1, 'receipt_no' => 'RCT-000001']);
    $this->client->run();

    add($this->here, 'student_payments', ['student_id' => $this->here->table('students')->value('id'), 'amount' => 80000, 'paid_on' => '2026-10-25', 'receipt_seq' => 2, 'receipt_no' => 'RCT-W00002']);
    $this->client->run();

    $review = $this->here->table('sync_reviews')->sole();
    expect($review->kind)->toBe('payment')
        ->and($this->online->table('student_payments')->count())->toBe(1);

    (new SyncReviews($this->client))->decide($review->id, 'offline');
    $this->client->run();

    expect($this->here->table('student_payments')->count())->toBe(1)
        ->and($this->online->table('student_payments')->sole()->receipt_no)->toBe('RCT-W00002');
});

it('deletes on both sides, but asks before deleting a payment', function () {
    $online = onlineBasics();
    $payment = add($this->online, 'student_payments', ['student_id' => $online['learner'], 'amount' => 1000, 'paid_on' => '2026-10-20', 'receipt_seq' => 1, 'receipt_no' => 'RCT-000001']);
    $house = add($this->online, 'houses', ['name' => 'Rwenzori']);
    $this->client->run();

    $this->online->table('houses')->where('id', $house)->delete();
    $this->online->table('student_payments')->where('id', $payment)->delete();
    $this->client->run();

    $review = $this->here->table('sync_reviews')->sole();
    expect($this->here->table('houses')->count())->toBe(0)
        ->and($review->kind)->toBe('deleted_there')
        ->and($this->here->table('student_payments')->count())->toBe(1);

    (new SyncReviews($this->client))->decide($review->id, 'delete');
    expect($this->here->table('student_payments')->count())->toBe(0);

    // Deleted here: confirmed before it goes online.
    $other = add($this->online, 'student_payments', ['student_id' => $online['learner'], 'amount' => 2000, 'paid_on' => '2026-10-21', 'receipt_seq' => 2, 'receipt_no' => 'RCT-000002']);
    $this->client->run();
    $this->here->table('student_payments')->delete();
    $this->client->run();

    $review = $this->here->table('sync_reviews')->where('status', 'open')->sole();
    expect($review->kind)->toBe('deleted_here')
        ->and($this->online->table('student_payments')->where('id', $other)->exists())->toBeTrue();

    $this->client->run(); // fetches the online copy to show
    expect((new SyncReviews($this->client))->compare($this->here->table('sync_reviews')->find($review->id))['online']['Amount'])->toBe('UGX 2,000');

    (new SyncReviews($this->client))->decide($review->id, 'delete');
    $this->client->run();
    expect($this->online->table('student_payments')->where('id', $other)->exists())->toBeFalse();
});

it('gives a different learner with the same admission number a new number', function () {
    $online = onlineBasics();
    $this->client->run();

    $class = $this->here->table('school_classes')->value('id');
    $here = add($this->here, 'students', ['name' => 'Akello Grace', 'admission_no' => 'HS/2026/0002', 'school_class_id' => $class]);
    add($this->online, 'students', ['name' => 'Opio Mark', 'admission_no' => 'HS/2026/0002', 'school_class_id' => $online['class']]);

    $this->client->run();
    $review = $this->here->table('sync_reviews')->sole();
    expect($review->kind)->toBe('number');

    (new SyncReviews($this->client))->decide($review->id, 'renumber');
    $this->client->run();

    expect($this->here->table('students')->where('id', $here)->value('admission_no'))->not->toBe('HS/2026/0002')
        ->and($this->here->table('students')->count())->toBe(3)
        ->and($this->online->table('students')->count())->toBe(3);
});

it('only links with a valid code, and only a linked computer can sync', function () {
    expect(fn () => $this->server->link('AAAA-BBBB', 'PC'))->toThrow(RuntimeException::class, 'wrong or has expired');

    $code = $this->server->makeLinkCode(School::on('server')->findOrFail($this->onlineSchool));
    $this->travel(2)->hours();
    expect(fn () => $this->server->link($code, 'PC'))->toThrow(RuntimeException::class, 'wrong or has expired')
        ->and($this->server->device('not-a-token'))->toBeNull();
});

it('shows the review list in the Windows app and decides from it', function () {
    config(['app.edition' => 'desktop']);
    $this->seed(RoleSeeder::class);
    Filament\Facades\Filament::setCurrentPanel('app');
    $this->withoutVite();
    app()->instance(SyncClient::class, $this->client);
    $this->actingAs(User::factory()->create(['school_id' => $this->hereSchool])->assignRole('School Admin'));

    $online = onlineBasics();
    $this->client->run();
    add($this->here, 'student_payments', ['student_id' => $this->here->table('students')->value('id'), 'amount' => 70000, 'paid_on' => '2026-10-27', 'receipt_seq' => 9, 'receipt_no' => 'RCT-W00009']);
    add($this->online, 'student_payments', ['student_id' => $online['learner'], 'amount' => 70000, 'paid_on' => '2026-10-27', 'receipt_seq' => 9, 'receipt_no' => 'RCT-000009']);

    expect(OnlineSync::canAccess())->toBeTrue();

    $page = Livewire\Livewire::test(OnlineSync::class)
        ->assertSee('Linked to Hope Secondary online')
        ->call('syncNow')
        ->assertNotified('Synced')
        ->assertSee('A similar payment was recorded on both sides')
        ->assertSee('RCT-000009')
        ->assertSee('RCT-W00009')
        ->assertSee('Different: keep both');

    expect(OnlineSync::getNavigationBadge())->toBe('1');

    $page->call('decide', $this->here->table('sync_reviews')->value('id'), 'both')
        ->assertNotified('Done')
        ->assertSee('Nothing needs your decision.');

    // Receipts made on a linked computer carry a W.
    expect(SyncClient::numberMark())->toBe('W');

    $page->call('unlink');
    expect($this->client->isLinked())->toBeFalse()
        ->and($this->here->table('sync_rows')->count())->toBe(0);
});

it('makes a link code online, and links and syncs over the internet with it', function () {
    $this->seed(RoleSeeder::class);
    Filament\Facades\Filament::setCurrentPanel('app');
    $this->withoutVite();
    $admin = User::factory()->create(['school_id' => $this->hereSchool])->assignRole('School Admin');
    $this->actingAs($admin);

    $page = Livewire\Livewire::test(WindowsApp::class)->call('makeCode')->assertSee('within the next hour');
    $code = $page->get('linkCode');

    $this->postJson('/sync/link', ['code' => 'WRONG-CODE'])->assertStatus(422);
    $token = $this->postJson('/sync/link', ['code' => $code, 'name' => 'BURSAR-PC'])->assertOk()->json('token');

    $this->postJson('/sync/pull', ['cursor' => 0])->assertUnauthorized();
    $this->withToken($token)->postJson('/sync/pull', ['cursor' => 0])->assertOk()->assertJsonStructure(['cursor', 'more', 'changes']);
    $this->withToken($token)->postJson('/sync/push', ['cursor' => 0, 'changes' => []])->assertOk()->assertJson(['results' => []]);

    $device = SyncDevice::sole();
    expect($device->name)->toBe('BURSAR-PC')
        ->and($device->last_synced_at)->not->toBeNull();

    $page->call('$refresh')->assertSee('BURSAR-PC')->call('remove', $device->id);
    $this->withToken($token)->postJson('/sync/pull', ['cursor' => 0])->assertUnauthorized();
});

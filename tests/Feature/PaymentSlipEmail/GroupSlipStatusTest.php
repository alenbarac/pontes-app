<?php

use App\Models\InvoiceMailing;
use App\Models\Mailing;
use App\Models\MemberGroup;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('the group page lists recent mailings for that group only', function () {
    $group = MemberGroup::query()->create(['name' => 'Grupa 6']);
    $other = MemberGroup::query()->create(['name' => 'Grupa 1']);

    $older = Mailing::factory()->create([
        'source' => Mailing::SOURCE_GROUP,
        'member_group_id' => $group->id,
        'label' => 'Uplatnice 09/2026',
        'month' => '2026-09',
        'started_at' => '2026-09-03 08:00:00',
    ]);
    InvoiceMailing::factory()->count(2)->create([
        'mailing_id' => $older->id,
        'status' => InvoiceMailing::STATUS_SENT,
        'sent_at' => '2026-09-03 08:00:00',
    ]);

    $newer = Mailing::factory()->create([
        'source' => Mailing::SOURCE_GROUP,
        'member_group_id' => $group->id,
        'label' => 'Uplatnice 10/2026',
        'month' => '2026-10',
        'started_at' => '2026-10-02 09:00:00',
    ]);
    InvoiceMailing::factory()->queued()->create([
        'mailing_id' => $newer->id,
    ]);

    Mailing::factory()->create([
        'source' => Mailing::SOURCE_GROUP,
        'member_group_id' => $other->id,
        'label' => 'Tuđe slanje',
        'started_at' => '2026-10-03 09:00:00',
    ]);

    $this->get(route('member-groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('MemberGroups/Show')
            ->has('groupMailings', 2)
            ->where('groupMailings.0.id', $newer->id)
            ->where('groupMailings.0.label', 'Uplatnice 10/2026')
            ->where('groupMailings.0.total', 1)
            ->where('groupMailings.0.status_label', '0 / 1')
            ->where('groupMailings.0.url', route('mailings.show', $newer))
            ->where('groupMailings.1.id', $older->id)
            ->where('groupMailings.1.status_label', '2 poslano')
            ->where('groupMailings.1.started_at', '03.09.2026. 08:00')
            ->where('groupMailings.1.url', route('mailings.show', $older)));
});

test('a later document mailing shows in the same group log', function () {
    $group = MemberGroup::query()->create(['name' => 'Grupa 6']);

    Mailing::factory()->create([
        'type' => 'document',
        'source' => Mailing::SOURCE_GROUP,
        'member_group_id' => $group->id,
        'label' => 'Potvrde 09/2026',
        'month' => '2026-09',
        'started_at' => '2026-09-20 11:00:00',
    ]);

    $this->get(route('member-groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groupMailings', 1)
            ->where('groupMailings.0.label', 'Potvrde 09/2026')
            ->where('groupMailings.0.type', 'document'));
});

test('a group with no mailings has an empty activity log', function () {
    $group = MemberGroup::query()->create(['name' => 'Grupa 6']);

    $this->get(route('member-groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('groupMailings', []));
});

test('the group log keeps only the five newest batches', function () {
    $group = MemberGroup::query()->create(['name' => 'Grupa 6']);

    foreach (range(1, 6) as $day) {
        Mailing::factory()->create([
            'source' => Mailing::SOURCE_GROUP,
            'member_group_id' => $group->id,
            'label' => 'Uplatnice 0'.$day.'/2026',
            'started_at' => sprintf('2026-09-%02d 08:00:00', $day),
        ]);
    }

    $this->get(route('member-groups.show', $group))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('groupMailings', 5)
            ->where('groupMailings.0.label', 'Uplatnice 06/2026')
            ->where('groupMailings.4.label', 'Uplatnice 02/2026'));
});

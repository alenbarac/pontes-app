<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Test members and test groups stay in the app, but their money stays out of
 * dashboard totals and the reminder list. A test member who is also enrolled
 * through a real group still counts for that workshop.
 */
class TestRecords
{
    public static function except(EloquentBuilder $query): EloquentBuilder
    {
        $table = $query->getModel()->getTable();

        return $query->whereNot(function (EloquentBuilder $query) use ($table) {
            $query->whereExists(fn (QueryBuilder $sub) => self::groupMatch($sub, $table, true))
                ->orWhere(function (EloquentBuilder $sub) use ($table) {
                    $sub->whereExists(fn (QueryBuilder $member) => self::testMember($member, $table))
                        ->whereNotExists(fn (QueryBuilder $real) => self::groupMatch($real, $table, false));
                });
        });
    }

    private static function groupMatch(QueryBuilder $sub, string $table, bool $test): void
    {
        $sub->selectRaw('1')
            ->from('member_workshop_group as mwg')
            ->join('member_groups as mg', 'mg.id', '=', 'mwg.member_group_id')
            ->whereColumn('mwg.member_id', $table.'.member_id')
            ->whereColumn('mwg.workshop_id', $table.'.workshop_id')
            ->where('mg.is_test', $test);
    }

    private static function testMember(QueryBuilder $sub, string $table): void
    {
        $sub->selectRaw('1')
            ->from('members')
            ->whereColumn('members.id', $table.'.member_id')
            ->where('members.is_test', true);
    }
}

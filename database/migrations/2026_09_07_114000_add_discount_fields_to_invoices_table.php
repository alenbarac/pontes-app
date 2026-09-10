<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->nullable()->after('amount_due');
            $table->decimal('original_amount', 8, 2)->nullable()->after('discount_percent');
            $table->index('discount_percent');
        });

        $plans = DB::table('membership_plans')
            ->where(function ($q) {
                $q->where('plan', 'like', '%OFF%')
                    ->orWhere('discount_type', 'like', '%OFF%');
            })
            ->get();

        foreach ($plans as $plan) {
            $percent = $this->parseDiscountPercent($plan);
            if (! $percent || $percent >= 100) {
                continue;
            }

            $discounted = (float) ($plan->total_fee ?? $plan->fee);
            $original = round($discounted / (1 - $percent / 100), 2);

            DB::table('invoices')
                ->where('membership_plan_id', $plan->id)
                ->whereNull('discount_percent')
                ->update([
                    'discount_percent' => $percent,
                    'original_amount' => $original,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['discount_percent']);
            $table->dropColumn(['discount_percent', 'original_amount']);
        });
    }

    private function parseDiscountPercent(object $plan): ?float
    {
        foreach ([(string) $plan->discount_type, (string) $plan->plan] as $source) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*%\s*OFF/i', $source, $matches)) {
                return (float) $matches[1];
            }
        }

        return null;
    }
};

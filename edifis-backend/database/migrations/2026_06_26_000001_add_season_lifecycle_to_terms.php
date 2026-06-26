<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Academic season rotation: gives every term a lifecycle so the school can
 * "rotate" through Term 1 -> 2 -> 3 -> year-end, like a sports season.
 *
 * - status: upcoming (not started) | active (current, open for entry) | closed (finished)
 * - current_sequence: which of the term's two sequences is open (1 or 2)
 * - starts_on / ends_on: the calendar window (informational, drives expected term)
 * - closed_at: when the principal closed the term (reversible by admin)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->string('status', 16)->default('upcoming')->after('position');
            $table->unsignedSmallInteger('current_sequence')->default(1)->after('status');
            $table->date('starts_on')->nullable()->after('current_sequence');
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->timestamp('closed_at')->nullable()->after('ends_on');
        });

        // Initialise: in each current academic year, open Term 1 (position 1).
        $currentYearIds = DB::table('academic_years')->where('is_current', true)->pluck('id');

        foreach ($currentYearIds as $yearId) {
            DB::table('terms')
                ->where('academic_year_id', $yearId)
                ->where('position', 1)
                ->update(['status' => 'active', 'current_sequence' => 1]);
        }
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn(['status', 'current_sequence', 'starts_on', 'ends_on', 'closed_at']);
        });
    }
};

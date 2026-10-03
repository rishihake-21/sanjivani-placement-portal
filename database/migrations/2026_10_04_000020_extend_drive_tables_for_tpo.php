<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drive_eligibility', function (Blueprint $table) {
            // Lateral-entry students have no 12th marks: let the diploma percentage stand in for it.
            $table->boolean('diploma_counts_as_twelfth')->default(true)->after('max_total_backlogs');
        });

        Schema::table('placement_drives', function (Blueprint $table) {
            // Default policy: a student who is already PLACED cannot apply to further drives.
            $table->boolean('allow_placed_students')->default(false)->after('additional_requirements');
            $table->timestampTz('closed_at')->nullable()->after('published_at');
            $table->text('cancel_reason')->nullable()->after('closed_at');
        });

        DB::statement("ALTER TABLE placement_drives ADD CONSTRAINT drives_cancel_reason_check
            CHECK (status <> 'CANCELLED' OR cancel_reason IS NOT NULL)");

        // Faster candidate lookups for the eligibility engine and "my drives" screens.
        DB::statement("CREATE INDEX drives_open_idx ON placement_drives (graduation_year, application_deadline) WHERE status = 'PUBLISHED'");
        DB::statement("CREATE INDEX placements_placed_idx ON placements (student_id) WHERE status = 'PLACED'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS placements_placed_idx');
        DB::statement('DROP INDEX IF EXISTS drives_open_idx');
        DB::statement('ALTER TABLE placement_drives DROP CONSTRAINT IF EXISTS drives_cancel_reason_check');

        Schema::table('placement_drives', function (Blueprint $table) {
            $table->dropColumn(['allow_placed_students', 'closed_at', 'cancel_reason']);
        });
        Schema::table('drive_eligibility', function (Blueprint $table) {
            $table->dropColumn('diploma_counts_as_twelfth');
        });
    }
};

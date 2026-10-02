<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Append-only audit trail. A trigger rejects UPDATE and DELETE at the database level. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_role', 30)->nullable();
            $table->string('action', 60);
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('student_id')->nullable()->constrained('students')->restrictOnDelete();
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['student_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['actor_id', 'created_at']);
            $table->index('action');
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION audit_logs_block_mutation() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'audit_logs is append-only';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER audit_logs_no_update_delete
BEFORE UPDATE OR DELETE ON audit_logs
FOR EACH ROW EXECUTE FUNCTION audit_logs_block_mutation();
SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update_delete ON audit_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS audit_logs_block_mutation()');
        Schema::dropIfExists('audit_logs');
    }
};

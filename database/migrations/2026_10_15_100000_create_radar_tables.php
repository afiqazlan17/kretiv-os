<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Radar: BOD's board of things that need action (leads, renewals,
        // admin), replacing the short-lived Rizq notes. New tables with a type
        // and a due date; any Rizq notes are copied across, then dropped.
        Schema::create('radar_items', function (Blueprint $table) {
            $table->id();
            $table->text('body');
            $table->string('type')->nullable(); // lead, renewal, admin, other
            $table->date('due_date')->nullable();
            $table->string('department')->nullable();
            $table->string('image_path')->nullable();
            $table->string('status')->default('open'); // open, taken, done
            $table->foreignId('taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('taken_at')->nullable();
            $table->string('outcome')->nullable(); // job, dropped
            $table->foreignId('job_id')->nullable()->constrained('jobs')->nullOnDelete();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_date']);
        });

        Schema::create('radar_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('radar_item_id')->constrained('radar_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        if (Schema::hasTable('rizq_notes')) {
            $cols = ['id', 'body', 'department', 'image_path', 'status', 'taken_by', 'taken_at', 'outcome', 'job_id', 'done_by', 'done_at', 'created_by', 'created_at', 'updated_at'];
            foreach (DB::table('rizq_notes')->orderBy('id')->get() as $row) {
                DB::table('radar_items')->insert(array_intersect_key((array) $row, array_flip($cols)) + ['type' => 'lead']);
            }
            foreach (DB::table('rizq_replies')->orderBy('id')->get() as $row) {
                DB::table('radar_replies')->insert(['id' => $row->id, 'radar_item_id' => $row->rizq_note_id, 'user_id' => $row->user_id, 'body' => $row->body, 'created_at' => $row->created_at, 'updated_at' => $row->updated_at]);
            }
            Schema::dropIfExists('rizq_replies');
            Schema::dropIfExists('rizq_notes');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('radar_replies');
        Schema::dropIfExists('radar_items');
    }
};

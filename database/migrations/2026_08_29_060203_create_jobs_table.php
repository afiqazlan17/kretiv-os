<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique(); // KP-2026-001, business display code
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('department'); // print, work, tech, machine, event, wisb
            $table->string('job_type');
            $table->string('job_type_category')->default('client_project'); // client_project, product_sale
            $table->string('status')->default('potential'); // potential, in_progress, completed, cancelled
            $table->string('closed_from_status')->nullable();
            $table->decimal('estimation_value', 12, 2)->nullable();
            $table->decimal('final_value', 12, 2)->nullable();
            $table->string('pic')->nullable();
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
            $table->text('notes')->nullable();
            $table->string('drive_link')->nullable();
            $table->string('priority')->default('normal'); // normal, urgent, low
            $table->boolean('archived')->default(false);
            $table->string('cancel_reason')->nullable();
            $table->text('cancel_reason_text')->nullable();
            $table->string('source')->default('other');
            $table->boolean('special_arrangement')->default(false);
            $table->json('installments')->nullable();
            $table->json('cost_breakdown')->nullable();
            $table->decimal('baki_kretivco', 12, 2)->default(0);
            $table->json('line_items')->nullable();
            $table->json('attachments')->nullable(); // [{id, kind, line_item_id, path, name, uploaded_by, uploaded_at}]
            $table->string('bank')->nullable();
            $table->string('hold_status')->nullable();
            $table->text('hold_reason')->nullable();
            $table->string('project_id')->nullable()->index(); // groups sibling jobs created together across departments
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};

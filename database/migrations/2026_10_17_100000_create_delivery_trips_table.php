<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One Lalamove pickup from a supplier (e.g. SY) to Kretivco, possibly
        // carrying several jobs. Each job's delivery entry (in jobs.vendor_costs)
        // points here by trip_id and carries its share of the actual cost.
        Schema::create('delivery_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pickup_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->date('trip_date');
            $table->decimal('actual_cost', 10, 2);
            $table->string('status')->default('booked'); // booked, picked_up, arrived
            $table->string('receipt_path')->nullable();
            $table->string('paid_bank')->nullable();
            $table->date('paid_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_trips');
    }
};

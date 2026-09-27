<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Monthly bills (rent, subscriptions, utilities) set up once and recorded with one click each month. */
    public function up(): void
    {
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('department')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('bank');
            $table->unsignedTinyInteger('day_of_month')->default(1);
            $table->boolean('active')->default(true);
            $table->date('last_recorded_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};

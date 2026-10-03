<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_price_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150)->default('Sin Nombre');
            $table->enum('type', ['discount', 'surcharge']);
            $table->enum('calculation_type', ['fixed', 'percentage']);
            $table->decimal('value', 12, 2);
            $table->text('reason')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->json('days_of_week')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_price_adjustments');
    }
};

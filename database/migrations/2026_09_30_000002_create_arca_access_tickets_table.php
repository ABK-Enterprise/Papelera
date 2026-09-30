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
        Schema::create('arca_access_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('cuit', 20)->index();
            $table->string('unique_id', 50)->index();
            $table->string('token_preview', 16)->nullable();
            $table->string('service', 30)->default('wsfe');
            $table->string('environment', 20)->default('testing');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();

            $table->index(['cuit', 'service', 'environment']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arca_access_tickets');
    }
};

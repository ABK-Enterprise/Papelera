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
        Schema::create('provinces', function (Blueprint $table) {
            $table->string('id', 10)->primary();
            $table->string('name', 100);
            $table->string('iso_code', 10)->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->string('id', 15)->primary();
            $table->string('province_id', 10);
            $table->string('name', 150);
            $table->timestamps();

            $table->foreign('province_id')->references('id')->on('provinces')->cascadeOnDelete();
            $table->index(['province_id', 'name']);
            $table->index('name');
        });

        Schema::create('localities', function (Blueprint $table) {
            $table->string('id', 20)->primary();
            $table->string('department_id', 15);
            $table->string('province_id', 10);
            $table->string('name', 150);
            $table->timestamps();

            $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete();
            $table->foreign('province_id')->references('id')->on('provinces')->cascadeOnDelete();
            $table->index(['department_id', 'name']);
            $table->index(['province_id', 'name']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('localities');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('provinces');
    }
};

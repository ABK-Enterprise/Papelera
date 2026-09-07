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
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('legal_name')->nullable()->after('name');
            $table->string('tax_condition', 50)->default('responsable_inscripto')->after('cuit_tax_id');
            $table->string('address')->nullable()->after('tax_condition');
            $table->string('city', 100)->nullable()->after('address');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->string('phone', 50)->nullable()->after('postal_code');
            $table->string('email')->nullable()->after('phone');
            $table->string('gross_income_number', 50)->nullable()->after('email');
            $table->date('start_activity_date')->nullable()->after('gross_income_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name',
                'tax_condition',
                'address',
                'city',
                'state',
                'postal_code',
                'phone',
                'email',
                'gross_income_number',
                'start_activity_date',
            ]);
        });
    }
};

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
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->foreignId('delivery_user_id')
                ->nullable()
                ->after('debt_amount')
                ->constrained('users')
                ->nullOnDelete();

            $table->string('delivery_failure_reason', 50)
                ->nullable()
                ->after('delivery_user_id');

            $table->text('delivery_notes')
                ->nullable()
                ->after('delivery_failure_reason');

            $table->index(['tenant_id', 'delivery_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'delivery_user_id', 'status']);
            $table->dropConstrainedForeignId('delivery_user_id');
            $table->dropColumn(['delivery_failure_reason', 'delivery_notes']);
        });
    }
};

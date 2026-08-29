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
        if (! Schema::hasColumn('tenants', 'default_bulk_discount_percentage')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->decimal('default_bulk_discount_percentage', 5, 2)->default(0.00);
            });
        }

        if (! Schema::hasColumn('products', 'bulk_discount_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('bulk_discount_type', 20)->default('inherited');
            });
        }

        if (! Schema::hasColumn('products', 'custom_bulk_discount_percentage')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('custom_bulk_discount_percentage', 5, 2)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'bulk_discount_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['bulk_discount_type']);
            });
        }

        if (Schema::hasColumn('products', 'custom_bulk_discount_percentage')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn(['custom_bulk_discount_percentage']);
            });
        }

        if (Schema::hasColumn('tenants', 'default_bulk_discount_percentage')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn(['default_bulk_discount_percentage']);
            });
        }
    }
};

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
        if (! Schema::hasColumn('customers', 'current_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->decimal('current_balance', 12, 2)->default(0.00)->after('credit_limit');
            });
        }

        if (! Schema::hasColumn('customers', 'credit_due_days')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->integer('credit_due_days')->default(30)->after('current_balance');
            });
        }

        if (! Schema::hasTable('customer_account_movements')) {
            Schema::create('customer_account_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->foreignId('customer_order_id')->nullable()->constrained('customer_orders')->nullOnDelete();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 30); // charge, payment, adjustment, credit_note, debit_note
                $table->string('description');
                $table->decimal('amount', 12, 2);
                $table->decimal('balance_after', 12, 2);
                $table->date('due_date')->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->string('reference_number', 100)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'customer_id']);
                $table->index(['tenant_id', 'type']);
                $table->index(['customer_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_account_movements');

        if (Schema::hasColumn('customers', 'credit_due_days')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn(['credit_due_days']);
            });
        }

        if (Schema::hasColumn('customers', 'current_balance')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn(['current_balance']);
            });
        }
    }
};

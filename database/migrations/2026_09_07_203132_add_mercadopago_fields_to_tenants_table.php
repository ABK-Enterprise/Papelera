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
            $table->string('mp_user_id', 100)->nullable()->after('default_bulk_discount_percentage');
            $table->text('mp_access_token')->nullable()->after('mp_user_id');
            $table->string('mp_public_key', 255)->nullable()->after('mp_access_token');
            $table->text('mp_refresh_token')->nullable()->after('mp_public_key');
            $table->boolean('mp_live_mode')->default(false)->after('mp_refresh_token');
            $table->string('mp_nickname', 150)->nullable()->after('mp_live_mode');
            $table->string('mp_email', 150)->nullable()->after('mp_nickname');
            $table->string('mp_pos_id', 100)->nullable()->default('SUC01POS01')->after('mp_email');
            $table->timestamp('mp_linked_at')->nullable()->after('mp_pos_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'mp_user_id',
                'mp_access_token',
                'mp_public_key',
                'mp_refresh_token',
                'mp_live_mode',
                'mp_nickname',
                'mp_email',
                'mp_pos_id',
                'mp_linked_at',
            ]);
        });
    }
};

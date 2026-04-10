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
        Schema::table('alias_recipients', function (Blueprint $table) {
            $table->index('recipient_id');
        });

        Schema::table('recipients', function (Blueprint $table) {
            $table->index(['user_id', 'pending', 'created_at'], 'recipients_user_pending_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alias_recipients', function (Blueprint $table) {
            $table->dropIndex('alias_recipients_recipient_id_index');
        });

        Schema::table('recipients', function (Blueprint $table) {
            $table->dropIndex('recipients_user_pending_created_at_index');
        });
    }
};

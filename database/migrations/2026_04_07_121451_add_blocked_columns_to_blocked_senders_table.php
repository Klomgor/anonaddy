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
        Schema::table('blocked_senders', function (Blueprint $table) {
            $table->after('value', function (Blueprint $table) {
                $table->unsignedInteger('blocked')->default(0);
                $table->timestamp('last_blocked')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blocked_senders', function (Blueprint $table) {
            $table->dropColumn(['blocked', 'last_blocked']);
        });
    }
};

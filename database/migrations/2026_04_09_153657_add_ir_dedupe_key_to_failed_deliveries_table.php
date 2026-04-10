<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Inbound rejection rows (email_type IR) use ir_dedupe_key (SHA-256 hex) for duplicate prevention.
     * Outbound rows leave ir_dedupe_key null; MySQL/SQLite allow many nulls under a unique index.
     */
    public function up(): void
    {
        Schema::table('failed_deliveries', function (Blueprint $table) {
            $table->string('ir_dedupe_key', 64)->nullable()->after('destination');
            $table->unique('ir_dedupe_key', 'failed_deliveries_ir_dedupe_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('failed_deliveries', function (Blueprint $table) {
            $table->dropUnique('failed_deliveries_ir_dedupe_key_unique');
            $table->dropColumn('ir_dedupe_key');
        });
    }
};

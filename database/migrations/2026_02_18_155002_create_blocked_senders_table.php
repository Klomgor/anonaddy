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
        Schema::create('blocked_senders', function (Blueprint $table) {
            $table->uuid('id');
            $table->uuid('user_id');
            $table->string('type', 10); // 'email' or 'domain'
            $table->string('value')->index();
            $table->timestamps();

            $table->primary('id');
            $table->unique(['user_id', 'type', 'value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blocked_senders');
    }
};

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
        Schema::table('in_scope_ips', function (Blueprint $table) {
            $table->string('ip_start', 45)->change();
            $table->string('ip_start', 45)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scope_ips', function (Blueprint $table) {
            //
        });
    }
};
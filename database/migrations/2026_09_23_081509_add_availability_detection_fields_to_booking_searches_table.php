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
        Schema::table('booking_searches', function (Blueprint $table) {
            $table->dateTime('detected_at')->nullable()->after('booked_at');
            $table->string('availability_url')->nullable()->after('detected_at');
            $table->string('availability_title')->nullable()->after('availability_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_searches', function (Blueprint $table) {
            $table->dropColumn(['detected_at', 'availability_url', 'availability_title']);
        });
    }
};

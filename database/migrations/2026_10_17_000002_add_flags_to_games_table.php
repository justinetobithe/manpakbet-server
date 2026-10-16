<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('badge')->nullable()->after('launch_url');
            $table->boolean('is_featured')->default(false)->after('badge');
            $table->unsignedBigInteger('plays_count')->default(0)->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['badge', 'is_featured', 'plays_count']);
        });
    }
};

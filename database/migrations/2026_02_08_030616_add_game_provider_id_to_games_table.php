<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->foreignId('game_provider_id')
                ->nullable()
                ->after('game_category_id')
                ->constrained('game_providers')
                ->nullOnDelete();

            $table->index(['game_provider_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_provider_id');
        });
    }
};

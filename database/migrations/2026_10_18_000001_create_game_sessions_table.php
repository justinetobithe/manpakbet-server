<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('provider_game_code')->nullable()->after('launch_url');
            $table->boolean('has_demo')->default(true)->after('provider_game_code');
        });

        Schema::create('game_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->string('driver');
            $table->string('mode')->default('real');
            $table->text('launch_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('device', 20)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('game_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_id')->nullable()->constrained()->nullOnDelete();
            $table->string('driver');
            $table->string('external_id');
            $table->string('round_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_before', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('status')->default('completed');
            $table->string('reference_external_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['driver', 'external_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('round_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rounds');
        Schema::dropIfExists('game_sessions');

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['provider_game_code', 'has_demo']);
        });
    }
};

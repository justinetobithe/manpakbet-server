<?php

use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('kyc_status')->default('unverified')->after('is_active');
            $table->date('birthdate')->nullable()->after('phone_verified_at');
        });

        Schema::create('kyc_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('birthdate');
            $table->string('nationality')->default('Filipino');
            $table->string('occupation')->nullable();
            $table->string('source_of_funds')->nullable();
            $table->string('address');
            $table->string('city');
            $table->string('province');
            $table->string('zip_code', 10)->nullable();
            $table->string('id_type');
            $table->string('id_number');
            $table->string('id_front_path');
            $table->string('id_back_path')->nullable();
            $table->string('selfie_path');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        AccessControl::sync();

        Role::query()->where('name', AccessControl::ROLE_STAFF)->where('guard_name', 'web')->first()?->givePermissionTo('kyc.review');
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_submissions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['kyc_status', 'birthdate']);
        });
    }
};

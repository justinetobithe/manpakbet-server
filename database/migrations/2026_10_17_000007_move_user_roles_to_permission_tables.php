<?php

use App\Support\AccessControl;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        AccessControl::sync();

        if (!Schema::hasColumn('users', 'role')) {
            return;
        }

        $roles = Role::query()->pluck('id', 'name');
        $modelType = 'App\\Models\\User';

        DB::table('users')->select(['id', 'role'])->orderBy('id')->chunk(500, function ($users) use ($roles, $modelType) {
            $rows = [];

            foreach ($users as $user) {
                $name = in_array($user->role, AccessControl::PROTECTED_ROLES, true) ? $user->role : AccessControl::ROLE_USER;
                $rows[] = ['role_id' => $roles[$name], 'model_type' => $modelType, 'model_id' => $user->id];
            }

            if ($rows) {
                DB::table('model_has_roles')->insertOrIgnore($rows);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('user')->after('password');
            });
        }
    }
};

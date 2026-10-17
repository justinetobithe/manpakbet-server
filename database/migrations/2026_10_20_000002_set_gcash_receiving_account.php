<?php

use App\Support\Settings;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Settings::update(['payments' => ['gcash_account_number' => '09276192326']]);
    }

    public function down(): void
    {
    }
};

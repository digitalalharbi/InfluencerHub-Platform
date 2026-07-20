<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone')->nullable()->after('email');
            $t->boolean('is_active')->default(true)->after('phone');
            $t->boolean('is_system_admin')->default(false)->after('is_active'); // منصة عليا (cross-tenant)
            $t->boolean('must_change_password')->default(false)->after('is_system_admin');
            $t->string('two_factor_secret')->nullable()->after('must_change_password');
            $t->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $t->timestamp('last_login_at')->nullable();
            $t->softDeletes();
        });
    }
    public function down(): void {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['phone','is_active','is_system_admin','must_change_password','two_factor_secret','two_factor_confirmed_at','last_login_at','deleted_at']);
        });
    }
};

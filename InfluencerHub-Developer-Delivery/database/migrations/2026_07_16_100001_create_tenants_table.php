<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('deployment_mode', 20)->default('saas'); // saas|dedicated|self_hosted
            $t->string('status', 20)->default('active');        // active|suspended|trial
            $t->json('settings')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index('status');
        });
    }
    public function down(): void { Schema::dropIfExists('tenants'); }
};

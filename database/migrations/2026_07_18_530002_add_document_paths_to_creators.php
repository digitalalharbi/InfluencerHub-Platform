<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('creators', function (Blueprint $t) {
            $t->string('avatar_path')->nullable();
            $t->string('mowthooq_document_path')->nullable();
            $t->string('iban_document_path')->nullable();
        });
        Schema::table('creator_portfolios', function (Blueprint $t) {
            $t->string('title')->nullable()->after('type');
            $t->string('media_type', 20)->nullable()->after('title');
        });
    }
    public function down(): void {
        Schema::table('creators', fn (Blueprint $t) => $t->dropColumn(['avatar_path','mowthooq_document_path','iban_document_path']));
        Schema::table('creator_portfolios', fn (Blueprint $t) => $t->dropColumn(['title','media_type']));
    }
};

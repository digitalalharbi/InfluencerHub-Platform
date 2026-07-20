<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('custom_field_definitions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->string('entity_type', 40);   // client|brand
            $t->string('key', 60);           // مُعرّف آلي فريد ضمن (tenant, entity_type)
            $t->string('label');
            $t->string('type', 20);          // text|textarea|number|date|datetime|boolean|select|multiselect|url|email|phone
            $t->boolean('is_required')->default(false);
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'entity_type', 'key']);
        });
        Schema::create('custom_field_options', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $t->string('value', 120);
            $t->string('label');
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('custom_field_values', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $t->foreignId('definition_id')->constrained('custom_field_definitions')->cascadeOnDelete();
            $t->string('entity_type', 40);   // client|brand
            $t->unsignedBigInteger('entity_id');
            $t->text('value')->nullable();   // مُخزّن كنص؛ multiselect كـJSON
            $t->timestamps();
            $t->unique(['definition_id', 'entity_type', 'entity_id']);
            $t->index(['tenant_id', 'entity_type', 'entity_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('custom_field_values');
        Schema::dropIfExists('custom_field_options');
        Schema::dropIfExists('custom_field_definitions');
    }
};

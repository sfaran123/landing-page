<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sd_layouts')) {
            Schema::create('sd_layouts', function (Blueprint $t) {
                $t->id();
                $t->string('scope', 20);            // business_type | role | user
                $t->string('scope_id', 64);
                $t->longText('layout');             // JSON {version, items:[…]}
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->unique(['scope', 'scope_id']);
            });
        }
        if (!Schema::hasTable('sd_settings')) {
            Schema::create('sd_settings', function (Blueprint $t) {
                $t->string('setting_key', 64)->primary();
                $t->text('value');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sd_layouts');
        Schema::dropIfExists('sd_settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mind_map_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surah_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('mind_map_nodes')->nullOnDelete();
            $table->text('label');
            $table->integer('ayah_start')->nullable();
            $table->integer('ayah_end')->nullable();
            $table->integer('level')->default(0);
            $table->string('type')->default('theme'); // theme, subtheme, ayah_group
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mind_map_nodes');
    }
};

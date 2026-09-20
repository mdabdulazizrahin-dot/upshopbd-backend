<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courier_settings', function (Blueprint $table) {
            $table->id();
            $table->string('courier_name'); // steadfast, pathao, redx
            $table->string('display_name');
            $table->string('api_key')->nullable();
            $table->string('api_secret')->nullable();
            $table->string('access_token')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->decimal('default_weight', 5, 2)->default(0.5);
            $table->timestamps();
        });

        // Default data insert
        DB::table('courier_settings')->insert([
            ['courier_name' => 'steadfast', 'display_name' => 'Steadfast', 'is_enabled' => false, 'is_default' => true, 'default_weight' => 0.5, 'created_at' => now(), 'updated_at' => now()],
            ['courier_name' => 'pathao', 'display_name' => 'Pathao', 'is_enabled' => false, 'is_default' => false, 'default_weight' => 0.5, 'created_at' => now(), 'updated_at' => now()],
            ['courier_name' => 'redx', 'display_name' => 'RedX', 'is_enabled' => false, 'is_default' => false, 'default_weight' => 0.5, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('courier_settings');
    }
};

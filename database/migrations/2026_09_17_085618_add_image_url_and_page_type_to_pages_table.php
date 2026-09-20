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
        Schema::table('pages', function (Blueprint $table) {
            if (!Schema::hasColumn('pages', 'image_url')) {
                $table->string('image_url', 500)->nullable()->after('content');
            }
            if (!Schema::hasColumn('pages', 'page_type')) {
                $table->string('page_type', 50)->default('page')->after('slug');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            if (Schema::hasColumn('pages', 'image_url')) {
                $table->dropColumn('image_url');
            }
            if (Schema::hasColumn('pages', 'page_type')) {
                $table->dropColumn('page_type');
            }
        });
    }
};

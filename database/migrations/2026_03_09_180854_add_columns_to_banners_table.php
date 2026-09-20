<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasColumn('banners', 'subtitle')) {
                $table->string('subtitle')->nullable()->after('title');
            }
            if (!Schema::hasColumn('banners', 'link_url')) {
                $table->string('link_url')->nullable()->after('image_url');
            }
            if (!Schema::hasColumn('banners', 'button_text')) {
                $table->string('button_text')->nullable()->after('link_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['subtitle', 'link_url', 'button_text']);
        });
    }
};

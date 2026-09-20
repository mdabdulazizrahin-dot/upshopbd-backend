<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'ip_address')) {
                $table->string('ip_address')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('orders', 'is_suspicious')) {
                $table->boolean('is_suspicious')->default(false)->after('order_status');
            }
            if (!Schema::hasColumn('orders', 'suspicious_reason')) {
                $table->string('suspicious_reason')->nullable()->after('is_suspicious');
            }
        });

        if (!Schema::hasTable('blocked_entities')) {
            Schema::create('blocked_entities', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20); // 'ip' or 'phone'
                $table->string('value')->index();
                $table->string('reason')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'ip_address')) {
                $table->dropColumn('ip_address');
            }
            if (Schema::hasColumn('orders', 'is_suspicious')) {
                $table->dropColumn('is_suspicious');
            }
            if (Schema::hasColumn('orders', 'suspicious_reason')) {
                $table->dropColumn('suspicious_reason');
            }
        });

        Schema::dropIfExists('blocked_entities');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'customer_name')) {
                $table->string('customer_name')->nullable();
            }
            if (!Schema::hasColumn('orders', 'customer_phone')) {
                $table->string('customer_phone')->nullable();
            }
            if (!Schema::hasColumn('orders', 'customer_email')) {
                $table->string('customer_email')->nullable();
            }
            if (!Schema::hasColumn('orders', 'delivery_address')) {
                $table->text('delivery_address')->nullable();
            }
            if (!Schema::hasColumn('orders', 'district')) {
                $table->string('district')->nullable();
            }
            if (!Schema::hasColumn('orders', 'upazila')) {
                $table->string('upazila')->nullable();
            }
            if (!Schema::hasColumn('orders', 'order_note')) {
                $table->text('order_note')->nullable();
            }
            if (!Schema::hasColumn('orders', 'delivery_type')) {
                $table->string('delivery_type')->nullable();
            }
            if (!Schema::hasColumn('orders', 'delivery_charge')) {
                $table->decimal('delivery_charge', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('orders', 'subtotal')) {
                $table->decimal('subtotal', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('orders', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->default(0);
            }
            if (!Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->default('cod');
            }
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status')->default('pending');
            }
            if (!Schema::hasColumn('orders', 'order_status')) {
                $table->string('order_status')->default('pending');
            }
            if (!Schema::hasColumn('orders', 'order_number')) {
                $table->string('order_number')->nullable();
            }
        });
    }

    public function down(): void
    {
        //
    }
};

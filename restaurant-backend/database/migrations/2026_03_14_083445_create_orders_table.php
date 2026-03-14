// database/migrations/2024_01_01_000005_create_orders_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->comment('pelayan');
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();

            // Status
            $table->enum('status', ['open', 'processing', 'completed', 'cancelled', 'paid', 'refunded'])->default('open');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');
            $table->enum('payment_method', ['cash', 'card', 'qris', 'transfer', 'other'])->nullable();
            $table->json('payment_details')->nullable();

            // Financial
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(11.00);
            $table->decimal('service_charge', 15, 2)->default(0);
            $table->decimal('service_charge_rate', 5, 2)->default(5.00);
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);

            // Notes
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Timestamps
            $table->datetime('order_date');
            $table->datetime('completed_at')->nullable();
            $table->datetime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            // Audit
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('order_number');
            $table->index('status');
            $table->index('payment_status');
            $table->index('order_date');
            $table->index('user_id');
            $table->index('cashier_id');
            $table->index('customer_id');
            $table->index('table_id');
            $table->index(['order_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

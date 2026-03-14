// database/migrations/2024_01_01_000006_create_order_items_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('menu_item_name');
            $table->decimal('price', 12, 2);
            $table->integer('quantity');
            $table->json('modifiers')->nullable();
            $table->text('notes')->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'preparing', 'ready', 'served', 'cancelled'])->default('pending');

            // Cooking tracking
            $table->foreignId('cooked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('cooked_at')->nullable();

            // Serving tracking
            $table->foreignId('served_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('served_at')->nullable();

            // Cancellation tracking
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();

            // Financial
            $table->decimal('subtotal', 15, 2);

            $table->softDeletes();
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index(['order_id', 'status']);
            $table->index('menu_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

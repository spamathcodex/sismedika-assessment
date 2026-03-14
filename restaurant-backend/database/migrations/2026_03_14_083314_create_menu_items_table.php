// database/migrations/2024_01_01_000003_create_menu_items_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50)->unique()->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('cost', 12, 2)->nullable();
            $table->enum('type', ['food', 'beverage', 'snack', 'dessert', 'other'])->default('food');
            $table->string('image')->nullable();
            $table->json('images')->nullable();
            $table->json('ingredients')->nullable();
            $table->json('nutrition_info')->nullable();
            $table->json('allergens')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->boolean('is_recommended')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_taxable')->default(true);
            $table->integer('preparation_time')->nullable()->comment('in minutes');
            $table->integer('calories')->nullable();
            $table->integer('sort_order')->default(0);
            $table->integer('stock')->nullable()->comment('null = unlimited');
            $table->integer('min_order')->default(1);
            $table->integer('max_order')->nullable();
            $table->string('unit')->default('pcs');
            $table->json('variants')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('code');
            $table->index('slug');
            $table->index('type');
            $table->index('is_available');
            $table->index('is_popular');
            $table->index('sort_order');
            $table->fullText(['name', 'description']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};

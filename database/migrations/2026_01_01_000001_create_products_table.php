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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('sku');
            $table->integer('quantity')->default(0);
            $table->string('batch_number')->nullable();
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date');
            $table->integer('safety_stock')->default(0);
            $table->string('image_url')->nullable();
            $table->jsonb('extraction_metadata')->nullable();
            $table->timestampsTz();

            // Indexes for multi-tenant querying and expiry analytics
            $table->index(['user_id', 'expiry_date']);
            $table->index(['user_id', 'sku']);
            $table->index(['user_id', 'batch_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

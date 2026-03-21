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
        Schema::create('referral_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->onDelete('cascade');
            $table->foreignId('parent_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('child_user_id')->constrained('users')->onDelete('cascade');
            $table->string('parent_slab_name');
            $table->string('child_slab_name');
            $table->decimal('parent_slab_percentage', 10, 2);
            $table->decimal('child_slab_percentage', 10, 2);
            $table->decimal('referral_commission_amount', 15, 2);
            $table->decimal('allocated_amount', 15, 2);
            $table->decimal('area_sold', 10, 2);
            $table->integer('level');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Indexes for better query performance
            $table->index('sale_id');
            $table->index('parent_user_id');
            $table->index('child_user_id');
            $table->index('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_commissions');
    }
};

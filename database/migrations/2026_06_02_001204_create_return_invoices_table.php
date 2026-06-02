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
        Schema::create('return_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // المحاسب اللي استلم المرتجع
            $table->foreignId('client_id')->nullable()->constrained(); // العميل (ممكن زبون طياري)
            $table->decimal('total_amount', 15, 2);
            $table->enum('return_type', ['cash', 'credit'])->default('cash');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_invoices');
    }
};

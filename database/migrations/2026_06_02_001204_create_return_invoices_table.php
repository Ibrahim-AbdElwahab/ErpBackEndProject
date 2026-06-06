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
            $table->enum('type', ['client', 'supplier']); // نوع المرتجع
            $table->foreignId('client_id')->nullable(); // لو المرتجع لعميل
            $table->foreignId('supplier_id')->nullable(); // لو المرتجع لمورد
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0); // الفلوس اللي ادفعت كاش وقت المرتجع
            $table->foreignId('user_id');
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

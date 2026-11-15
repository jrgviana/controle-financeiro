<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_installment_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_installment_id')->unique()->constrained('credit_card_installments')->restrictOnDelete();
            $table->foreignId('original_invoice_id')->constrained('credit_card_invoices')->restrictOnDelete();
            $table->foreignId('target_invoice_id')->constrained('credit_card_invoices')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_installment_plans');
    }
};

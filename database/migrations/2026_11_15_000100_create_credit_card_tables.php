<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_cards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('issuer', 100)->nullable();
            $table->string('brand', 50)->nullable();
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->unsignedTinyInteger('closing_day');
            $table->unsignedTinyInteger('due_day');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['user_id', 'active']);
        });

        Schema::create('credit_card_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->constrained()->restrictOnDelete();
            $table->date('due_on');
            $table->timestamps();
            $table->unique(['credit_card_id', 'due_on']);
        });

        Schema::create('credit_card_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_id')->constrained()->restrictOnDelete();
            $table->date('purchased_on');
            $table->string('description', 160);
            $table->string('category', 30);
            $table->decimal('amount', 14, 2);
            $table->unsignedSmallInteger('installments_count');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purchased_on']);
        });

        Schema::create('credit_card_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_purchase_id')->constrained()->restrictOnDelete();
            $table->foreignId('credit_card_invoice_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['credit_card_purchase_id', 'installment_number']);
        });

        Schema::create('credit_card_invoice_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_invoice_id')->constrained()->restrictOnDelete();
            $table->string('description', 160);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('credit_card_invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('credit_card_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->index(['financial_account_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_invoice_payments');
        Schema::dropIfExists('credit_card_invoice_charges');
        Schema::dropIfExists('credit_card_installments');
        Schema::dropIfExists('credit_card_purchases');
        Schema::dropIfExists('credit_card_invoices');
        Schema::dropIfExists('credit_cards');
    }
};

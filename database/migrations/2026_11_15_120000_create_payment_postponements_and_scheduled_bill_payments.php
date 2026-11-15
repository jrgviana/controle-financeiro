<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_postponements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('payable_key', 180);
            $table->date('from_month');
            $table->date('to_month');
            $table->string('description', 160);
            $table->decimal('remaining_amount', 14, 2);
            $table->string('reason', 500)->nullable();
            $table->string('source', 16)->default('manual');
            $table->timestamps();
            $table->unique(['user_id', 'payable_key', 'from_month'], 'pay_postpone_unique_month');
            $table->index(['user_id', 'to_month']);
        });

        Schema::create('scheduled_bill_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheduled_bill_id')->constrained()->restrictOnDelete();
            $table->foreignId('daily_expense_id')->unique()->constrained('daily_expenses')->restrictOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'scheduled_bill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_bill_payments');
        Schema::dropIfExists('payment_postponements');
    }
};

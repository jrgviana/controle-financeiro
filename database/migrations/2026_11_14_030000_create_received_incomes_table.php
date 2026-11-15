<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('received_incomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')
                ->constrained('financial_accounts')
                ->restrictOnDelete();
            $table->date('received_on');
            $table->string('description', 160);
            $table->string('category', 30);
            $table->decimal('amount', 14, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'received_on']);
            $table->index(['financial_account_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('received_incomes');
    }
};

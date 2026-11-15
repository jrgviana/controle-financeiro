<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('to_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->date('transferred_on');
            $table->decimal('amount', 14, 2);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'transferred_on']);
            $table->index(['from_account_id', 'transferred_on']);
            $table->index(['to_account_id', 'transferred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
    }
};

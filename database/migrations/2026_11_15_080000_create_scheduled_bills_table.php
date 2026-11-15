<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('description', 160);
            $table->string('category', 30)->default('outros');
            $table->date('due_on');
            $table->decimal('amount', 14, 2);
            $table->string('notes', 500)->nullable();
            $table->foreignId('daily_expense_id')->nullable()->unique()
                ->constrained('daily_expenses')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_bills');
    }
};

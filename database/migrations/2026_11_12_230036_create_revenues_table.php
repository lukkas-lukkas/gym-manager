<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One revenue per enrollment. `price_in_cents` is the plan price on the day of the enrollment, so later
     * price changes never rewrite history. `member_id` is copied from the enrollment so a member's charges
     * can be listed without a join.
     */
    public function up(): void
    {
        Schema::create('revenues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->unique()->constrained();
            $table->foreignId('member_id')->constrained();
            $table->unsignedInteger('price_in_cents');
            $table->date('due_on');
            $table->string('status', 20);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['status', 'due_on']);
            $table->index('due_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenues');
    }
};

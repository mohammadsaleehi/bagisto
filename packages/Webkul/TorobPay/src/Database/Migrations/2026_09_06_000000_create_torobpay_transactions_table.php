<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('torobpay_transactions', function (Blueprint $table) {
            $table->id();

            $table->integer('code')->nullable();

            $table->text('message')->nullable();

            $table->text('token')->nullable();

            $table->string('reference_id', 255)->nullable();

            $table->string('transaction_id', 255)
                ->unique();

            $table->unsignedBigInteger('order_id')->nullable();

            $table->unsignedBigInteger('amount');

            $table->enum('status', [
                'pending',
                'success',
                'failed',
            ])->default('pending');

            $table->json('request_data')->nullable();

            $table->json('response_data')->nullable();

            $table->timestamps();

            $table->index('order_id');
            $table->index('reference_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('torobpay_transactions');
    }
};
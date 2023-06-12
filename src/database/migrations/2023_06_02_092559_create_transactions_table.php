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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')
                ->primary();

            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users');

            $table->string('address', 150)->nullable();
            $table->float('weight')->nullable();
            $table->integer('quantity')->nullable();
            $table->integer('total_selling_price')->nullable();
            $table->integer('total_pickup_cost')->nullable();
            $table->dateTime('pickup_date')->nullable();
            $table->string('status', 15)->nullable();
            $table->string('account_type',30 )->nullable();
            $table->string('account_number', 25)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};

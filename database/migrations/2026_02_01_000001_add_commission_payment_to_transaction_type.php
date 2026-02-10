<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Modify the enum to include 'commission_payment'
        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN transaction_type ENUM('cash_submission', 'online_payment', 'commission_payment') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE payment_transactions MODIFY COLUMN transaction_type ENUM('cash_submission', 'online_payment') NOT NULL");
    }
};

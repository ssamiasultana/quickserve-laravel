<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fix scheduled_at column: Change from TIMESTAMP to DATETIME.
     * 
     * The TIMESTAMP column type in MariaDB/MySQL automatically gets
     * "ON UPDATE CURRENT_TIMESTAMP" for the first timestamp column,
     * which causes scheduled_at to be OVERWRITTEN with the current time
     * whenever ANY column in the row is updated (e.g., during payment callbacks).
     * 
     * DATETIME does not have this behavior and stores the value as-is
     * without implicit timezone conversion.
     */
    public function up()
    {
        // Change scheduled_at from TIMESTAMP to DATETIME
        // This removes ON UPDATE CURRENT_TIMESTAMP and prevents timezone conversion
        DB::statement('ALTER TABLE booking MODIFY scheduled_at DATETIME NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement('ALTER TABLE booking MODIFY scheduled_at TIMESTAMP NULL');
    }
};

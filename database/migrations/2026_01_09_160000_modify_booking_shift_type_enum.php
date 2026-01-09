<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Check if the enum already includes 'flexible'
        // MySQL doesn't support direct ENUM modification, so we use raw SQL
        // First, update any 'flexible' values to 'day' if they exist (though they shouldn't)
        DB::table('booking')
            ->where('shift_type', 'flexible')
            ->update(['shift_type' => 'day']);

        // Modify the enum to include 'flexible'
        DB::statement("ALTER TABLE `booking` MODIFY COLUMN `shift_type` ENUM('day', 'night', 'flexible') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Update any 'flexible' values back to 'day' before reverting
        DB::table('booking')
            ->where('shift_type', 'flexible')
            ->update(['shift_type' => 'day']);

        // Revert to original enum values
        DB::statement("ALTER TABLE `booking` MODIFY COLUMN `shift_type` ENUM('day', 'night') NOT NULL");
    }
};


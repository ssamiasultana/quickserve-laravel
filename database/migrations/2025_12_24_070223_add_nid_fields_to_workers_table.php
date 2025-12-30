<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('workers', function (Blueprint $table) {
            //
            $table->string('nid', 20)->unique()->nullable()->after('phone');
            $table->boolean('nid_verified')->default(false)->after('nid');
            $table->timestamp('nid_verified_at')->nullable()->after('nid_verified');
            $table->string('nid_front_image')->nullable()->after('image');
            $table->string('nid_back_image')->nullable()->after('nid_front_image');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('workers', function (Blueprint $table) {
            //
            $table->dropColumn([
                'nid',
                'nid_verified',
                'nid_verified_at',
                'nid_front_image',
                'nid_back_image'
            ]);
        
        });
    }
};

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
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique(); 
            $table->string('phone')->nullable();

            $table->string('age');
            $table->string('image')->nullable(); 
           
            $table->json('service_type'); 
            $table->json('expertise_of_service');
            $table->integer('expertise_of_service'); 
            $table->string('shift');
            $table->decimal('rating', 2, 1)->default(0);
            $table->text('feedback')->nullable(); 
            $table->boolean('is_active')->default(true);
           
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('workers');
    }
};

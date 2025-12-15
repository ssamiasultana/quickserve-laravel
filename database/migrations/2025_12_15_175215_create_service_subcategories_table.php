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
        Schema::create('service_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')
          ->constrained('services')
          ->cascadeOnDelete();

            $table->string('name');
            $table->decimal('base_price', 10, 2);
            $table->enum('unit_type', ['fixed', 'hourly'])->default('fixed');
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
        Schema::dropIfExists('service_subcategories');
    }
};

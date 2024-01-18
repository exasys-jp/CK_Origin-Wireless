<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLogConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('log_configs', function (Blueprint $table) {
            $table->id();
            $table->string('serviceid');
            $table->integer('configid');
            $table->string('bot');
            $table->integer('minutes');
            $table->string('findlost');
            $table->string('truefalse');
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
        Schema::dropIfExists('log_configs');
    }
}

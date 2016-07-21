<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateResourceSetTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('resource_set', function (Blueprint $table) {
            $table->increments('resource_set_id');
            $table->string('name', 255);
            $table->string('icon_uri', 255)->nullable();
            $table->string('user_id', 255)->nullable();
            $table->string('client_id', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('resource_set');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRsToDirectoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rs_to_directory', function (Blueprint $table) {
            $table->increments('id');
            $table->string('directory_id', 255)->nullable();
            $table->string('client_id', 255)->nullable();
            $table->tinyInteger('consent_public_publish_directory');
            $table->tinyInteger('consent_private_publish_directory');
            $table->tinyInteger('consent_last_activity');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rs_to_directory');
    }
}

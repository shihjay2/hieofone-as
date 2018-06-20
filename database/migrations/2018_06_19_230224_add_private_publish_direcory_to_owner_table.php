<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPrivatePublishDirecoryToOwnerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('owner', function (Blueprint $table) {
            $table->tinyInteger('private_publish_directory');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('owner', function (Blueprint $table) {
            $table->dropColumn('private_publish_directory');
        });
    }
}

<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePermissionTicketTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('permission_ticket', function (Blueprint $table) {
            $table->increments('permission_ticket_id');
            $table->bigInteger('permission_id');
            $table->string('ticket', 255);
            $table->timestamp('expires');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('permission_ticket');
    }
}

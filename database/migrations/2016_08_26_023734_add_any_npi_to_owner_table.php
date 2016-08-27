<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAnyNpiToOwnerTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('owner', function (Blueprint $table) {
            $table->tinyInteger('any_npi');
			$table->tinyInteger('login_direct');
			$table->tinyInteger('login_md_nosh');
			$table->tinyInteger('login_google');
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
            $table->dropColumn('any_npi');
			$table->dropColumn('login_direct');
			$table->dropColumn('login_md_nosh');
			$table->dropColumn('login_google');
        });
    }
}

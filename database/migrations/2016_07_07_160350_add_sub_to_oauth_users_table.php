<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSubToOauthUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('oauth_users', function (Blueprint $table) {
            $table->string('sub', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('npi', 255)->nullable();
            $table->string('picture', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('oauth_users', function (Blueprint $table) {
              $table->dropColumn('sub');
              $table->dropColumn('email');
              $table->dropColumn('npi');
              $table->dropColumn('picture');
        });
    }
}

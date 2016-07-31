<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddConsentLoginDirectToOauthClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->tinyInteger('consent_login_direct');
			$table->tinyInteger('consent_login_md_nosh');
			$table->tinyInteger('consent_login_google');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
            $table->dropColumn('consent_login_direct');
			$table->dropColumn('consent_login_md_nosh');
			$table->dropColumn('consent_login_google');
        });
    }
}

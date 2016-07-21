<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIdTokenToOauthAuthorizationCodesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('oauth_authorization_codes', function (Blueprint $table) {
            $table->longText('id_token')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('oauth_authorization_codes', function (Blueprint $table) {
            $table->dropColumn('id_token');
        });
    }
}

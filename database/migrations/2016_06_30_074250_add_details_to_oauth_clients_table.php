<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDetailsToOauthClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('oauth_clients', function (Blueprint $table) {
          $table->string('logo_uri', 255)->nullable();
          $table->string('client_name', 255)->nullable();
          $table->string('client_uri', 255)->nullable();
          $table->tinyInteger('authorized')->default(0);
          $table->tinyInteger('allow_introspection')->default(0);
          $table->string('claims_redirect_uris', 2000)->nullable();
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
            $table->dropColumn('logo_uri');
            $table->dropColumn('client_name');
            $table->dropColumn('client_uri');
            $table->dropColumn('authorized');
            $table->dropColumn('allow_introspection');
            $table->dropColumn('claims_redirect_uris');
        });
    }
}

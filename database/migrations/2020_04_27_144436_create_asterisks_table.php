<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAsterisksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('asterisks', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->string('server_address');
            $table->string('user');
            $table->string('password');
            $table->string('ari_user');
            $table->string('ari_password');
            $table->string('ari_port');
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
        Schema::dropIfExists('asterisks');
    }
}

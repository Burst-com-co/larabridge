<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAmericanLogisticGuidesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('american_logistic_guides', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->text('request')->nullable();
            $table->string('guide_number');
            $table->string('url')->nullable();
            $table->string('pdf_base64')->nullable();
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
        Schema::dropIfExists('american_logistic_guides');
    }
}

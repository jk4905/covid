<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCovidDataTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('covid_data', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index('Idx_date')->comment('日期');
            $table->string('country')->nullable()->comment('国家');
            $table->string('country_code')->nullable()->index('Idx_country_code')->comment('国家代码');
            $table->string('province')->nullable()->comment('省份');
            $table->string('province_code')->nullable()->index('Idx_province_code')->comment('省份代码');
            $table->string('city')->nullable()->comment('市区');
            $table->string('code')->nullable()->index('Idx_code')->comment('市区代码');
            $table->integer('confirmed')->default(0)->comment('确诊数');
            $table->integer('suspected')->default(0)->comment('疑似数');
            $table->integer('cured')->default(0)->comment('治愈数');
            $table->integer('dead')->default(0)->comment('死亡数');
            $table->integer('predicted')->default(0)->comment('预测值');
            $table->integer('risk')->default(0)->comment('风险值');
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
        Schema::dropIfExists('covid_data');
    }
}

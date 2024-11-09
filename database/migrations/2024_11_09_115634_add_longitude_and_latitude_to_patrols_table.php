<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLongitudeAndLatitudeToPatrolsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('patrols', function (Blueprint $table) {
            $table->decimal('longitude', 10, 7)->nullable()->after('location');
            $table->decimal('latitude', 10, 7)->nullable()->after('longitude');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('patrols', function (Blueprint $table) {
            $table->dropColumn(['longitude', 'latitude']);
        });
    }
}

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePatrolTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patrol', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id', 10)->unsigned();
            $table->string('security_name');
            $table->string('photo_selfie');
            $table->enum('patrol_type', [
                'checkpoint 1', 'checkpoint 2', 'checkpoint 3', 'checkpoint 4', 
                'checkpoint 5', 'checkpoint 6', 'checkpoint 7', 'checkpoint 8', 
                'checkpoint 9', 'checkpoint 10', 'checkpoint 11', 'checkpoint 12', 
                'checkpoint 13', 'checkpoint 14', 'checkpoint 15'
            ]);
            $table->decimal('longitude', 10, 7);
            $table->decimal('latitude', 10, 7);
            $table->text('description')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('patrol');
    }
}

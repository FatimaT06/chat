<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBiometricCredentialsTable extends Migration
{
    public function up()
    {
        Schema::create('biometric_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('credential_id')->unique(); // base64
            $table->timestamps();

            $table->foreign('user_id')->references('id_usuario')->on('usuarios')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('biometric_credentials');
    }
}

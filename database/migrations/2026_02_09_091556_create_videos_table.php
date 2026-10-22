<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('filename');
            $table->string('thumbnail')->nullable();
            $table->integer('duration')->default(0); // в секундах
            $table->string('format')->default('mp4');
            $table->string('quality')->default('HD');
            $table->integer('views')->default(0);
            $table->integer('likes')->default(0);
            $table->boolean('featured')->default(false);
            $table->integer('rating')->default(0); // просмотры + комментарии
            $table->timestamps();
            $table->index('rating');
        });
    }

    public function down()
    {
        Schema::dropIfExists('videos');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_video', function (Blueprint $table) {
            // PK покрывает model_id, но карточки видео ищут связи по video_id.
            $table->index('video_id', 'model_video_video_id_index');
        });

        Schema::table('tag_video', function (Blueprint $table) {
            $table->index('video_id', 'tag_video_video_id_index');
        });

        Schema::table('comments', function (Blueprint $table) {
            // withCount(['comments as comments_count' => approved]) и рейтинг.
            $table->index(['video_id', 'status'], 'comments_video_id_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('model_video', fn (Blueprint $table) => $table->dropIndex('model_video_video_id_index'));
        Schema::table('tag_video', fn (Blueprint $table) => $table->dropIndex('tag_video_video_id_index'));
        Schema::table('comments', fn (Blueprint $table) => $table->dropIndex('comments_video_id_status_index'));
    }
};

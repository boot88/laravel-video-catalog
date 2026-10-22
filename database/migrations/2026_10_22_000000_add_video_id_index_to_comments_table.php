<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Комментарии видео всегда выбираются по video_id (страница видео,
        // withCount('comments') для карточек на главной). В SQLite внешний ключ
        // не создаёт индекс автоматически, поэтому каждый такой запрос был
        // full scan по comments. created_at добавлен вторым столбцом, чтобы
        // покрыть ORDER BY created_at DESC.
        Schema::table('comments', function (Blueprint $table) {
            $table->index(['video_id', 'created_at'], 'comments_video_id_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_video_id_created_at_index');
        });
    }
};

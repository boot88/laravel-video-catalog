<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Антиспам-проверка выбирает последний комментарий по IP —
        // без составного индекса это full scan по comments на каждый коммент.
        Schema::table('comments', function (Blueprint $table) {
            $table->index(['ip_address', 'created_at'], 'comments_ip_address_created_at_index');
        });

        // Главная страница фильтрует по категории и сортирует по рейтингу.
        // Второй индекс покрывает сортировку без фильтра (ORDER BY rating, views, id)
        // — с ним СУБД читает индекс в обратном порядке вместо сортировки всей таблицы.
        Schema::table('videos', function (Blueprint $table) {
            $table->index(['category', 'rating'], 'videos_category_rating_index');
            $table->index(['rating', 'views', 'id'], 'videos_ranked_index');
        });

        // Индекс по одному rating стал избыточным (он префикс videos_ranked_index),
        // а каждый лишний индекс замедляет UPDATE, которые мы делаем на каждый просмотр/лайк.
        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('videos_rating_index');
        });

        // Приводим rating к актуальному значению (views + likes + комментарии)
        // одним запросом вместо save() в цикле.
        DB::table('videos')->update([
            'rating' => DB::raw(
                'views + likes + (select count(*) from comments where comments.video_id = videos.id)'
            ),
        ]);
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_ip_address_created_at_index');
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex('videos_category_rating_index');
            $table->dropIndex('videos_ranked_index');
            $table->index('rating', 'videos_rating_index');
        });
    }
};

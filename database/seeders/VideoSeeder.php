<?php

namespace Database\Seeders;

use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VideoSeeder extends Seeder
{
    public function run()
    {
        // Сначала комментарии: у них внешний ключ на videos, и на SQLite
        // удаление videos с существующими комментариями падает по FK.
        DB::table('comments')->delete();
        DB::table('videos')->truncate();

        Cache::forget('video_categories');
        Cache::forget('video_footer_stats');

        echo "Создание 160 видео (без копирования файлов)...\n";

        // Массив случайных описаний
        $descriptions = [
            'Захватывающие моменты из повседневной жизни',
            'Самые смешные и невероятные ситуации',
            'То, что заставит вас улыбнуться',
            'Невероятные совпадения и курьёзные случаи',
            'Лучшие моменты, собранные в одном видео',
            'То, что вы не увидите больше нигде',
            'Эмоции, которые трудно передать словами',
            'Уникальные кадры из разных уголков мира',
            'Моменты, которые вошли в историю',
            'То, о чем говорят все вокруг',
        ];

        $categories = ['runway', 'streetwear', 'haute-couture', 'accessories', 'beauty', 'lookbook', 'backstage', 'editorial', 'menswear', 'sustainable', 'kids-fashion'];

        // Метаданные соответствуют реальным файлам в storage/app/public/videos:
        // раньше duration/quality/format были случайными и не совпадали с файлом.
        $sourceFiles = [
            'fashion_source_1.mp4' => ['duration' => 177, 'quality' => '240p'],
            'fashion_source_2.mp4' => ['duration' => 17, 'quality' => 'Full HD'],
            'fashion_source_3.mp4' => ['duration' => 25, 'quality' => 'Full HD'],
            'fashion_source_4.mp4' => ['duration' => 177, 'quality' => '240p'],
        ];
        $sourceNames = array_keys($sourceFiles);

        for ($i = 1; $i <= 160; $i++) {
            $source = $sourceNames[($i - 1) % count($sourceNames)];
            $views = rand(100, 10000);
            $likes = rand(50, 5000);

            // created_at/updated_at не входят в $fillable, поэтому задаём их
            // после создания модели: иначе все видео получали текущую дату.
            $video = Video::create([
                'title' => "Fashion Collection Video #$i",
                'slug' => "fashion-collection-video-$i",
                'description' => $descriptions[array_rand($descriptions)]." - Часть $i",
                'filename' => $source,
                'thumbnail' => null,
                'duration' => $sourceFiles[$source]['duration'],
                'format' => strtoupper(pathinfo($source, PATHINFO_EXTENSION)),
                'quality' => $sourceFiles[$source]['quality'],
                'views' => $views,
                'likes' => $likes,
                // rating — денормализованная сумма views + likes + комментарии.
                'rating' => $views + $likes,
                'featured' => $i === 1,
                'category' => $categories[($i - 1) % count($categories)],
            ]);

            $video->created_at = now()->subDays(rand(0, 30));
            $video->updated_at = now();
            $video->save();

            echo "Создано видео {$i}/160: {$video->title}\n";
        }

        echo "Все 160 видео созданы без дублирования файлов!\n";
        echo "Теперь запустите: php artisan videos:generate-thumbnails --all\n";
    }
}

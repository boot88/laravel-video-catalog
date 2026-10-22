<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Video;
use App\Services\VideoThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Регрессионные тесты производительности: падают, если вернуть обратно N+1
 * в аксессоре comments_count, COUNT(*)-в-цикле, eager-загрузку всех mp4 на
 * главной или сломанный путь к ffmpeg.
 */
class VideoPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function seedVideos(int $count = 20): void
    {
        foreach (range(1, $count) as $i) {
            Video::create([
                'title' => "Видео {$i}",
                'slug' => "video-{$i}",
                'description' => 'Описание',
                'filename' => "video-{$i}.mp4",
                'duration' => 60 + $i,
                'format' => 'mp4',
                'quality' => 'HD',
                'views' => $i * 10,
                'likes' => $i,
                'rating' => $i * 11,
                'category' => 'fashion',
            ]);
        }
    }

    private function queryCount(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();
        } finally {
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        return $count;
    }

    /** Число запросов главной не должно зависеть от количества карточек (было ~3 запроса на видео). */
    public function test_index_page_query_count_is_bounded(): void
    {
        $this->seedVideos(60);

        $queries = $this->queryCount(fn () => $this->get('/')->assertOk());

        $this->assertLessThanOrEqual(
            20,
            $queries,
            "Главная страница выполнила {$queries} запросов при 60 видео — похоже на N+1"
        );
    }

    public function test_show_page_query_count_is_bounded(): void
    {
        $this->seedVideos(3);
        $video = Video::where('slug', 'video-2')->firstOrFail();

        Comment::create([
            'video_id' => $video->id,
            'username' => 'Тест',
            'content' => 'Комментарий',
            'ip_address' => '10.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $queries = $this->queryCount(fn () => $this->get('/video/'.$video->slug)->assertOk());

        $this->assertLessThanOrEqual(26, $queries, "Страница видео выполнила {$queries} запросов");
    }

    /** comments_count берётся из withCount('comments') и не делает отдельный COUNT(*). */
    public function test_comments_count_comes_from_eager_loaded_column(): void
    {
        $this->seedVideos(2);

        $video = Video::withCount('comments')->firstOrFail();

        $queries = $this->queryCount(function () use ($video) {
            $this->assertSame(0, $video->comments_count);
            $this->assertSame(0, $video->comments_count);
            $this->assertSame((int) $video->views + (int) $video->likes, $video->total_rating);
        });

        $this->assertSame(0, $queries, 'Обращение к comments_count/total_rating вызвало запрос в БД');
    }

    /** Пересчёт рейтингов — один UPDATE вместо save() в цикле по всем видео. */
    public function test_refresh_all_ratings_uses_bulk_update(): void
    {
        $this->seedVideos(30);

        $video = Video::where('slug', 'video-1')->firstOrFail();
        Video::whereKey($video->id)->update(['rating' => 0]);

        Comment::create([
            'video_id' => $video->id,
            'username' => 'Тест',
            'content' => 'Комментарий',
            'ip_address' => '10.0.0.2',
            'user_agent' => 'phpunit',
        ]);

        $queries = $this->queryCount(fn () => $this->assertSame(30, Video::refreshAllRatings()));

        $this->assertLessThanOrEqual(3, $queries, "Пересчёт рейтингов занял {$queries} запросов вместо одного UPDATE");
        $this->assertSame(10 + 1 + 1, (int) Video::whereKey($video->id)->value('rating'));
    }

    /** Комментарий обновляет рейтинг одним UPDATE, без refresh() + повторного COUNT(*). */
    public function test_storing_comment_keeps_rating_in_sync(): void
    {
        $this->seedVideos(1);
        $video = Video::firstOrFail();

        $queries = $this->queryCount(function () use ($video) {
            $this->post('/video/'.$video->slug.'/comment', [
                'username' => 'Тест',
                'content' => 'Отличное видео',
            ])->assertRedirect();
        });

        $video->refresh();

        $this->assertSame(1, $video->comments()->count());
        $this->assertSame((int) $video->views + 1 + (int) $video->likes, (int) $video->rating);
        $this->assertLessThanOrEqual(12, $queries, "Отправка комментария заняла {$queries} запросов");
    }

    /** Лайк обновляет likes и rating одной командой UPDATE. */
    public function test_like_updates_likes_and_rating_atomically(): void
    {
        $this->seedVideos(1);
        $video = Video::firstOrFail();

        $queries = $this->queryCount(function () use ($video) {
            $this->postJson('/video/'.$video->slug.'/like')->assertOk();
        });

        $video->refresh();

        $this->assertSame(2, (int) $video->likes);
        $this->assertSame((int) $video->views + (int) $video->likes, (int) $video->rating);
        $this->assertLessThanOrEqual(10, $queries, "Лайк занял {$queries} запросов");
    }

    /** Главная не тянет mp4 всех карточек сразу и не встраивает SVG-заглушки в HTML. */
    public function test_index_page_does_not_preload_videos_or_inline_placeholders(): void
    {
        $this->seedVideos(5);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data:image/svg+xml;base64', $html);
        $this->assertStringNotContainsString('<source src=', $html);
        $this->assertStringNotContainsString('/storage/videos/', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    /** Заглушка превью отдаётся отдельным ответом с длинным Cache-Control. */
    public function test_thumbnail_placeholder_is_cacheable_svg(): void
    {
        $response = $this->get('/thumbnail/placeholder/42.svg');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('max-age=31536000', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    /** Превью реально вырезается из видео файла через ffmpeg (проверка бинарника и быстрого seek). */
    public function test_thumbnail_is_extracted_from_video_file(): void
    {
        $sample = public_path('images/m2-res_1920p.mp4');

        if (! is_file($sample) || VideoThumbnailService::ffmpegBinary() === null) {
            $this->markTestSkipped('ffmpeg или тестовое видео недоступны');
        }

        $videosDir = storage_path('app/public/videos');
        if (! is_dir($videosDir)) {
            mkdir($videosDir, 0755, true);
        }
        copy($sample, $videosDir.'/perf-test.mp4');

        $video = Video::create([
            'title' => 'Perf Test',
            'slug' => 'perf-test',
            'filename' => 'perf-test.mp4',
            'duration' => 25,
            'views' => 0,
            'likes' => 0,
            'rating' => 0,
        ]);

        // В тестовой БД ID начинаются с 1, поэтому имя превью может совпасть с превью
        // реального видео из storage/app/public/thumbnails: сохраняем оригинал и вернём его.
        $thumbnailPath = storage_path('app/public/thumbnails/video_thumb_'.$video->id.'.jpg');
        $backup = is_file($thumbnailPath) ? file_get_contents($thumbnailPath) : null;

        try {
            $started = microtime(true);
            $generated = VideoThumbnailService::generateThumbnail($video, 10);
            $elapsed = microtime(true) - $started;

            $this->assertTrue($generated);

            $video->refresh();
            $this->assertSame('thumbnails/video_thumb_'.$video->id.'.jpg', $video->thumbnail);
            $this->assertFileExists(storage_path('app/public/'.$video->thumbnail));
            $this->assertLessThan(15.0, $elapsed, 'Кадр достаётся слишком долго: проверьте, что -ss стоит до -i');
        } finally {
            if ($backup !== null) {
                file_put_contents($thumbnailPath, $backup);
            } elseif (is_file($thumbnailPath)) {
                @unlink($thumbnailPath);
            }

            @unlink($videosDir.'/perf-test.mp4');
        }
    }
}

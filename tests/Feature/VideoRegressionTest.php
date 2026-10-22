<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\Video;
use App\Services\VideoProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Регрессионные тесты для исправлений: атомарные счётчики, обработка
 * комментариев, окно пагинации и выборка видео без настоящего превью.
 */
class VideoRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function createVideo(array $attributes = []): Video
    {
        return Video::query()->create(array_merge([
            'title' => 'Тестовое видео',
            'slug' => 'test-video',
            'filename' => 'test.mp4',
            'duration' => 125,
            'views' => 10,
            'likes' => 5,
            'rating' => 15,
            'category' => 'test',
        ], $attributes));
    }

    public function test_two_stale_models_increment_views_without_losing_updates(): void
    {
        $video = $this->createVideo();

        // Обе модели загружены до изменений — как два одновременных запроса.
        $first = Video::query()->findOrFail($video->id);
        $second = Video::query()->findOrFail($video->id);

        $first->registerView();
        $second->registerView();

        $fresh = $video->fresh();

        $this->assertSame(12, (int) $fresh->views);
        $this->assertSame(17, (int) $fresh->rating);
    }

    public function test_register_comment_added_without_preloaded_counter_uses_count_after_insert(): void
    {
        $video = $this->createVideo();

        Comment::create([
            'video_id' => $video->id,
            'username' => 'Гость',
            'content' => 'Комментарий',
            'ip_address' => '10.0.0.3',
            'user_agent' => 'phpunit',
        ]);

        // Свежая модель без withCount/loadCount: COUNT(*) уже включает комментарий,
        // поэтому прибавлять к нему ещё единицу нельзя.
        $fresh = Video::query()->findOrFail($video->id);
        $fresh->registerCommentAdded();

        $this->assertSame(1, $fresh->comments_count);
        $this->assertSame(16, (int) $fresh->rating);
        $this->assertSame(16, (int) $video->fresh()->rating);
    }

    public function test_comment_with_only_markup_is_rejected(): void
    {
        $video = $this->createVideo();

        $response = $this->from('/video/test-video')
            ->post('/video/test-video/comment', [
                'content' => '<b></b>',
                'username' => 'Гость',
            ]);

        $response->assertRedirect('/video/test-video');
        $response->assertSessionHasErrors('content');

        $this->assertDatabaseCount('comments', 0);
        $this->assertSame(15, (int) $video->fresh()->rating);
    }

    public function test_comment_is_cleaned_and_long_user_agent_is_truncated(): void
    {
        $video = $this->createVideo();

        $response = $this->withHeader('User-Agent', str_repeat('A', 1000))
            ->post('/video/test-video/comment', [
                'content' => '<b>Привет</b>',
                'username' => '<i>Боб</i>',
            ]);

        $response->assertSessionHasNoErrors();

        $comment = Comment::query()->firstOrFail();

        $this->assertSame('Привет', $comment->content);
        $this->assertSame('Боб', $comment->username);
        $this->assertSame(255, mb_strlen($comment->user_agent));
        $this->assertSame(16, (int) $video->fresh()->rating);
    }

    public function test_more_than_three_comments_per_hour_from_one_ip_are_rejected(): void
    {
        $video = $this->createVideo();

        foreach ([10, 20, 30] as $minutesAgo) {
            DB::table('comments')->insert([
                'video_id' => $video->id,
                'username' => 'Гость',
                'content' => 'Старый комментарий',
                'ip_address' => '10.0.0.9',
                'user_agent' => 'test',
                'created_at' => now()->subMinutes($minutesAgo),
                'updated_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->from('/video/test-video')
            ->post('/video/test-video/comment', [
                'content' => 'Ещё один комментарий',
            ]);

        $response->assertSessionHasErrors('content');
        $this->assertDatabaseCount('comments', 3);
    }

    public function test_pagination_window_follows_current_page(): void
    {
        for ($i = 1; $i <= 80; $i++) {
            $this->createVideo([
                'title' => 'Видео '.$i,
                'slug' => 'video-'.$i,
                'filename' => 'video-'.$i.'.mp4',
                'views' => 1000 - $i,
            ]);
        }

        // 80 видео по 10 на страницу = 8 страниц; проверяем пятую.
        $response = $this->get('/videos?per_page=10&page=5')->assertOk();
        $html = $response->getContent();

        // Окно пагинации должно тянуться за текущей страницей, а не всегда быть 1–5.
        $this->assertStringContainsString('page=7', $html);
        $this->assertStringContainsString('page=8', $html);
        $this->assertMatchesRegularExpression('/class="pagination__link is-active"[^>]*>\s*5\s*</u', $html);
    }

    public function test_quality_label_uses_smaller_side_and_does_not_call_1920_square_4k(): void
    {
        $this->assertSame('4K', VideoProbeService::qualityLabel(3840, 2160));
        $this->assertSame('Full HD', VideoProbeService::qualityLabel(1920, 1080));
        $this->assertSame('Full HD', VideoProbeService::qualityLabel(1080, 1920));
        $this->assertSame('Full HD', VideoProbeService::qualityLabel(1920, 1920));
        $this->assertSame('240p', VideoProbeService::qualityLabel(320, 240));
    }

    public function test_without_real_thumbnail_scope_skips_color_placeholders(): void
    {
        $missing = $this->createVideo(['slug' => 'missing']);

        $placeholder = $this->createVideo([
            'slug' => 'placeholder',
            'thumbnail' => 'thumbnails/color_thumb_abc123.jpg',
        ]);

        $real = $this->createVideo([
            'slug' => 'real',
            'thumbnail' => 'thumbnails/video_thumb_abc123.jpg',
        ]);

        $ids = Video::query()->withoutRealThumbnail()->pluck('id')->all();

        $this->assertContains($missing->id, $ids);
        $this->assertContains($placeholder->id, $ids);
        $this->assertNotContains($real->id, $ids);
    }
}

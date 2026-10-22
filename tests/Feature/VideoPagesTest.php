<?php

namespace Tests\Feature;

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Страницы списка/показа: нумерация карточек, per_page и MIME-тип источника.
 * Тесты работают только с БД (файлы и превью не создаются), поэтому реальный
 * storage не затрагивается.
 */
class VideoPagesTest extends TestCase
{
    use RefreshDatabase;

    private function seedVideos(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Video::query()->create([
                'title' => 'Видео '.$i,
                'slug' => 'video-'.$i,
                'filename' => 'video-'.$i.'.mp4',
                'duration' => 60,
                'views' => 1000 - $i,
                'likes' => 0,
                'category' => 'blondinki',
            ]);
        }
    }

    /** @return string[] номера бейджей рейтинга (#1, #2, ...) в порядке появления в HTML */
    private function rankBadges(string $html): array
    {
        preg_match_all('/rating-number">\s*([^<]*)</', $html, $matches);

        // В бейдже топ-3 есть префикс «🔥 », для сравнения оставляем только номер.
        return array_map(static function (string $badge): string {
            preg_match('/#\d+/', $badge, $number);

            return $number[0] ?? trim($badge);
        }, $matches[1]);
    }

    public function test_rank_badges_start_from_two_and_continue_across_pages(): void
    {
        $this->seedVideos(60);

        $firstPage = $this->get('/')->assertOk();
        $badges = $this->rankBadges($firstPage->getContent());

        // $badges[0] — featured-карточка (видео #1), значит первая карточка сетки
        // должна быть #2, а не #3 (раньше номер считался с лишним +1).
        $this->assertSame('#1', $badges[0]);
        $this->assertSame('#2', $badges[1]);
        $this->assertSame('#50', end($badges));

        $secondPage = $this->get('/?page=2')->assertOk();
        $secondPage->assertSee('ВИДЕО #51');

        // Нумерация продолжается, а не начинается заново с #1.
        $this->assertSame('#51', $this->rankBadges($secondPage->getContent())[0]);
        $this->assertSame('#52', $this->rankBadges($secondPage->getContent())[1]);
    }

    public function test_invalid_per_page_falls_back_to_default(): void
    {
        $this->seedVideos(60);

        // 50 видео на странице: 1 featured + 49 в сетке + 49 в мобильной сетке
        $response = $this->get('/?per_page=abc')->assertOk();

        $this->assertSame(99, substr_count($response->getContent(), 'class="video-card"'));
    }

    public function test_source_mime_type_follows_file_extension(): void
    {
        $mimeTypes = [
            'clip.mp4' => 'video/mp4',
            'clip.webm' => 'video/webm',
            'clip.mkv' => 'video/x-matroska',
            'clip.mov' => 'video/quicktime',
            'clip' => 'video/mp4',
        ];

        foreach ($mimeTypes as $filename => $expected) {
            $video = new Video(['filename' => $filename, 'format' => 'AVI']);

            $this->assertSame($expected, $video->mime_type, $filename);
        }
    }
}

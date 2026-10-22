<?php

namespace Tests\Feature;

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Публичные страницы: редакционная главная, каталог, пагинация и SEO-разметка.
 */
class VideoPagesTest extends TestCase
{
    use RefreshDatabase;

    private function seedVideos(int $count): void
    {
        foreach (range(1, $count) as $i) {
            Video::query()->create([
                'title' => 'Видео '.$i,
                'slug' => 'video-'.$i,
                'filename' => 'video-'.$i.'.mp4',
                'duration' => 60,
                'views' => 1000 - $i,
                'likes' => 0,
                'category' => 'blondinki',
                'published_at' => now()->subMinutes($count - $i),
            ]);
        }
    }

    public function test_homepage_uses_featured_video_for_hero_and_shows_new_section(): void
    {
        $this->seedVideos(30);

        $featured = Video::query()->create([
            'title' => 'Главный показ',
            'slug' => 'glavnyi-pokaz',
            'filename' => 'hero.mp4',
            'duration' => 90,
            'views' => 10,
            'likes' => 1,
            'category' => 'runway',
            'featured' => true,
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get('/')->assertOk();

        $response->assertSee($featured->title);
        $response->assertSee('Главный показ');
        $response->assertSee('Новое');
        $response->assertSee('Сейчас смотрят');
        $response->assertSee('Fashion Video Hub');
    }

    public function test_invalid_per_page_falls_back_to_default_catalog_page(): void
    {
        $this->seedVideos(60);

        $response = $this->get('/videos?per_page=abc')->assertOk();
        $html = $response->getContent();

        $this->assertSame(24, substr_count($html, 'data-video-card'));
        $this->assertStringContainsString('Видео 60', $html);
    }

    public function test_catalog_pagination_continues_on_second_page(): void
    {
        $this->seedVideos(60);

        $secondPage = $this->get('/videos?per_page=10&page=2')->assertOk();

        $secondPage->assertSee('Видео 50');
        $secondPage->assertDontSee('Видео 60');
        $secondPage->assertSee('page=3', false);
    }

    public function test_aspect_ratio_and_orientation_support_vertical_video(): void
    {
        $vertical = new Video(['width' => 1080, 'height' => 1920]);
        $landscape = new Video(['width' => 1920, 'height' => 1080]);

        $this->assertSame('9 / 16', $vertical->aspect_ratio);
        $this->assertSame('portrait', $vertical->orientation);
        $this->assertSame('16 / 9', $landscape->aspect_ratio);
        $this->assertSame('landscape', $landscape->orientation);
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

    public function test_video_page_contains_seo_and_videoobject_schema(): void
    {
        $video = Video::query()->create([
            'title' => 'SEO показ',
            'slug' => 'seo-pokaz',
            'filename' => 'seo.mp4',
            'description' => 'Описание для поисковых систем',
            'duration' => 75,
            'views' => 12,
            'likes' => 2,
            'category' => 'runway',
            'published_at' => now()->subDay(),
        ]);

        $response = $this->get('/video/'.$video->slug)->assertOk();

        $response->assertSee('<meta name="description" content="Описание для поисковых систем">', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('"@type": "VideoObject"', false);
        $response->assertSee('rel="canonical"', false);
    }

    public function test_homepage_does_not_load_full_video_sources(): void
    {
        $this->seedVideos(5);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('data:image/svg+xml;base64', $html);
        $this->assertStringNotContainsString('<source src=', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }
}

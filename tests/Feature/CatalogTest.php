<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FashionCollection;
use App\Models\ModelProfile;
use App\Models\Tag;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function video(array $attributes = []): Video
    {
        return Video::query()->create(array_merge([
            'title' => 'Показ',
            'slug' => 'pokaz',
            'filename' => 'pokaz.mp4',
            'description' => 'Описание',
            'duration' => 60,
            'views' => 0,
            'likes' => 0,
            'rating' => 0,
            'category' => 'runway',
        ], $attributes));
    }

    public function test_category_model_brand_and_collection_pages_render_their_videos(): void
    {
        $category = Category::query()->create(['name' => 'Подиум', 'slug' => 'runway', 'is_visible' => true]);
        $brand = Brand::query()->create(['name' => 'Atelier Nord', 'slug' => 'atelier-nord', 'is_visible' => true]);
        $collection = FashionCollection::query()->create([
            'brand_id' => $brand->id,
            'title' => 'Northern Light',
            'slug' => 'northern-light',
            'is_visible' => true,
        ]);
        $model = ModelProfile::query()->create(['name' => 'Александра', 'slug' => 'alexandra', 'is_visible' => true]);
        $tag = Tag::query()->create(['name' => 'подиум', 'slug' => 'runway-tag']);

        $video = $this->video([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'collection_id' => $collection->id,
        ]);

        $video->models()->sync([$model->id]);
        $video->tags()->sync([$tag->id]);

        $this->get('/category/runway')->assertOk()->assertSee('Показ');
        $this->get('/models')->assertOk()->assertSee('Александра');
        $this->get('/models/alexandra')->assertOk()->assertSee('Показ');
        $this->get('/brands')->assertOk()->assertSee('Atelier Nord');
        $this->get('/brands/atelier-nord')->assertOk()->assertSee('Показ');
        $this->get('/collections')->assertOk()->assertSee('Northern Light');
        $this->get('/collections/northern-light')->assertOk()->assertSee('Показ');
        $this->get('/tags/runway-tag')->assertOk()->assertSee('Показ');
    }

    public function test_search_finds_video_by_model_name(): void
    {
        $model = ModelProfile::query()->create(['name' => 'Александра', 'slug' => 'alexandra', 'is_visible' => true]);
        $video = $this->video();
        $video->models()->sync([$model->id]);

        $this->get('/search?q=Александра')->assertOk()->assertSee('Показ');
        $this->get('/search?q=нет-такого')->assertOk()->assertSee('Ничего не найдено');
    }

    public function test_favorites_toggle_is_stored_in_session(): void
    {
        $video = $this->video();

        $this->postJson('/video/pokaz/favorite')
            ->assertOk()
            ->assertJson(['success' => true, 'favorite' => true, 'count' => 1]);

        $this->get('/favorites')->assertOk()->assertSee('Показ');
        $this->assertSame([$video->id], session('favorite_videos'));

        $this->postJson('/video/pokaz/favorite')
            ->assertOk()
            ->assertJson(['success' => true, 'favorite' => false, 'count' => 0]);
    }

    public function test_view_is_counted_once_per_session(): void
    {
        $video = $this->video();

        $this->get('/video/pokaz')->assertOk();
        $this->get('/video/pokaz')->assertOk();

        $this->assertSame(1, (int) $video->fresh()->views);
        $this->assertDatabaseCount('view_events', 1);
    }

    public function test_sitemap_contains_published_videos_only(): void
    {
        $published = $this->video(['slug' => 'published', 'title' => 'Опубликовано']);
        $this->video(['slug' => 'draft', 'title' => 'Черновик', 'status' => 'draft']);

        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertSee('/video/published', false);
        $response->assertDontSee('/video/draft', false);
        $this->assertNotNull($published->slug);
    }

    public function test_comments_can_require_moderation(): void
    {
        config()->set('site.comments_require_moderation', true);

        $video = $this->video(['views' => 10, 'rating' => 10]);

        $this->post('/video/pokaz/comment', ['content' => 'На модерацию'])->assertRedirect();

        $this->assertDatabaseHas('comments', ['video_id' => $video->id, 'status' => 'pending']);
        $this->assertSame(10, (int) $video->fresh()->rating);
        $this->get('/video/pokaz')->assertOk()->assertDontSee('На модерацию');
    }

    public function test_import_reads_metadata_and_creates_taxonomy(): void
    {
        $dir = storage_path('framework/testing/import/fashionable');
        File::ensureDirectoryExists($dir);
        File::copy(public_path('images/m2-res_1920p.mp4'), $dir.'/look.mp4');

        file_put_contents($dir.'/metadata.json', json_encode([
            'look.mp4' => [
                'title' => 'Метаданные победили',
                'description' => 'Описание из metadata.json',
                'category' => 'Подиум',
                'brand' => 'Atelier Nord',
                'collection' => 'Northern Light',
                'models' => ['Александра'],
                'tags' => ['подиум', 'минимализм'],
                'credits' => 'Тестовый кредит',
                'published_at' => '2026-01-10',
                'featured' => true,
            ],
        ], JSON_UNESCAPED_UNICODE));

        try {
            $this->artisan('videos:import', ['folder' => $dir, '--skip-thumbnails' => true])
                ->assertSuccessful();

            $video = Video::query()->where('title', 'Метаданные победили')->firstOrFail();

            $this->assertSame('Описание из metadata.json', $video->description);
            $this->assertSame('Подиум', $video->categoryInfo->name);
            $this->assertSame('Atelier Nord', $video->brand->name);
            $this->assertSame('Northern Light', $video->collection->title);
            $this->assertSame('Александра', $video->models->first()->name);
            $this->assertCount(2, $video->tags);
            $this->assertSame('Тестовый кредит', $video->credits);
            $this->assertTrue($video->featured);
            $this->assertSame('2026-01-10', $video->published_at->toDateString());
        } finally {
            File::deleteDirectory($dir);
            @unlink(storage_path('app/public/videos/metadannye-pobedili.mp4'));
        }
    }

    public function test_public_media_video_is_available_without_storage_copy(): void
    {
        $video = new Video(['filename' => 'archive-show-1951.mp4']);

        $this->assertTrue($video->has_video);
        $this->assertStringContainsString('media/videos/archive-show-1951.mp4', $video->video_url);
    }
}

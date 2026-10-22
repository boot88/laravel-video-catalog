<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FashionCollection;
use App\Models\ModelProfile;
use App\Models\Tag;
use App\Models\Video;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VideoSeeder extends Seeder
{
    public function run(): void
    {
        // Сначала зависимые таблицы: на SQLite внешние ключи не дадут удалить
        // videos, пока на них ссылаются комментарии и пивоты.
        DB::table('view_events')->delete();
        DB::table('comments')->delete();
        DB::table('model_video')->delete();
        DB::table('tag_video')->delete();
        DB::table('videos')->delete();

        Cache::forget('video_categories');
        Cache::forget('video_footer_stats');

        $this->call(CatalogSeeder::class);

        $categories = Category::query()->ordered()->get();
        $brands = Brand::query()->orderBy('id')->get();
        $collections = FashionCollection::query()->with('brand')->orderBy('id')->get();
        $models = ModelProfile::query()->orderBy('id')->get();
        $tags = Tag::query()->orderBy('id')->get();

        $credits = collect(config('media_credits.videos', []))->keyBy('file');

        // Все ролики — короткие фрагменты из public/media/videos. Они лежат
        // в репозитории, поэтому демо работает без ручной загрузки в storage.
        $sources = [
            'archive-show-1951.mp4' => ['duration' => 15, 'width' => 960, 'height' => 720, 'quality' => '720p', 'poster' => 'media/posters/archive-show-1951.jpg'],
            'archive-euro-1970.mp4' => ['duration' => 15, 'width' => 960, 'height' => 720, 'quality' => '720p', 'poster' => 'media/posters/archive-euro-1970.jpg'],
            'archive-fashion-1951.mp4' => ['duration' => 15, 'width' => 960, 'height' => 720, 'quality' => '720p', 'poster' => 'media/posters/archive-fashion-1951.jpg'],
            'archive-atelier-1957.mp4' => ['duration' => 15, 'width' => 960, 'height' => 720, 'quality' => '720p', 'poster' => 'media/posters/archive-atelier-1957.jpg'],
            'archive-holland-runway.mp4' => ['duration' => 15, 'width' => 960, 'height' => 720, 'quality' => '720p', 'poster' => 'media/posters/archive-holland-runway.jpg'],
        ];

        $sourceNames = array_keys($sources);
        $total = 160;

        echo "Создание {$total} видео и каталога...\n";

        for ($i = 1; $i <= $total; $i++) {
            $source = $sourceNames[($i - 1) % count($sourceNames)];
            $sourceMeta = $sources[$source];
            $credit = $credits->get($source);

            $category = $categories[($i - 1) % $categories->count()];
            $collection = $collections[($i - 1) % $collections->count()];
            $brand = $collection->brand ?? $brands[($i - 1) % $brands->count()];
            $model = $models[($i - 1) % $models->count()];
            $secondModel = $models[($i + 2) % $models->count()];
            $tag = $tags[($i - 1) % $tags->count()];
            $secondTag = $tags[($i + 3) % $tags->count()];

            $look = intdiv($i - 1, $collections->count()) + 1;
            $title = $collection->title.' — look '.str_pad((string) $look, 2, '0', STR_PAD_LEFT);
            $views = random_int(120, 18000);
            $likes = random_int(10, 2400);
            $publishedAt = now()->subDays(($i * 7) % 120)->subHours($i % 24);

            $video = Video::query()->create([
                'title' => $title,
                'slug' => 'fashion-video-'.$i,
                'description' => $model->name.' в коллекции «'.$collection->title.'» бренда '.$brand->name.'. '
                    .$category->description,
                'filename' => $source,
                'thumbnail' => $sourceMeta['poster'],
                'duration' => $sourceMeta['duration'],
                'format' => 'MP4',
                'quality' => $sourceMeta['quality'],
                'views' => $views,
                'likes' => $likes,
                'rating' => $views + $likes,
                'featured' => $i <= 3,
                'category' => $category->slug,
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'collection_id' => $collection->id,
                'status' => 'published',
                'published_at' => $publishedAt,
                'credits' => 'Демо-фрагмент: '.($credit['title'] ?? $source).'. '
                    .($credit['author'] ?? 'Wikimedia Commons').', '.($credit['license'] ?? 'CC BY-SA').'.',
                'width' => $sourceMeta['width'],
                'height' => $sourceMeta['height'],
                'preview' => null,
                'thumbnail_time' => 3,
                'created_at' => $publishedAt,
                'updated_at' => now(),
            ]);

            $video->models()->sync(array_values(array_unique([$model->id, $secondModel->id])));
            $video->tags()->sync(array_values(array_unique([$tag->id, $secondTag->id])));

            if ($i % 20 === 0) {
                echo "  {$i}/{$total}\n";
            }
        }

        echo "Готово: {$total} видео, {$categories->count()} категорий, {$models->count()} моделей.\n";
        echo "Постеры и hover-превью уже лежат в public/media; для новых импортов используйте videos:generate-thumbnails и videos:generate-previews.\n";
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\FashionCollection;
use App\Models\ModelProfile;
use App\Models\Tag;
use App\Models\Video;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function models(): View
    {
        $models = ModelProfile::query()
            ->visible()
            ->withCount(['videos as published_videos_count' => fn (Builder $query) => $query->published()])
            ->orderBy('name')
            ->paginate(24);

        return view('models.index', [
            'models' => $models,
            'stats' => $this->stats(),
        ]);
    }

    public function model(ModelProfile $model): View
    {
        abort_unless($model->is_visible, 404);

        $videos = $model->videos()
            ->published()
            ->with(['categoryInfo', 'brand', 'models'])
            ->withCount(['comments as comments_count' => fn (Builder $query) => $query->approved()])
            ->newest()
            ->paginate(24);

        return view('models.show', [
            'model' => $model,
            'videos' => $videos,
            'stats' => $this->stats(),
        ]);
    }

    public function brands(): View
    {
        $brands = Brand::query()
            ->visible()
            ->withCount(['videos as published_videos_count' => fn (Builder $query) => $query->published()])
            ->withCount('collections')
            ->orderBy('name')
            ->paginate(24);

        return view('brands.index', [
            'brands' => $brands,
            'stats' => $this->stats(),
        ]);
    }

    public function brand(Brand $brand): View
    {
        abort_unless($brand->is_visible, 404);

        $brand->load(['collections' => fn ($query) => $query->where('is_visible', true)->orderByDesc('event_date')]);
        $brand->loadCount(['videos as published_videos_count' => fn (Builder $query) => $query->published()]);

        $videos = $brand->videos()
            ->published()
            ->with(['categoryInfo', 'brand', 'collection', 'models'])
            ->withCount(['comments as comments_count' => fn (Builder $query) => $query->approved()])
            ->newest()
            ->paginate(24);

        return view('brands.show', [
            'brand' => $brand,
            'videos' => $videos,
            'stats' => $this->stats(),
        ]);
    }

    public function collections(): View
    {
        $collections = FashionCollection::query()
            ->visible()
            ->with('brand')
            ->withCount(['videos as published_videos_count' => fn (Builder $query) => $query->published()])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->paginate(24);

        return view('collections.index', [
            'collections' => $collections,
            'stats' => $this->stats(),
        ]);
    }

    public function collection(FashionCollection $collection): View
    {
        abort_unless($collection->is_visible, 404);

        $collection->load('brand');

        $videos = $collection->videos()
            ->published()
            ->with(['categoryInfo', 'brand', 'collection', 'models'])
            ->withCount(['comments as comments_count' => fn (Builder $query) => $query->approved()])
            ->newest()
            ->paginate(24);

        return view('collections.show', [
            'collection' => $collection,
            'videos' => $videos,
            'stats' => $this->stats(),
        ]);
    }

    public function tag(Tag $tag): View
    {
        $videos = $tag->videos()
            ->published()
            ->with(['categoryInfo', 'brand', 'models', 'tags'])
            ->withCount(['comments as comments_count' => fn (Builder $query) => $query->approved()])
            ->newest()
            ->paginate(24);

        return view('tags.show', [
            'tag' => $tag,
            'videos' => $videos,
            'stats' => $this->stats(),
        ]);
    }

    private function stats(): array
    {
        return [
            'videos' => Video::query()->published()->count(),
            'views' => (int) Video::query()->published()->sum('views'),
            'comments' => \App\Models\Comment::query()->approved()->count(),
            'categories' => \App\Models\Category::query()->visible()->count(),
            'models' => ModelProfile::query()->visible()->count(),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Comment;
use App\Models\FashionCollection;
use App\Models\ModelProfile;
use App\Models\Tag;
use App\Models\Video;
use App\Models\ViewEvent;
use App\Services\VideoThumbnailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class VideoController extends Controller
{
    private const MAX_PER_PAGE = 60;

    private const DEFAULT_PER_PAGE = 24;

    public function index(): View
    {
        $base = Video::query()
            ->published()
            ->with(['categoryInfo', 'brand', 'collection', 'models'])
            ->withCount(['comments as comments_count' => fn (Builder $query) => $query->approved()]);

        $hero = (clone $base)
            ->where('featured', true)
            ->newest()
            ->first()
            ?? (clone $base)->newest()->first();

        $newVideos = (clone $base)
            ->when($hero, fn (Builder $query) => $query->whereKeyNot($hero->getKey()))
            ->newest()
            ->limit(8)
            ->get();

        $popularVideos = (clone $base)
            ->when($hero, fn (Builder $query) => $query->whereKeyNot($hero->getKey()))
            ->trending()
            ->limit(6)
            ->get();

        $categories = Category::query()
            ->visible()
            ->withCount(['videos as published_videos_count' => fn (Builder $query) => $query->published()])
            ->ordered()
            ->get();

        $models = ModelProfile::query()
            ->visible()
            ->withCount(['videos as published_videos_count' => fn (Builder $query) => $query->published()])
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $collections = FashionCollection::query()
            ->visible()
            ->with('brand')
            ->withCount(['videos as published_videos_count' => fn (Builder $query) => $query->published()])
            ->orderByDesc('event_date')
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        return view('video.index', [
            'hero' => $hero,
            'newVideos' => $newVideos,
            'popularVideos' => $popularVideos,
            'categories' => $categories,
            'models' => $models,
            'collections' => $collections,
            'stats' => $this->stats(),
        ]);
    }

    public function browse(Request $request): View
    {
        $videos = $this->filteredQuery($request)
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return view('video.browse', [
            'videos' => $videos,
            'categories' => $this->categoriesForFilters(),
            'brands' => Brand::query()->visible()->orderBy('name')->get(),
            'models' => ModelProfile::query()->visible()->orderBy('name')->get(),
            'filters' => $this->filters($request),
            'activeCategory' => null,
            'pageTitle' => $this->searchTitle($request),
            'stats' => $this->stats(),
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $filters = $this->filters($request);

        $query = Video::query()
            ->published()
            ->with(['categoryInfo', 'brand', 'collection', 'models', 'tags'])
            ->withCount(['comments as comments_count' => fn (Builder $query) => $query->approved()]);

        if ($filters['q'] !== '') {
            $term = '%'.$filters['q'].'%';

            $query->where(function (Builder $query) use ($term): void {
                $query->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('credits', 'like', $term)
                    ->orWhereHas('models', fn (Builder $query) => $query->where('name', 'like', $term))
                    ->orWhereHas('brand', fn (Builder $query) => $query->where('name', 'like', $term))
                    ->orWhereHas('tags', fn (Builder $query) => $query->where('name', 'like', $term));
            });
        }

        if ($filters['category'] !== '') {
            $query->where(function (Builder $query) use ($filters): void {
                $query->whereHas('categoryInfo', fn (Builder $query) => $query->where('slug', $filters['category']))
                    ->orWhere('category', $filters['category']);
            });
        }

        if ($filters['brand'] !== '') {
            $query->whereHas('brand', fn (Builder $query) => $query->where('slug', $filters['brand']));
        }

        if ($filters['model'] !== '') {
            $query->whereHas('models', fn (Builder $query) => $query->where('slug', $filters['model']));
        }

        if ($filters['collection'] !== '') {
            $query->whereHas('collection', fn (Builder $query) => $query->where('slug', $filters['collection']));
        }

        if ($filters['tag'] !== '') {
            $query->whereHas('tags', fn (Builder $query) => $query->where('slug', $filters['tag']));
        }

        if ($filters['season'] !== '') {
            $query->whereHas('collection', fn (Builder $query) => $query->where('season', $filters['season']));
        }

        return match ($filters['sort']) {
            'popular' => $query->ranked(),
            'views' => $query->orderByDesc('views')->orderByDesc('id'),
            'oldest' => $query->orderBy('published_at')->orderBy('id'),
            default => $query->newest(),
        };
    }

    /** @return array{q:string,category:string,brand:string,model:string,collection:string,tag:string,season:string,sort:string} */
    private function filters(Request $request): array
    {
        $sort = (string) $request->query('sort', 'new');

        if (! in_array($sort, ['new', 'popular', 'views', 'oldest'], true)) {
            $sort = 'new';
        }

        return [
            'q' => trim(mb_substr((string) $request->query('q', ''), 0, 120)),
            'category' => trim((string) $request->query('category', '')),
            'brand' => trim((string) $request->query('brand', '')),
            'model' => trim((string) $request->query('model', '')),
            'collection' => trim((string) $request->query('collection', '')),
            'tag' => trim((string) $request->query('tag', '')),
            'season' => trim((string) $request->query('season', '')),
            'sort' => $sort,
        ];
    }

    private function searchTitle(Request $request): string
    {
        $term = trim((string) $request->query('q', ''));

        if ($term !== '') {
            return 'Поиск: '.$term;
        }

        return match ((string) $request->query('sort', 'new')) {
            'popular' => 'Популярное',
            'views' => 'Больше просмотров',
            'oldest' => 'Архив',
            default => 'Все показы',
        };
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_visible, 404);

        $request->merge(['category' => $category->slug]);

        $videos = $this->filteredQuery($request)
            ->paginate($this->resolvePerPage($request))
            ->withQueryString();

        return view('video.browse', [
            'videos' => $videos,
            'categories' => $this->categoriesForFilters(),
            'brands' => Brand::query()->visible()->orderBy('name')->get(),
            'models' => ModelProfile::query()->visible()->orderBy('name')->get(),
            'filters' => $this->filters($request),
            'activeCategory' => $category,
            'pageTitle' => $category->name,
            'stats' => $this->stats(),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $video = Video::query()
            ->published()
            ->with(['categoryInfo', 'brand', 'collection', 'models', 'tags'])
            ->withCount(['comments as comments_count' => fn (Builder $query) => $query->approved()])
            ->where('slug', $slug)
            ->firstOrFail();

        $this->recordView($request, $video);

        $comments = $video->comments()
            ->approved()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $related = Video::query()
            ->published()
            ->with(['categoryInfo', 'brand', 'models'])
            ->withCount(['comments as comments_count' => fn (Builder $query) => $query->approved()])
            ->whereKeyNot($video->getKey())
            ->where(function (Builder $query) use ($video): void {
                if ($video->category_id) {
                    $query->where('category_id', $video->category_id);
                }

                if ($video->models->isNotEmpty()) {
                    $query->orWhereHas('models', fn (Builder $query) => $query
                        ->whereIn('models.id', $video->models->modelKeys()));
                }
            })
            ->ranked()
            ->limit(6)
            ->get();

        $stockQuery = Video::query()->published();

        $previous = (clone $stockQuery)
            ->when($video->published_at, fn (Builder $query) => $query->where('published_at', '<', $video->published_at))
            ->newest()
            ->first();

        $next = (clone $stockQuery)
            ->when($video->published_at, fn (Builder $query) => $query->where('published_at', '>', $video->published_at))
            ->oldest()
            ->first();

        return view('video.show', [
            'video' => $video,
            'comments' => $comments,
            'related' => $related,
            'previous' => $previous,
            'next' => $next,
            'stats' => $this->stats(),
            'isFavorite' => in_array($video->getKey(), $request->session()->get('favorite_videos', []), true),
            'isLiked' => in_array($video->getKey(), $request->session()->get('liked_videos', []), true),
        ]);
    }

    public function storeComment(Request $request, string $slug)
    {
        $request->validate([
            'content' => 'required|string|min:3|max:1000',
            'username' => 'nullable|string|max:50',
        ]);

        // strip_tags может превратить «<b></b>» в пустую строку, поэтому
        // минимальную длину проверяем уже после очистки разметки.
        $content = trim(strip_tags((string) $request->input('content')));

        if (mb_strlen($content) < 3) {
            return back()
                ->withErrors(['content' => 'Комментарий должен содержать хотя бы 3 символа'])
                ->withInput();
        }

        $username = trim(strip_tags((string) $request->input('username')));
        $username = $username !== '' ? mb_substr($username, 0, 50) : 'Аноним';

        $video = Video::query()->published()->where('slug', $slug)->firstOrFail();

        // Защита от спама: не более одного комментария в минуту и трёх в час
        // с одного IP (второе ограничение совпадает с подписью в форме).
        $recentComments = Comment::query()
            ->where('ip_address', $request->ip())
            ->where('created_at', '>=', now()->subHour())
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        if ($recentComments->count() >= 3) {
            return back()
                ->withErrors(['content' => 'С одного IP можно оставить не более 3 комментариев в час'])
                ->withInput();
        }

        $lastComment = $recentComments->first();

        if ($lastComment && $lastComment->created_at->diffInMinutes(now()) < 1) {
            return back()
                ->withErrors(['content' => 'Пожалуйста, подождите минуту перед отправкой следующего комментария'])
                ->withInput();
        }

        $status = config('site.comments_require_moderation', false) ? 'pending' : 'approved';

        $video->comments()->create([
            'username' => $username,
            'content' => $content,
            // ip_address/user_agent в БД NOT NULL, а заголовок может отсутствовать
            // или быть длиннее 255 символов (заголовок не ограничен валидацией).
            'ip_address' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
            'status' => $status,
        ]);

        if ($status === 'approved') {
            $video->registerCommentAdded();
        }

        // В футере показывается кешированный COUNT(*) комментариев — сбрасываем,
        // чтобы новый комментарий был виден сразу после редиректа.
        Cache::forget('video_footer_stats');

        return back()->with(
            'success',
            $status === 'approved'
                ? 'Комментарий добавлен!'
                : 'Комментарий отправлен на модерацию.'
        );
    }

    public function fullscreen(string $slug)
    {
        $video = Video::query()->published()->where('slug', $slug)->firstOrFail();

        return view('video.fullscreen', compact('video'));
    }

    public function like(Request $request, string $slug): JsonResponse
    {
        $video = Video::query()->published()->where('slug', $slug)->firstOrFail();

        $likedVideos = $request->session()->get('liked_videos', []);

        if (in_array($video->getKey(), $likedVideos, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Вы уже лайкнули это видео',
            ], 409);
        }

        $video->registerLike();

        $likedVideos[] = $video->getKey();
        $request->session()->put('liked_videos', array_values(array_unique($likedVideos)));

        return response()->json([
            'success' => true,
            'likes' => $video->likes,
            'rating' => $video->rating,
        ]);
    }

    public function favorite(Request $request, string $slug): JsonResponse
    {
        $video = Video::query()->published()->where('slug', $slug)->firstOrFail();
        $favorites = $request->session()->get('favorite_videos', []);

        $isFavorite = in_array($video->getKey(), $favorites, true);

        if ($isFavorite) {
            $favorites = array_values(array_diff($favorites, [$video->getKey()]));
        } else {
            $favorites[] = $video->getKey();
        }

        $favorites = array_values(array_unique($favorites));
        $request->session()->put('favorite_videos', $favorites);

        return response()->json([
            'success' => true,
            'favorite' => ! $isFavorite,
            'count' => count($favorites),
        ]);
    }

    public function favorites(Request $request): View
    {
        $ids = $request->session()->get('favorite_videos', []);

        $videos = Video::query()
            ->published()
            ->with(['categoryInfo', 'brand', 'models'])
            ->withCount(['comments as comments_count' => fn (Builder $query) => $query->approved()])
            ->whereIn('id', $ids)
            ->newest()
            ->get();

        return view('video.favorites', [
            'videos' => $videos,
            'stats' => $this->stats(),
        ]);
    }

    public function updateAllRatings(): JsonResponse
    {
        $count = Video::refreshAllRatings();

        return response()->json(['message' => 'Рейтинги обновлены', 'count' => $count]);
    }

    /**
     * Кешируемая SVG-заглушка превью. Раньше заглушка отдавалась прямо в HTML как
     * data-URI, генерировалась с rand() на каждый рендер (HTML не кешировался и
     * «мигал»), теперь это отдельный ответ с долгим Cache-Control.
     */
    public function thumbnailPlaceholder(string $seed)
    {
        return response(VideoThumbnailService::placeholderSvg($seed), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function resolvePerPage(Request $request): int
    {
        $perPage = filter_var($request->query('per_page', self::DEFAULT_PER_PAGE), FILTER_VALIDATE_INT) ?: self::DEFAULT_PER_PAGE;

        return max(6, min($perPage, self::MAX_PER_PAGE));
    }

    private function categoriesForFilters()
    {
        return Category::query()
            ->visible()
            ->withCount(['videos as published_videos_count' => fn (Builder $query) => $query->published()])
            ->ordered()
            ->get();
    }

    /** @return array{videos:int,views:int,comments:int,categories:int,models:int} */
    private function stats(): array
    {
        return Cache::remember('video_footer_stats', now()->addMinutes(5), function (): array {
            $videos = Video::query()
                ->published()
                ->selectRaw('COUNT(*) as videos_count, COALESCE(SUM(views), 0) as views_sum')
                ->first();

            return [
                'videos' => (int) ($videos->videos_count ?? 0),
                'views' => (int) ($videos->views_sum ?? 0),
                'comments' => Comment::query()->approved()->count(),
                'categories' => Category::query()->visible()->count(),
                'models' => ModelProfile::query()->visible()->count(),
            ];
        });
    }

    /**
     * Просмотр учитывается один раз на сессию/устройство за 6 часов.
     * Раньше счётчик рос при каждом обновлении страницы и у ботов.
     */
    private function recordView(Request $request, Video $video): void
    {
        $sessionKey = 'viewed_video_'.$video->getKey();

        if ($request->session()->has($sessionKey)) {
            return;
        }

        $sessionId = mb_substr($request->session()->getId(), 0, 80);
        $ipHash = hash('sha256', (string) $request->ip().'|'.(string) config('app.key'));
        $since = now()->subHours(6);

        $alreadyCounted = ViewEvent::query()
            ->where('video_id', $video->getKey())
            ->where('created_at', '>=', $since)
            ->where(function (Builder $query) use ($sessionId, $ipHash): void {
                $query->where('session_id', $sessionId)->orWhere('ip_hash', $ipHash);
            })
            ->exists();

        $request->session()->put($sessionKey, true);

        if ($alreadyCounted) {
            return;
        }

        ViewEvent::query()->create([
            'video_id' => $video->getKey(),
            'session_id' => $sessionId,
            'ip_hash' => $ipHash,
        ]);

        $video->registerView();
    }
}

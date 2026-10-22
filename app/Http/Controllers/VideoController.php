<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Video;
use App\Services\VideoThumbnailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class VideoController extends Controller
{
    /** Максимум видео на страницу (защита от per_page=1000000). */
    private const MAX_PER_PAGE = 100;

    /** Размер «этажа» пирамиды на главной. */
    private const FLOOR_SIZE = 5;

    public function index(Request $request)
    {
        $perPage = $this->resolvePerPage($request);
        $page = max(1, (int) $request->get('page', 1));
        $selectedCategory = (string) $request->get('category', 'all');

        $categories = $this->categories();

        if ($selectedCategory !== 'all' && ! in_array($selectedCategory, $categories, true)) {
            $selectedCategory = 'all';
        }

        // Получаем видео с подсчетом комментариев и сортируем по рейтингу (просмотры + комментарии)
        $videosQuery = Video::withCount('comments');

        if ($selectedCategory !== 'all') {
            $videosQuery->where('category', $selectedCategory);
        }

        $videos = $videosQuery
            ->ranked()
            ->paginate($perPage, ['*'], 'page', $page);

        $videos->appends($request->query());

        // Группируем видео по "этажам" для веб-версии
        $groupedVideos = $this->groupVideosForWeb($videos->items());

        return view('video.index', compact('videos', 'groupedVideos', 'categories', 'selectedCategory'));
    }

    /**
     * Список категорий (папки импорта + значения из БД) кешируется: раньше на
     * каждый запрос выполнялись скан файловой системы и DISTINCT-запрос.
     */
    private function categories(): array
    {
        return Cache::remember('video_categories', now()->addMinutes(10), function (): array {
            $fromFolders = collect();

            $importDir = public_path('images/import');
            if (File::isDirectory($importDir)) {
                $fromFolders = collect(File::directories($importDir))
                    ->map(static fn (string $path): string => basename($path));
            }

            $fromDb = Video::query()
                ->select('category')
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->distinct()
                ->pluck('category');

            return $fromFolders
                ->merge($fromDb)
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        });
    }

    /** per_page из строки запроса ограничиваем разумным максимумом. */
    private function resolvePerPage(Request $request): int
    {
        // (int) 'abc' === 0, из-за чего ?per_page=abc отдавал 5 видео вместо дефолтных 50.
        $perPage = filter_var($request->get('per_page', 50), FILTER_VALIDATE_INT) ?: 50;

        return max(self::FLOOR_SIZE, min($perPage, self::MAX_PER_PAGE));
    }

    private function groupVideosForWeb($videos)
    {
        $groups = [];
        $index = 0;
        $floorSizes = [1, 2, 3, 4]; // первые 4 этажа
        $total = count($videos);

        // Первые 4 этажа (1, 2, 3, 4 видео)
        foreach ($floorSizes as $size) {
            if ($index >= $total) {
                break;
            }
            $groups[] = array_slice($videos, $index, $size);
            $index += $size;
        }

        // Остальные этажи по 5 видео
        while ($index < $total) {
            $groups[] = array_slice($videos, $index, self::FLOOR_SIZE);
            $index += self::FLOOR_SIZE;
        }

        return $groups;
    }

    public function show($slug)
    {
        $video = Video::where('slug', $slug)
            ->withCount('comments')
            ->firstOrFail();

        // +1 просмотр и пересчёт рейтинга одной UPDATE-командой (было: increment + save)
        $video->registerView();

        $comments = $video->comments()->latest()->get();

        // Общая статистика футера кешируется: раньше на каждый показ видео
        // выполнялись COUNT(*) videos, SUM(views) и COUNT(*) comments.
        $stats = Cache::remember('video_footer_stats', now()->addMinutes(5), function (): array {
            // COUNT и SUM по videos отдаём одним запросом, а не двумя.
            $videos = Video::query()
                ->selectRaw('COUNT(*) as videos_count, COALESCE(SUM(views), 0) as views_sum')
                ->first();

            return [
                'videos' => (int) ($videos->videos_count ?? 0),
                'views' => (int) ($videos->views_sum ?? 0),
                'comments' => Comment::count(),
            ];
        });

        return view('video.show', compact('video', 'comments', 'stats'));
    }

    public function storeComment(Request $request, $slug)
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

        $video = Video::where('slug', $slug)->withCount('comments')->firstOrFail();

        // Защита от спама: не более одного комментария в минуту и трёх в час
        // с одного IP (второе ограничение совпадает с подписью в форме).
        $recentComments = Comment::where('ip_address', $request->ip())
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

        $video->comments()->create([
            'username' => $username,
            'content' => $content,
            // ip_address/user_agent в БД NOT NULL, а заголовок может отсутствовать
            // или быть длиннее 255 символов (заголовок не ограничен валидацией).
            'ip_address' => (string) $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        // Рейтинг обновляем одной командой UPDATE (без refresh() и повторного COUNT)
        $video->registerCommentAdded();

        // В футере показывается кешированный COUNT(*) комментариев — сбрасываем,
        // чтобы новый комментарий был виден сразу после редиректа.
        Cache::forget('video_footer_stats');

        return back()->with('success', 'Комментарий добавлен!');
    }

    public function fullscreen($slug)
    {
        $video = Video::where('slug', $slug)->firstOrFail();

        return view('video.fullscreen', compact('video'));
    }

    // Метод для обновления рейтингов всех видео (можно запускать по расписанию)
    public function updateAllRatings()
    {
        // Один SQL-запрос вместо выборки всех видео + save() в цикле
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

    public function like(Request $request, $slug)
    {
        $video = Video::where('slug', $slug)->withCount('comments')->firstOrFail();

        $likedVideos = $request->session()->get('liked_videos', []);

        if (in_array($video->id, $likedVideos, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Вы уже лайкнули это видео',
            ], 409);
        }

        // +1 лайк и пересчёт рейтинга одной SQL-командой UPDATE
        // (было: increment + refresh + COUNT(*) + save)
        $video->registerLike();

        $likedVideos[] = $video->id;
        $request->session()->put('liked_videos', array_unique($likedVideos));

        return response()->json([
            'success' => true,
            'likes' => $video->likes,
            'rating' => $video->rating,
        ]);
    }
}

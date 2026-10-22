<?php

namespace App\Models;

use App\Services\VideoThumbnailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class Video extends Model
{
    protected $fillable = [
        'title', 'slug', 'description', 'filename', 'thumbnail',
        'duration', 'format', 'quality', 'views', 'likes', 'rating', 'featured', 'category',
        'category_id', 'brand_id', 'collection_id', 'status', 'published_at', 'credits',
        'width', 'height', 'preview', 'thumbnail_time',
    ];

    protected $casts = [
        'featured' => 'boolean',
        'published_at' => 'datetime',
        'width' => 'integer',
        'height' => 'integer',
        'thumbnail_time' => 'integer',
        'views' => 'integer',
        'likes' => 'integer',
        'rating' => 'integer',
    ];

    protected $appends = [
        'comments_count', 'thumbnail_url', 'formatted_duration', 'total_rating',
        'video_url', 'has_video', 'preview_url', 'aspect_ratio', 'orientation',
    ];

    /**
     * Мемоизация количества комментариев на время жизни инстанса модели.
     * Без этого аксессор comments_count выполнял бы по одному COUNT(*) на каждое
     * обращение (и по одному на каждую видеокарточку в списке).
     */
    private ?int $commentsCountCache = null;

    /** Мемоизация проверок существования видеофайла (одна на файл за запрос). */
    private static array $fileExistsCache = [];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Отношение называется categoryInfo, потому что колонка category уже
     * содержит строковый slug для обратной совместимости с импортом.
     */
    public function categoryInfo(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(FashionCollection::class, 'collection_id');
    }

    public function models(): BelongsToMany
    {
        return $this->belongsToMany(ModelProfile::class, 'model_video', 'video_id', 'model_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'tag_video');
    }

    public function viewEvents(): HasMany
    {
        return $this->hasMany(ViewEvent::class);
    }

    /** После refresh() закешированный счётчик комментариев должен быть сброшен. */
    public function refresh()
    {
        $this->commentsCountCache = null;

        return parent::refresh();
    }

    /**
     * Количество комментариев: сначала берём значение, загруженное через
     * withCount('comments') / loadCount('comments'), и только если его нет —
     * идём в БД (один раз, дальше значение кешируется).
     */
    public function commentsCount(): int
    {
        if ($this->commentsCountCache !== null) {
            return $this->commentsCountCache;
        }

        if (array_key_exists('comments_count', $this->attributes)) {
            return $this->commentsCountCache = (int) $this->attributes['comments_count'];
        }

        return $this->commentsCountCache = $this->comments()->count();
    }

    public function getCommentsCountAttribute(): int
    {
        return $this->commentsCount();
    }

    public function getTotalRatingAttribute(): int
    {
        return (int) $this->views + $this->commentsCount() + (int) $this->likes;
    }

    public function getThumbnailUrlAttribute(): string
    {
        return VideoThumbnailService::getThumbnailUrl($this->thumbnail, $this->getKey());
    }

    public function getVideoUrlAttribute(): string
    {
        // Проверяем существует ли файл (результат мемоизируется на время запроса).
        // Второй путь — публичные демо/архивные ролики, которые попадают в git,
        // тогда как storage/app/public в репозиторий не коммитится.
        $filename = (string) $this->filename;

        $path = self::$fileExistsCache[$filename] ??= match (true) {
            is_file(storage_path('app/public/videos/'.$filename)) => 'storage/videos/'.$filename,
            is_file(public_path('media/videos/'.$filename)) => 'media/videos/'.$filename,
            default => '',
        };

        return $path === '' ? '' : asset($path);
    }

    public function getHasVideoAttribute(): bool
    {
        return $this->video_url !== '';
    }

    public function getPreviewUrlAttribute(): string
    {
        if ($this->preview) {
            $path = ltrim((string) $this->preview, '/');

            if (is_file(storage_path('app/public/'.$path))) {
                return asset('storage/'.$path);
            }

            if (is_file(public_path($path))) {
                return asset($path);
            }

            if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }
        }

        // Превью генерируются командой videos:generate-previews и называются
        // так же, как исходный ролик. Это позволяет переиспользовать один
        // превью-файл для нескольких записей с общим filename.
        $filename = (string) $this->filename;

        foreach (['storage/previews/'.$filename, 'media/previews/'.$filename] as $candidate) {
            if (is_file(public_path($candidate))) {
                return asset($candidate);
            }
        }

        $storagePath = storage_path('app/public/previews/'.$filename);

        return is_file($storagePath) ? asset('storage/previews/'.$filename) : '';
    }

    public function getAspectRatioAttribute(): string
    {
        $width = (int) $this->width;
        $height = (int) $this->height;

        if ($width <= 0 || $height <= 0) {
            return '16 / 9';
        }

        $gcd = static function (int $a, int $b) use (&$gcd): int {
            return $b === 0 ? $a : $gcd($b, $a % $b);
        };

        $divisor = max(1, $gcd($width, $height));

        return intdiv($width, $divisor).' / '.intdiv($height, $divisor);
    }

    public function getOrientationAttribute(): string
    {
        $width = (int) $this->width;
        $height = (int) $this->height;

        if ($width <= 0 || $height <= 0) {
            return 'landscape';
        }

        if ($height > $width * 1.1) {
            return 'portrait';
        }

        return abs($width - $height) < max($width, $height) * 0.1 ? 'square' : 'landscape';
    }

    public function getPublishedLabelAttribute(): string
    {
        return $this->published_at?->translatedFormat('j F Y') ?? '';
    }

    public function getIsPublishedAttribute(): bool
    {
        return $this->status === 'published'
            && ($this->published_at === null || $this->published_at->isPast());
    }

    /**
     * MIME-тип для <source type="...">. Раньше во вьюхах был жёстко зашит video/mp4:
     * для webm/mkv/mov браузер считал источник непроигрываемым и не запускал видео.
     * Считаем по расширению реального файла: колонку format заполняет в том числе
     * VideoSeeder — случайными значениями, которым доверять нельзя.
     */
    public function getMimeTypeAttribute(): string
    {
        $extension = strtolower(pathinfo((string) $this->filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'webm' => 'video/webm',
            'ogg', 'ogv' => 'video/ogg',
            'mkv' => 'video/x-matroska',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            default => 'video/mp4',
        };
    }

    public function getFormattedDurationAttribute(): string
    {
        $duration = max(0, (int) $this->duration);
        $minutes = intdiv($duration, 60);
        $seconds = $duration % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }

    /**
     * Сортировка для главной страницы. Использует индексированную колонку rating
     * вместо ORDER BY (views + comments_count + likes), который заставлял SQLite/
     * MySQL сортировать всю таблицу без индекса.
     */
    public function scopePublished(Builder $query): Builder
    {
        $model = $query->getModel();

        return $query->where($model->qualifyColumn('status'), 'published')
            ->where(function (Builder $query) use ($model): void {
                $query->whereNull($model->qualifyColumn('published_at'))
                    ->orWhere($model->qualifyColumn('published_at'), '<=', now());
            });
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function scopeOldest(Builder $query): Builder
    {
        return $query->orderBy('published_at')->orderBy('id');
    }

    public function scopeTrending(Builder $query): Builder
    {
        return $query
            ->withCount(['viewEvents as recent_views_count' => function (Builder $query): void {
                $query->where('created_at', '>=', now()->subDays(7));
            }])
            ->orderByDesc('recent_views_count')
            ->orderByDesc('rating')
            ->orderByDesc('views');
    }

    public function scopeRanked(Builder $query): Builder
    {
        return $query->orderByDesc('rating')
            ->orderByDesc('views')
            ->orderByDesc('id');
    }

    /**
     * Видео без настоящего превью из кадра: значение ещё не задано либо это
     * сгенерированная ранее заглушка. Используется командой генерации превью.
     */
    public function scopeWithoutRealThumbnail(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('thumbnail')
                ->orWhere('thumbnail', '')
                ->orWhere('thumbnail', 'like', '%color_thumb_%')
                ->orWhere('thumbnail', 'like', '%simple_thumb_%');
        });
    }

    /**
     * Пересчёт и запись рейтинга в БД (views + likes + комментарии).
     * Использует уже загруженное значение comments_count, если оно есть.
     */
    public function updateRating(): void
    {
        $this->attributes['rating'] = (int) $this->views + $this->commentsCount() + (int) $this->likes;

        $this->atomicUpdate(['rating' => $this->ratingExpression()]);

        $this->syncOriginalAttribute('rating');
    }

    /** +1 просмотр и обновление рейтинга одной SQL-командой UPDATE. */
    public function registerView(): void
    {
        $this->attributes['views'] = (int) $this->views + 1;
        $this->attributes['rating'] = (int) $this->views + $this->commentsCount() + (int) $this->likes;

        // views увеличиваем относительно значения в БД, а не абсолютным числом:
        // иначе два одновременных просмотра затирали бы друг друга.
        // rating ставим ПЕРВЫМ: MySQL применяет SET слева направо, и если сначала
        // увеличить views, ratingExpression(1) увидит уже новое значение (+2).
        $this->atomicUpdate([
            'rating' => $this->ratingExpression(1),
            'views' => DB::raw('views + 1'),
        ]);

        $this->syncOriginalAttributes(['views', 'rating']);
    }

    /** +1 комментарий и обновление рейтинга одной SQL-командой UPDATE. */
    public function registerCommentAdded(): void
    {
        // Если счётчик уже известен (withCount/предыдущий вызов) — просто +1.
        // Иначе считаем после вставки: COUNT(*) уже включает новый комментарий,
        // и прибавлять ещё единицу было бы ошибкой.
        $comments = $this->commentsCountCache !== null
            ? $this->commentsCountCache + 1
            : (array_key_exists('comments_count', $this->attributes)
                ? (int) $this->attributes['comments_count'] + 1
                : $this->comments()->count());

        $this->commentsCountCache = $comments;
        $this->attributes['rating'] = (int) $this->views + $comments + (int) $this->likes;

        // rating считается подзапросом по comments, поэтому новый комментарий
        // учитывается даже при параллельной вставке с другого соединения.
        $this->atomicUpdate(['rating' => $this->ratingExpression()]);

        $this->syncOriginalAttribute('rating');
    }

    /** +1 лайк и обновление рейтинга одной SQL-командой UPDATE. */
    public function registerLike(): void
    {
        $this->attributes['likes'] = (int) $this->likes + 1;
        $this->attributes['rating'] = (int) $this->views + $this->commentsCount() + (int) $this->likes;

        // rating — первым, чтобы MySQL не увидел уже увеличенный likes (+2).
        $this->atomicUpdate([
            'rating' => $this->ratingExpression(0, 1),
            'likes' => DB::raw('likes + 1'),
        ]);

        $this->syncOriginalAttributes(['likes', 'rating']);
    }

    /**
     * Пересчёт rating всех видео одним SQL-запросом.
     * Было: выборка всех моделей + save() в цикле (1 UPDATE на видео).
     */
    public static function refreshAllRatings(): int
    {
        $model = new static;

        DB::table($model->getTable())->update([
            'rating' => $model->ratingExpression(),
            'updated_at' => now(),
        ]);

        return (int) DB::table($model->getTable())->count();
    }

    /**
     * SQL-выражение рейтинга: текущие views + likes + число комментариев.
     * Подзапрос не требует N+1 и всегда видит актуальные данные.
     */
    private function ratingExpression(int $viewsIncrement = 0, int $likesIncrement = 0): Expression
    {
        $table = $this->getTable();

        return DB::raw(sprintf(
            "views + %d + likes + %d + (select count(*) from comments where comments.video_id = %s.id and comments.status = 'approved')",
            $viewsIncrement,
            $likesIncrement,
            $table
        ));
    }

    private function atomicUpdate(array $values): void
    {
        $timestamp = $this->freshTimestamp();
        $values['updated_at'] = $timestamp;

        static::query()->whereKey($this->getKey())->update($values);

        $this->attributes['updated_at'] = $timestamp;
        $this->syncOriginalAttribute('updated_at');
    }
}

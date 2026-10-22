<?php

namespace App\Models;

use App\Services\VideoThumbnailService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class Video extends Model
{
    protected $fillable = [
        'title', 'slug', 'description', 'filename', 'thumbnail',
        'duration', 'format', 'quality', 'views', 'likes', 'rating', 'featured', 'category',
    ];

    protected $appends = ['comments_count', 'thumbnail_url', 'formatted_duration', 'total_rating'];

    /**
     * Мемоизация количества комментариев на время жизни инстанса модели.
     * Без этого аксессор comments_count выполнял бы по одному COUNT(*) на каждое
     * обращение (и по одному на каждую видеокарточку в списке).
     */
    private ?int $commentsCountCache = null;

    /** Мемоизация проверок существования видеофайла (одна на файл за запрос). */
    private static array $fileExistsCache = [];

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
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
        // Проверяем существует ли файл (результат мемоизируется на время запроса)
        $filename = (string) $this->filename;

        $exists = self::$fileExistsCache[$filename]
            ??= file_exists(storage_path('app/public/videos/'.$filename));

        if (! $exists) {
            // Создаем заглушку если файла нет
            return $this->createDummyVideoUrl();
        }

        return asset('storage/videos/'.$filename);
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

    private function createDummyVideoUrl(): string
    {
        // Возвращаем тестовое видео
        return 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4';
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
            'views + %d + likes + %d + (select count(*) from comments where comments.video_id = %s.id)',
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

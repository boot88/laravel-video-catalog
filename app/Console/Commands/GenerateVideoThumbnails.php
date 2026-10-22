<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\VideoThumbnailService;
use Illuminate\Console\Command;

class GenerateVideoThumbnails extends Command
{
    /**
     * Один консольный вход вместо четырёх дублирующих команд
     * (video:generate-thumbnails / video:thumbnails / video:regenerate-thumbnails /
     * app:generate-thumbnails) — старые имена сохранены как алиасы.
     */
    protected $signature = 'videos:generate-thumbnails
                            {--all : Обработать все видео (по умолчанию — только без реального превью)}
                            {--force : Перегенерировать превью даже там, где оно уже есть}
                            {--id=* : Обработать только указанные ID видео}
                            {--second= : С какой секунды брать кадр (по умолчанию из config/ffmpeg.php)}
                            {--skip=0 : Пропустить первые N видео}
                            {--limit=0 : Обработать не более N видео}
                            {--test : Тестовый режим — только 2 видео}';

    protected $aliases = [
        'video:generate-thumbnails',
        'video:thumbnails',
        'video:regenerate-thumbnails',
        'app:generate-thumbnails',
    ];

    protected $description = 'Генерация превью видео (ffmpeg, быстрый seek) с фолбэком на цветную заглушку';

    public function handle(): int
    {
        if (VideoThumbnailService::ffmpegBinary() === null) {
            $this->warn('ffmpeg не найден: задайте FFMPEG_BINARIES в .env или установите ffmpeg в PATH. Будут созданы цветные заглушки.');
        }

        $query = Video::query()->select(['id', 'title', 'filename', 'thumbnail']);

        $ids = array_filter((array) $this->option('id'));

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif (! $this->option('all') && ! $this->option('force')) {
            // Только видео без «настоящего» превью (заглушки перегенерируем).
            // Важно: в БД путь хранится как thumbnails/color_thumb_*, поэтому
            // шаблон должен искать подстроку, а не начинаться с color_thumb.
            $query->withoutRealThumbnail();
        }

        if ((int) $this->option('skip') > 0) {
            $query->skip((int) $this->option('skip'));
        }

        $limit = (int) $this->option('limit');
        if ($this->option('test')) {
            $limit = 2;
        }
        if ($limit > 0) {
            $query->take($limit);
        }

        $videos = $query->get();

        if ($videos->isEmpty()) {
            $this->info('Нет видео для обработки.');

            return self::SUCCESS;
        }

        $second = $this->option('second') !== null
            ? (int) $this->option('second')
            : null;

        $this->info("Видео к обработке: {$videos->count()} (секунда кадра: thumbnail_time видео или конфиг)");

        $bar = $this->output->createProgressBar($videos->count());
        $bar->start();

        $fromVideo = 0;
        $fromFallback = 0;
        $errors = 0;

        foreach ($videos as $video) {
            if ($this->option('force') && $video->thumbnail) {
                $oldPath = storage_path('app/public/'.$video->thumbnail);
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
                $video->thumbnail = null;
            }

            if (VideoThumbnailService::generateThumbnail($video, $second)) {
                if (str_starts_with((string) $video->thumbnail, 'thumbnails/video_thumb_')) {
                    $fromVideo++;
                } else {
                    $fromFallback++;
                }

                $this->line("  ✔ {$video->title} → {$video->thumbnail}");
            } else {
                $errors++;
                $this->error("  ✗ {$video->title}: превью не создано");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Готово.');
        $this->line("  Из кадра видео: {$fromVideo}");
        $this->line("  Заглушки (файл не найден): {$fromFallback}");
        $this->line("  Ошибок: {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}

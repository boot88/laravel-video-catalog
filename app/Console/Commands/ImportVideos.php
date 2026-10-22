<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\VideoProbeService;
use App\Services\VideoThumbnailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Добавление видео в базу из папки.
 *
 * Раньше в проекте не было НИ ОДНОГО способа добавить видео, кроме сидера
 * (VideoSeeder), который сначала делает TRUNCATE таблицы и создаёт 160 записей
 * с выдуманными именами файлов. Из-за этого файлы, положенные в папку
 * public/images/import/<категория>, попадали только в список категорий, но
 * видео не появлялись. Теперь есть команда: файл из папки → запись в БД.
 */
class ImportVideos extends Command
{
    private const EXTENSIONS = ['mp4', 'm4v', 'mov', 'webm', 'mkv', 'avi', 'flv'];

    protected $signature = 'videos:import
                            {folder? : Папка с видео: имя внутри public/images/import (например blondinki) или любой путь. Без аргумента — все папки import}
                            {--category= : Категория (по умолчанию — имя папки)}
                            {--limit=0 : Обработать не более N файлов}
                            {--dry-run : Показать план, ничего не записывая}
                            {--force : Перезаписать уже добавленные (совпадение по имени файла)}
                            {--copy : Копировать файлы, оставляя оригиналы (по умолчанию файлы переносятся в storage)}
                            {--views=0 : Стартовые просмотры (влияют на позицию на главной: рейтинг = views + likes + комментарии)}
                            {--skip-thumbnails : Не создавать превью (потом: php artisan videos:generate-thumbnails)}';

    protected $description = 'Импорт видео из папки в базу: положил файлы в public/images/import/<категория> и запустил команду';

    public function handle(): int
    {
        $folders = $this->resolveFolders();

        if ($folders === []) {
            $arg = trim((string) $this->argument('folder'));
            $expected = $arg === '' ? public_path('images/import') : public_path('images/import/'.$arg);

            $this->error('Папка не найдена: '.$expected);
            $this->line('Создайте её и положите туда видеофайлы, например:');
            $this->line('  mkdir -p '.$expected.' && cp /путь/к/видео.mp4 '.$expected.'/');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $copy = (bool) $this->option('copy');
        $limit = max(0, (int) $this->option('limit'));
        $views = max(0, (int) $this->option('views'));
        $withThumbnails = ! (bool) $this->option('skip-thumbnails');

        $probeAvailable = VideoProbeService::ffprobeBinary() !== null;

        if (! $probeAvailable) {
            $this->warn('ffprobe не найден (FFPROBE_BINARIES / PATH): длительность и качество будут нулевыми/по умолчанию.');
        }

        if ($dryRun) {
            $this->warn('Режим --dry-run: файлы не переносятся, в базу ничего не пишется.');
        }

        foreach ($folders as [$path, $category]) {
            $this->line('Источник: '.$path.'  → категория «'.$category.'»');
        }

        $added = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $reachedLimit = false;

        foreach ($folders as [$path, $category]) {
            $files = $this->videoFiles($path);

            if ($files === []) {
                $this->line('В папке '.basename($path).' нет видеофайлов ('.implode(', ', self::EXTENSIONS).').');

                continue;
            }

            $this->newLine();
            $this->info('Папка '.basename($path).': файлов '.count($files).', категория «'.$category.'»');

            foreach ($files as $sourcePath) {
                if ($limit > 0 && ($added + $updated) >= $limit) {
                    $reachedLimit = true;
                    break 2;
                }

                $originalName = basename($sourcePath);
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $title = $this->makeTitle($originalName);
                // Имя файла — идентификатор видео в базе. Учитываем категорию:
                // одинаковые имена в разных папках (частый случай при раскладке
                // одного ролика по категориям) не должны «съедать» друг друга.
                $storedFilename = $this->storedFilename($originalName, $category);

                // Проверяем до ffprobe: повторный запуск импорта не должен
                // запускать внешний процесс для каждого уже импортированного файла.
                $existing = Video::query()->where('filename', $storedFilename)->first();

                if ($existing !== null && ! $force) {
                    $this->line('  ↷ '.$originalName.' — уже в базе («'.$existing->title
                        .'», категория «'.$existing->category.'»), пропущено');
                    $skipped++;

                    continue;
                }

                $meta = $probeAvailable ? VideoProbeService::probe($sourcePath) : null;

                if ($probeAvailable && $meta === null) {
                    $this->error('  ✗ '.$originalName.' — не читается как видеофайл (пропущено)');
                    $errors++;

                    continue;
                }

                if ($probeAvailable && $meta['has_video'] === false) {
                    $this->error('  ✗ '.$originalName.' — в файле нет видеопотока (пропущено)');
                    $errors++;

                    continue;
                }

                $duration = (int) ($meta['duration'] ?? 0);
                $quality = VideoProbeService::qualityLabel(
                    (int) ($meta['width'] ?? 0),
                    (int) ($meta['height'] ?? 0)
                );

                if ($dryRun) {
                    $this->line('  + '.$originalName.' → '.$storedFilename
                        .' ['.$quality.', '.gmdate('i:s', $duration).']');
                    $existing === null ? $added++ : $updated++;

                    continue;
                }

                $targetPath = storage_path('app/public/videos/'.$storedFilename);

                if (! $this->placeFile($sourcePath, $targetPath, $copy)) {
                    $this->error('  ✗ '.$originalName.' — не удалось '.($copy ? 'скопировать' : 'перенести').' файл');
                    $errors++;

                    continue;
                }

                $video = $existing ?? new Video;
                $video->filename = $storedFilename;
                $video->title = $title;
                $video->description = 'Видео «'.$title.'» из категории «'.$category.'».';
                $video->duration = $duration;
                $video->format = strtoupper($ext);
                $video->quality = $quality;
                $video->category = $category;

                if (! $video->exists) {
                    $video->slug = $this->uniqueSlug($title);
                    $video->views = $views;
                    $video->likes = 0;
                    $video->rating = $views;
                    $video->featured = false;
                }

                $video->save();

                if ($withThumbnails && (! $video->thumbnail || $force)) {
                    VideoThumbnailService::generateThumbnail($video);
                }

                if ($existing === null) {
                    $added++;
                    $this->line('  ✔ '.$originalName.' → «'.$title.'» ['
                        .$quality.', '.gmdate('i:s', $duration).']');
                } else {
                    $updated++;
                    $this->line('  ↻ '.$originalName.' → «'.$title.'» (обновлено)');
                }

                if (! in_array($ext, ['mp4', 'webm', 'm4v'], true)) {
                    $this->warn('    формат '.strtoupper($ext).' может не воспроизводиться в браузере — лучше mp4');
                }
            }
        }

        // Новые категории и счётчики в футере кешируются в контроллере — сбрасываем.
        Cache::forget('video_categories');
        Cache::forget('video_footer_stats');

        if ($reachedLimit) {
            $this->warn('Достигнут лимит --limit='.$limit.': остальные файлы не обработаны.');
        }

        $this->newLine();
        $this->info('Готово.');
        $this->line('  Добавлено: '.$added);
        $this->line('  Обновлено: '.$updated);
        $this->line('  Пропущено (уже в базе): '.$skipped);
        $this->line('  Ошибок: '.$errors);

        if ($added + $updated > 0 && ! $dryRun) {
            $this->line($withThumbnails
                ? '  Превью созданы из кадров видео (storage/app/public/thumbnails)'
                : '  Превью не создавались: php artisan videos:generate-thumbnails');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Папки-источники. Без аргумента — все подпапки public/images/import
     * (имя папки = категория).
     *
     * @return array<int, array{0:string,1:string}>
     */
    private function resolveFolders(): array
    {
        $arg = trim((string) $this->argument('folder'));
        $categoryOption = trim((string) $this->option('category'));

        if ($arg === '') {
            $folders = [];

            foreach ($this->directories(public_path('images/import')) as $dir) {
                $folders[] = [$dir, $categoryOption !== '' ? $categoryOption : basename($dir)];
            }

            return $folders;
        }

        foreach ([$arg, public_path('images/import/'.$arg), base_path($arg)] as $candidate) {
            if ($candidate !== '' && is_dir($candidate)) {
                $path = rtrim($candidate, '/\\');

                return [[$path, $categoryOption !== '' ? $categoryOption : basename($path)]];
            }
        }

        return [];
    }

    /** @return string[] подпапки с натуральной сортировкой */
    private function directories(string $base): array
    {
        if (! is_dir($base)) {
            return [];
        }

        $dirs = glob(rtrim($base, '/\\').'/*', GLOB_ONLYDIR) ?: [];
        natcasesort($dirs);

        return array_values($dirs);
    }

    /** @return string[] видеофайлы в папке (натуральная сортировка: «2» перед «10») */
    private function videoFiles(string $dir): array
    {
        $files = [];

        foreach (scandir($dir) ?: [] as $entry) {
            $path = $dir.DIRECTORY_SEPARATOR.$entry;

            if (! is_file($path)) {
                continue;
            }

            if (! in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), self::EXTENSIONS, true)) {
                continue;
            }

            $files[] = $path;
        }

        natcasesort($files);

        return array_values($files);
    }

    /**
     * Имя файла для storage: ASCII, без пробелов и кириллицы (надёжнее для URL и ffmpeg).
     *
     * Если такое имя уже занято видео из другой категории, добавляем суффикс
     * категории: иначе второй файл с тем же basename молча считался бы дубликатом.
     */
    private function storedFilename(string $originalName, string $category = ''): string
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $base = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));

        if ($base === '') {
            $base = 'video-'.substr(md5($originalName), 0, 8);
        }

        $candidate = $base.'.'.$ext;
        $existing = Video::query()->where('filename', $candidate)->first();

        if ($existing === null || $existing->category === $category) {
            return $candidate;
        }

        $suffix = Str::slug($category) ?: 'copy';
        $candidate = $base.'-'.$suffix.'.'.$ext;
        $number = 2;

        while (($existing = Video::query()->where('filename', $candidate)->first()) !== null
            && $existing->category !== $category) {
            $candidate = $base.'-'.$suffix.'-'.$number++.'.'.$ext;
        }

        return $candidate;
    }

    /** Заголовок из имени файла: «блондинка_1.mp4» → «Блондинка 1». */
    private function makeTitle(string $originalName): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $base = str_replace(['_', '-', '+'], ' ', $base);
        $base = trim(preg_replace('/\s+/u', ' ', $base) ?? '');

        if ($base === '') {
            return $originalName;
        }

        return mb_strtoupper(mb_substr($base, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($base, 1, null, 'UTF-8');
    }

    /** Уникальный slug: «blondinka-1», при повторе — «blondinka-1-2». */
    private function uniqueSlug(string $title): string
    {
        $slug = Str::slug($title);

        if ($slug === '') {
            $slug = 'video-'.Str::lower(Str::random(6));
        }

        $base = $slug;
        $suffix = 2;

        while (Video::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Перенос/копирование файла в storage/app/public/videos.
     * Если файл уже лежит там и совпадает по размеру — дубликат не создаётся.
     */
    private function placeFile(string $sourcePath, string $targetPath, bool $copy): bool
    {
        // Папка импорта может совпадать с storage/app/public/videos. Без этой
        // проверки ветка «файл уже на месте» удаляла бы единственную копию.
        if (realpath($sourcePath) === realpath($targetPath)) {
            return true;
        }

        $dir = dirname($targetPath);

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return false;
        }

        if (is_file($targetPath) && filesize($targetPath) === filesize($sourcePath)) {
            return $copy ? true : @unlink($sourcePath);
        }

        if ($copy) {
            return @copy($sourcePath, $targetPath);
        }

        // rename не работает между разными файловыми системами — тогда copy + unlink
        if (@rename($sourcePath, $targetPath)) {
            return true;
        }

        if (@copy($sourcePath, $targetPath)) {
            return @unlink($sourcePath);
        }

        return false;
    }
}

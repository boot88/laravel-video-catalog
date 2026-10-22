<?php

/**
 * Диагностика генерации превью (ручной запуск: php test-ffmpeg.php).
 * Раньше здесь были захардкожены Windows-пути и -ss стоял после -i;
 * теперь используется тот же код, что и приложение.
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Video;
use App\Services\VideoThumbnailService;

echo "Проверка ffmpeg и генерации превью\n";
echo str_repeat('=', 40), "\n";

$binary = VideoThumbnailService::ffmpegBinary();
echo 'ffmpeg: ', $binary ?? '❌ не найден (задайте FFMPEG_BINARIES в .env)', "\n";

if ($binary !== null) {
    echo '  версия: ', trim((string) shell_exec(escapeshellarg($binary).' -version 2>&1 | head -1')), "\n";
}

echo 'кадр берётся с секунды: ', (int) config('ffmpeg.thumbnail_second', 10), "\n\n";

$video = Video::query()->select(['id', 'title', 'filename', 'thumbnail'])->first();

if (! $video) {
    exit("В базе нет видео.\n");
}

$videoPath = storage_path('app/public/videos/'.$video->filename);

echo "Видео: #{$video->id} {$video->title}\n";
echo "Файл: {$videoPath} — ", (is_file($videoPath) ? '✅ есть' : '❌ нет (будет создана цветная заглушка)'), "\n";

$started = microtime(true);
$ok = VideoThumbnailService::generateThumbnail($video);
$elapsed = round((microtime(true) - $started) * 1000);

echo $ok ? '✅ Превью создано' : '❌ Превью не создано', " за {$elapsed} мс\n";

$thumbnailPath = $video->thumbnail ? storage_path('app/public/'.$video->thumbnail) : null;

if ($thumbnailPath && is_file($thumbnailPath)) {
    $info = @getimagesize($thumbnailPath);
    echo 'Файл: ', $video->thumbnail, ' (', round(filesize($thumbnailPath) / 1024, 1), " КБ)\n";

    if ($info) {
        echo "Размер: {$info[0]}x{$info[1]}, ", image_type_to_mime_type($info[2]), "\n";
    }
} else {
    echo 'Путь к превью в БД: ', var_export($video->thumbnail, true), "\n";
}

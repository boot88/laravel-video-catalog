<?php

namespace App\Services;

use App\Models\Video;
use App\Support\Media;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class VideoThumbnailService
{
    /** Кеш проверок существования превью за время запроса (раньше: stat-вызов на каждую карточку). */
    private static array $existsCache = [];

    /** Шаблон URL заглушки — route() собирается один раз на запрос. */
    private static ?string $placeholderTemplate = null;

    public static function generateThumbnail(Video $video, ?int $second = null): bool
    {
        try {
            // Пытаемся создать превью из видео
            return self::createVideoThumbnail(
                $video,
                $second ?? $video->thumbnail_time ?? (int) config('ffmpeg.thumbnail_second', 10)
            );
        } catch (\Throwable $e) {
            Log::error('Video thumbnail generation failed: '.$e->getMessage());

            // Если не получилось из видео, создаем цветное превью
            return self::createColorfulThumbnail($video);
        }
    }

    /**
     * Поиск бинарника ffmpeg: конфиг → типовые пути → PATH.
     * Раньше путь был жёстко зашит как 'C:\Soz\laragon\bin\ffmpeg\bin\ffmpeg.exe',
     * из-за чего на не-Windows машинах генерация превью падала.
     */
    public static function ffmpegBinary(): ?string
    {
        static $resolved = null;

        if ($resolved !== null) {
            return $resolved === '' ? null : $resolved;
        }

        $candidates = array_filter([
            (string) config('ffmpeg.ffmpeg.binaries', ''),
            getenv('HOME') ? getenv('HOME').'/.local/bin/ffmpeg' : '',
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/opt/homebrew/bin/ffmpeg',
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\Soz\\laragon\\bin\\ffmpeg\\bin\\ffmpeg.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $resolved = $candidate;
            }
        }

        // Последний шанс — поиск в PATH (windows: where, *nix: which)
        try {
            $finder = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', 'ffmpeg']);
            $finder->setTimeout(5);
            $finder->run();

            if ($finder->isSuccessful()) {
                $found = trim(strtok($finder->getOutput(), "\r\n") ?: '');
                if ($found !== '' && is_file($found) && is_executable($found)) {
                    return $resolved = $found;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ffmpeg search in PATH failed: '.$e->getMessage());
        }

        $resolved = '';

        return null;
    }

    private static function createVideoThumbnail(Video $video, int $second = 10): bool
    {
        $videoPath = Media::videoPath((string) $video->filename);

        if ($videoPath === null) {
            throw new \RuntimeException('Video file not found: '.$video->filename);
        }

        $binary = self::ffmpegBinary();

        if ($binary === null) {
            throw new \RuntimeException('ffmpeg binary not found (задайте FFMPEG_BINARIES в .env)');
        }

        $thumbnailName = 'video_thumb_'.$video->id.'.jpg';
        $thumbnailPath = storage_path('app/public/thumbnails/'.$thumbnailName);

        $thumbnailsDir = dirname($thumbnailPath);
        if (! is_dir($thumbnailsDir)) {
            mkdir($thumbnailsDir, 0755, true);
        }

        // Первый кадр: пробуем запрошенную секунду, затем 1с и 0с
        // (короткие ролики не «дотягивают» до 10-й секунды — раньше это была ошибка).
        $found = false;
        foreach (array_values(array_unique([max(0, $second), 1, 0])) as $candidateSecond) {
            if (self::extractFrame($binary, $videoPath, $thumbnailPath, $candidateSecond)) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            @unlink($thumbnailPath);
            throw new \RuntimeException('ffmpeg did not produce a thumbnail for '.$video->filename);
        }

        $video->thumbnail = 'thumbnails/'.$thumbnailName;
        $video->save();

        return true;
    }

    /**
     * Извлечение одного кадра. -ss стоит ДО -i (быстрый seek: ffmpeg не декодирует
     * видео с начала), размер задаётся в самом ffmpeg — GD-пересжатие больше не нужно.
     */
    private static function extractFrame(string $binary, string $videoPath, string $thumbnailPath, int $second): bool
    {
        $process = new Process([
            $binary,
            '-hide_banner',
            '-loglevel', 'error',
            '-y',
            '-ss', (string) $second,
            '-i', $videoPath,
            '-frames:v', '1',
            '-q:v', (string) config('ffmpeg.thumbnail_quality', 3),
            '-vf', 'scale='.(int) config('ffmpeg.thumbnail_width', 1280).':-2',
            $thumbnailPath,
        ]);

        $process->setTimeout((float) config('ffmpeg.process_timeout', 120));

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::error('ffmpeg process error: '.$e->getMessage());

            return false;
        }

        if (! $process->isSuccessful() || ! is_file($thumbnailPath) || filesize($thumbnailPath) === 0) {
            Log::warning('ffmpeg frame extraction failed', [
                'second' => $second,
                'error' => trim($process->getErrorOutput()),
            ]);
            @unlink($thumbnailPath);

            return false;
        }

        return true;
    }

    /**
     * Цветная заглушка (fallback, если видеофайла нет). Цвет выбирается
     * детерминированно по ID видео — раньше был rand(), из-за чего превью
     * «мигало» при каждом обновлении страницы.
     */
    public static function createColorfulThumbnail(Video $video): bool
    {
        try {
            $thumbnailName = 'color_thumb_'.($video->id ?: md5((string) $video->title)).'.jpg';
            $thumbnailPath = storage_path('app/public/thumbnails/'.$thumbnailName);

            $thumbnailsDir = dirname($thumbnailPath);
            if (! is_dir($thumbnailsDir)) {
                mkdir($thumbnailsDir, 0755, true);
            }

            if (! function_exists('imagecreatetruecolor')) {
                throw new \RuntimeException('GD extension not available');
            }

            $gradients = [
                ['#ff6b9d', '#4ecdc4'],
                ['#667eea', '#764ba2'],
                ['#f093fb', '#f5576c'],
                ['#4facfe', '#00f2fe'],
                ['#43e97b', '#38f9d7'],
            ];

            $gradient = $gradients[abs((int) $video->id) % count($gradients)];
            $color1 = self::hexToRgb($gradient[0]);
            $color2 = self::hexToRgb($gradient[1]);

            $width = (int) config('ffmpeg.thumbnail_width', 1280);
            $height = (int) config('ffmpeg.thumbnail_height', 720);

            $image = imagecreatetruecolor($width, $height);

            // Градиент полосами по 4 пикселя: 180 вызовов вместо 720
            $bands = 4;
            for ($y = 0; $y < $height; $y += $bands) {
                $ratio = $y / $height;
                $color = imagecolorallocate(
                    $image,
                    (int) ($ratio * $color2['r'] + (1 - $ratio) * $color1['r']),
                    (int) ($ratio * $color2['g'] + (1 - $ratio) * $color1['g']),
                    (int) ($ratio * $color2['b'] + (1 - $ratio) * $color1['b'])
                );
                imagefilledrectangle($image, 0, $y, $width, min($y + $bands - 1, $height), $color);
            }

            self::drawTitle($image, $width, $height, (string) $video->title);

            imagejpeg($image, $thumbnailPath, 85);
            imagedestroy($image);

            $video->thumbnail = 'thumbnails/'.$thumbnailName;
            $video->save();

            Log::info("Colorful thumbnail created for: {$video->title}");

            return true;
        } catch (\Throwable $e) {
            Log::error('Color thumbnail creation failed: '.$e->getMessage());

            return self::createSimpleThumbnail($video);
        }
    }

    private static function createSimpleThumbnail(Video $video): bool
    {
        try {
            $thumbnailName = 'simple_thumb_'.($video->id ?: md5((string) $video->title)).'.jpg';
            $thumbnailPath = storage_path('app/public/thumbnails/'.$thumbnailName);

            $thumbnailsDir = dirname($thumbnailPath);
            if (! is_dir($thumbnailsDir)) {
                mkdir($thumbnailsDir, 0755, true);
            }

            $width = (int) config('ffmpeg.thumbnail_width', 1280);
            $height = (int) config('ffmpeg.thumbnail_height', 720);

            $image = imagecreatetruecolor($width, $height);

            $bgColor = imagecolorallocate($image, 40, 40, 60);
            imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

            self::drawTitle($image, $width, $height, (string) $video->title);

            imagejpeg($image, $thumbnailPath, 80);
            imagedestroy($image);

            $video->thumbnail = 'thumbnails/'.$thumbnailName;
            $video->save();

            return true;
        } catch (\Throwable $e) {
            Log::error('Simple thumbnail also failed: '.$e->getMessage());

            return false;
        }
    }

    /** Текст по центру превью: TTF-шрифт, если найден, иначе imagestring. */
    private static function drawTitle(\GdImage $image, int $width, int $height, string $title): void
    {
        if ($title === '') {
            return;
        }

        $textColor = imagecolorallocate($image, 255, 255, 255);
        $font = self::getFontPath();
        $text = mb_substr($title, 0, 40);

        if ($font !== null) {
            $size = 32;
            $box = imagettfbbox($size, 0, $font, $text);
            $textWidth = abs($box[2] - $box[0]);
            $textHeight = abs($box[7] - $box[1]);

            imagettftext(
                $image,
                $size,
                0,
                (int) (($width - $textWidth) / 2),
                (int) (($height + $textHeight) / 2),
                $textColor,
                $font,
                $text
            );

            return;
        }

        imagestring($image, 5, (int) (($width - strlen($text) * 9) / 2), (int) ($height / 2), $text, $textColor);
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = str_replace('#', '', $hex);

        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }

        return ['r' => $r, 'g' => $g, 'b' => $b];
    }

    private static function getFontPath(): ?string
    {
        $possiblePaths = [
            resource_path('fonts/arial.ttf'),
            resource_path('fonts/Roboto-Regular.ttf'),
            storage_path('fonts/arial.ttf'),
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
            'C:\\Windows\\Fonts\\calibri.ttf',
            'C:\\Windows\\Fonts\\tahoma.ttf',
        ];

        foreach ($possiblePaths as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * URL превью. Для отсутствующих превью отдаём ссылку на кешируемый
     * SVG-маршрут (video.thumbnail.placeholder) вместо data-URI с rand().
     */
    public static function getThumbnailUrl(?string $thumbnailPath, ?string $seed = null): string
    {
        if ($thumbnailPath) {
            if (self::thumbnailExists($thumbnailPath)) {
                return asset('storage/'.$thumbnailPath);
            }

            // Демо-постеры лежат в public/media и коммитятся в git, поэтому
            // остаются доступны даже без storage:link и запуска ffmpeg.
            if (is_file(public_path($thumbnailPath))) {
                return asset($thumbnailPath);
            }
        }

        $seed = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($seed ?? '0')) ?: '0';

        try {
            self::$placeholderTemplate ??= route('video.thumbnail.placeholder', ['seed' => '__SEED__']);

            return str_replace('__SEED__', $seed, self::$placeholderTemplate);
        } catch (\Throwable $e) {
            // Роуты недоступны (например, в консольной команде) — отдаём inline SVG
            return 'data:image/svg+xml;base64,'.base64_encode(self::placeholderSvg($seed));
        }
    }

    /** Проверка существования файла превью (результат кешируется на время запроса). */
    private static function thumbnailExists(string $path): bool
    {
        return self::$existsCache[$path] ??= Storage::disk('public')->exists($path);
    }

    /** Разметка SVG-заглушки (используется также контроллером маршрута-заглушки). */
    public static function placeholderSvg(string $seed): string
    {
        $palette = [
            ['#ff6b9d', '#4ecdc4'],
            ['#667eea', '#764ba2'],
            ['#f093fb', '#f5576c'],
            ['#4facfe', '#00f2fe'],
            ['#43e97b', '#38f9d7'],
        ];

        $color = $palette[abs(crc32($seed)) % count($palette)];
        $label = 'VIDEO #'.htmlspecialchars(substr($seed, 0, 12), ENT_QUOTES, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720">'
            .'<defs><linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">'
            .'<stop offset="0%" stop-color="'.$color[0].'"/>'
            .'<stop offset="100%" stop-color="'.$color[1].'"/>'
            .'</linearGradient></defs>'
            .'<rect width="1280" height="720" fill="url(#g)"/>'
            .'<circle cx="640" cy="320" r="80" fill="rgba(255,255,255,0.2)"/>'
            .'<polygon points="610,290 690,320 610,350" fill="#fff"/>'
            .'<text x="640" y="480" text-anchor="middle" fill="#fff" font-family="Arial" font-size="36" font-weight="bold">FASHION VIDEO</text>'
            .'<text x="640" y="530" text-anchor="middle" fill="rgba(255,255,255,0.9)" font-family="Arial" font-size="24">'.$label.'</text>'
            .'</svg>';
    }
}

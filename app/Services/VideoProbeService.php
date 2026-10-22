<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Чтение метаданных видеофайла через ffprobe (длительность, разрешение).
 * Нужно импорту видео: раньше эти поля заполнялись случайными числами в сидере.
 */
class VideoProbeService
{
    private static ?string $binary = null;

    private static bool $resolved = false;

    /** Поиск ffprobe: конфиг (FFPROBE_BINARIES) → типовые пути → PATH. */
    public static function ffprobeBinary(): ?string
    {
        if (self::$resolved) {
            return self::$binary;
        }

        self::$resolved = true;

        $configured = (string) config('ffmpeg.ffprobe.binaries', '');

        $candidates = array_filter([
            $configured,
            '/usr/bin/ffprobe',
            '/usr/local/bin/ffprobe',
            getenv('HOME') ? getenv('HOME').'/.local/bin/ffprobe' : '',
            '/opt/homebrew/bin/ffprobe',
            'C:\\ffmpeg\\bin\\ffprobe.exe',
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return self::$binary = $candidate;
            }
        }

        try {
            $finder = new Process([PHP_OS_FAMILY === 'Windows' ? 'where' : 'which', 'ffprobe']);
            $finder->setTimeout(5);
            $finder->run();

            if ($finder->isSuccessful()) {
                $found = trim(strtok($finder->getOutput(), "\r\n") ?: '');
                if ($found !== '' && is_file($found) && is_executable($found)) {
                    return self::$binary = $found;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('ffprobe search in PATH failed: '.$e->getMessage());
        }

        return self::$binary = null;
    }

    /**
     * Метаданные первого видеопотока.
     *
     * @return array{duration:int,width:int,height:int,has_video:bool}|null null — если ffprobe нет или файл нечитаем
     */
    public static function probe(string $path): ?array
    {
        $binary = self::ffprobeBinary();

        if ($binary === null || ! is_file($path)) {
            return null;
        }

        $process = new Process([
            $binary,
            '-hide_banner',
            '-loglevel', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=width,height,duration',
            '-show_entries', 'format=duration',
            '-of', 'json',
            $path,
        ]);

        $process->setTimeout((float) config('ffmpeg.process_timeout', 120));

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::warning('ffprobe process error: '.$e->getMessage());

            return null;
        }

        if (! $process->isSuccessful()) {
            Log::warning('ffprobe failed', [
                'file' => basename($path),
                'error' => trim($process->getErrorOutput()),
            ]);

            return null;
        }

        $data = json_decode($process->getOutput(), true);

        if (! is_array($data)) {
            return null;
        }

        $stream = $data['streams'][0] ?? null;

        if ($stream === null) {
            // Видеопотока нет (не видео, либо битый файл)
            return [
                'duration' => 0,
                'width' => 0,
                'height' => 0,
                'has_video' => false,
            ];
        }

        $duration = (int) round(max(
            (float) ($stream['duration'] ?? 0),
            (float) ($data['format']['duration'] ?? 0)
        ));

        return [
            'duration' => max(0, $duration),
            'width' => (int) ($stream['width'] ?? 0),
            'height' => (int) ($stream['height'] ?? 0),
            'has_video' => true,
        ];
    }

    /**
     * Качество по меньшей стороне кадра (вертикальные видео 1080x1920 — тоже Full HD).
     */
    public static function qualityLabel(int $width, int $height = 0): string
    {
        $side = $width > 0 && $height > 0 ? min($width, $height) : max($width, $height);

        return match (true) {
            // 4K — это минимум 2160 по меньшей стороне; порог 1900 ошибочно
            // помечал квадратные 1920x1920 как 4K.
            $side >= 2000 => '4K',
            $side >= 1000 => 'Full HD',
            $side >= 700 => '720p',
            $side >= 400 => '480p',
            $side >= 200 => '240p',
            default => 'HD',
        };
    }
}

<?php

namespace App\Services;

use App\Support\Media;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class VideoPreviewService
{
    /**
     * Делает короткий беззвучный mp4 для hover-превью. Один файл на исходник:
     * если 160 записей ссылаются на 5 роликов, ffmpeg запускается 5 раз.
     */
    public static function generateForFilename(string $filename, int $seconds = 4, int $width = 640, bool $force = false): ?string
    {
        $source = Media::videoPath($filename);

        if ($source === null) {
            Log::warning('Preview source not found', ['filename' => $filename]);

            return null;
        }

        $binary = VideoThumbnailService::ffmpegBinary();

        if ($binary === null) {
            Log::warning('ffmpeg binary not found for preview generation');

            return null;
        }

        $relative = 'previews/'.pathinfo($filename, PATHINFO_FILENAME).'.mp4';
        $target = storage_path('app/public/'.$relative);

        if (! $force && is_file($target) && filesize($target) > 0) {
            return $relative;
        }

        if (! is_dir(dirname($target)) && ! @mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target))) {
            return null;
        }

        $process = new Process([
            $binary,
            '-hide_banner',
            '-loglevel', 'error',
            '-y',
            '-ss', '0',
            '-t', (string) max(1, $seconds),
            '-i', $source,
            '-vf', 'scale='.max(240, $width).':-2',
            '-an',
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-crf', '28',
            '-pix_fmt', 'yuv420p',
            '-movflags', '+faststart',
            $target,
        ]);

        $process->setTimeout((float) config('ffmpeg.process_timeout', 120));

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::error('Preview generation failed: '.$e->getMessage());

            return null;
        }

        if (! $process->isSuccessful() || ! is_file($target) || filesize($target) === 0) {
            Log::warning('Preview generation failed', [
                'filename' => $filename,
                'error' => trim($process->getErrorOutput()),
            ]);
            @unlink($target);

            return null;
        }

        return $relative;
    }
}

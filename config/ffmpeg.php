<?php

return [
    /*
    |--------------------------------------------------------------------------
    | pbmedia/laravel-ffmpeg (config пакета: vendor/pbmedia/laravel-ffmpeg/config/config.php)
    |--------------------------------------------------------------------------
    | Именно эти ключи читает FFMpeg::create(). Раньше здесь были 'ffmpeg_path'
    | и 'ffprobe_path' — пакет их не видит, а сервис превью не читал конфиг
    | вообще и падал на хардкод Windows-пути.
    */
    'ffmpeg' => [
        'binaries' => env('FFMPEG_BINARIES', env('FFMPEG_PATH', 'ffmpeg')),
        'threads' => (int) env('FFMPEG_THREADS', 12),
    ],

    'ffprobe' => [
        'binaries' => env('FFPROBE_BINARIES', env('FFPROBE_PATH', 'ffprobe')),
    ],

    'timeout' => 3600,

    'log_channel' => env('LOG_CHANNEL', 'stack'),

    'temporary_files_root' => env('FFMPEG_TEMPORARY_FILES_ROOT', sys_get_temp_dir()),

    /*
    |--------------------------------------------------------------------------
    | Генерация превью (App\Services\VideoThumbnailService)
    |--------------------------------------------------------------------------
    */
    'thumbnail_second' => (float) env('FFMPEG_THUMBNAIL_SECOND', 10),
    'thumbnail_width' => (int) env('FFMPEG_THUMBNAIL_WIDTH', 1280),
    'thumbnail_height' => (int) env('FFMPEG_THUMBNAIL_HEIGHT', 720),
    'thumbnail_quality' => (int) env('FFMPEG_THUMBNAIL_QUALITY', 3),

    /** Максимальное время одного вызова ffmpeg (сек). */
    'process_timeout' => (float) env('FFMPEG_PROCESS_TIMEOUT', 120),
];

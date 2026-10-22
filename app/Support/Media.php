<?php

namespace App\Support;

class Media
{
    /**
     * URL для файла из public/ или storage/. Внешние URL возвращаются как есть.
     * null/пустая строка → переданный fallback (например, заглушка превью).
     */
    /** Абсолютный путь к ролику: storage имеет приоритет, затем public/media. */
    public static function videoPath(string $filename): ?string
    {
        foreach ([storage_path('app/public/videos/'.$filename), public_path('media/videos/'.$filename)] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function url(?string $path, ?string $fallback = null): string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return $fallback ?? '';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '//')) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }
}

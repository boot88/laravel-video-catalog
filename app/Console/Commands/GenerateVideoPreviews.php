<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\VideoPreviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateVideoPreviews extends Command
{
    protected $signature = 'videos:generate-previews
                            {--limit=0 : Обработать не более N уникальных исходников}
                            {--seconds=4 : Длительность превью}
                            {--width=640 : Ширина превью}
                            {--force : Пересоздать существующие файлы}';

    protected $description = 'Создать короткие беззвучные mp4-превью для hover-карточек';

    public function handle(): int
    {
        $query = Video::query()
            ->select('filename')
            ->whereNotNull('filename')
            ->where('filename', '!=', '')
            ->distinct();

        if (($limit = max(0, (int) $this->option('limit'))) > 0) {
            $query->limit($limit);
        }

        $filenames = $query->pluck('filename');
        $created = 0;
        $failed = 0;

        foreach ($filenames as $filename) {
            $this->line('  '.$filename);
            $preview = VideoPreviewService::generateForFilename(
                (string) $filename,
                max(1, (int) $this->option('seconds')),
                max(240, (int) $this->option('width')),
                (bool) $this->option('force')
            );

            if ($preview === null) {
                $failed++;
                $this->warn('    не удалось создать');

                continue;
            }

            Video::query()->where('filename', $filename)->update(['preview' => $preview]);
            $created++;
            $this->info('    '.$preview);
        }

        Cache::forget('video_footer_stats');

        $this->newLine();
        $this->info("Готово: {$created} превью, ошибок: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

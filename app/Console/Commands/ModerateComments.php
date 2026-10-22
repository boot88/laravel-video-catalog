<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ModerateComments extends Command
{
    protected $signature = 'comments:moderate
                            {id? : ID комментария}
                            {--approve : Опубликовать}
                            {--reject : Отклонить}
                            {--list : Показать очередь модерации}';

    protected $description = 'Очередь комментариев без административной панели';

    public function handle(): int
    {
        $id = $this->argument('id');

        if ($id === null) {
            return $this->listPending();
        }

        $comment = Comment::query()->find($id);

        if ($comment === null) {
            $this->error('Комментарий не найден.');

            return self::FAILURE;
        }

        $status = match (true) {
            (bool) $this->option('approve') => 'approved',
            (bool) $this->option('reject') => 'rejected',
            default => null,
        };

        if ($status === null) {
            $this->warn('Укажите --approve или --reject.');

            return self::INVALID;
        }

        $comment->status = $status;
        $comment->save();

        $video = Video::query()->find($comment->video_id);

        if ($video !== null) {
            $video->updateRating();
        }

        Cache::forget('video_footer_stats');

        $this->info("Комментарий #{$comment->id}: {$status}.");

        return self::SUCCESS;
    }

    private function listPending(): int
    {
        $comments = Comment::query()
            ->where('status', 'pending')
            ->with('video:id,title,slug')
            ->latest()
            ->limit(100)
            ->get();

        if ($comments->isEmpty()) {
            $this->info('Очередь модерации пуста.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Видео', 'Автор', 'Комментарий', 'Дата'],
            $comments->map(fn (Comment $comment): array => [
                $comment->id,
                $comment->video?->title,
                $comment->username,
                mb_strimwidth($comment->content, 0, 70, '…'),
                $comment->created_at?->format('d.m.Y H:i'),
            ])->all()
        );

        $this->line('Одобрить: php artisan comments:moderate <id> --approve');
        $this->line('Отклонить: php artisan comments:moderate <id> --reject');

        return self::SUCCESS;
    }
}

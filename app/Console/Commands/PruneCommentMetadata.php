<?php

namespace App\Console\Commands;

use App\Models\Comment;
use Illuminate\Console\Command;

class PruneCommentMetadata extends Command
{
    protected $signature = 'comments:prune
                            {--days=180 : Обезличить комментарии старше N дней}
                            {--dry-run : Только показать количество}';

    protected $description = 'Обезличить IP-адреса и User-Agent старых комментариев';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = Comment::query()
            ->where('created_at', '<', $cutoff)
            ->where(function ($query): void {
                $query->where('ip_address', '!=', 'скрыто')
                    ->orWhere('user_agent', '!=', 'скрыто');
            });

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("К обезличиванию: {$count} комментариев старше {$days} дней.");

            return self::SUCCESS;
        }

        $query->update([
            'ip_address' => 'скрыто',
            'user_agent' => 'скрыто',
        ]);

        $this->info("Обезличено комментариев: {$count}.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\UserRender;
use Illuminate\Console\Command;

class CleanupUserRenders extends Command
{
    protected $signature = 'user-renders:cleanup {--days= : Keep files of rejected renders for this many days}';

    protected $description = 'Keep each user render image on the disk matching its status and delete images of renders rejected long ago';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?? config('user-renders.rejected_retention_days')));
        $moved = 0;
        $deleted = 0;

        UserRender::whereNotNull('image')->chunkById(100, function ($renders) use (&$moved, &$deleted, $days) {
            foreach ($renders as $render) {
                if ($render->status === 'rejected' && $render->rejected_at?->lte(now()->subDays($days))) {
                    $render->forgetImage();
                    $deleted++;

                    continue;
                }

                $before = $render->imageDisk();
                $render->syncImageLocation();
                if ($before !== null && $before !== $render->imageDisk()) {
                    $moved++;
                }
            }
        });

        $this->info("Moved {$moved} image(s), deleted {$deleted} image(s) of renders rejected more than {$days} days ago.");

        return self::SUCCESS;
    }
}

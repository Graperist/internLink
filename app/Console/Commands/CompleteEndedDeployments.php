<?php

namespace App\Console\Commands;

use App\Support\DeploymentLifecycle;
use Illuminate\Console\Command;

class CompleteEndedDeployments extends Command
{
    protected $signature = 'internlink:complete-ended-deployments';

    protected $description = 'Mark active deployments Completed once their end date has passed.';

    public function handle(): int
    {
        $count = DeploymentLifecycle::completeEnded();
        $this->info("Completed {$count} deployment(s).");

        return self::SUCCESS;
    }
}

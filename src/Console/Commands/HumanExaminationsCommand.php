<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Console\Commands;

use Illuminate\Console\Command;

class HumanExaminationsCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'human-examinations:placeholder';

    /**
     * The command description.
     */
    protected $description = 'Placeholder Artisan command shipped by the package human-examinations.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->line('HumanExaminations placeholder command executed.');

        return self::SUCCESS;
    }
}

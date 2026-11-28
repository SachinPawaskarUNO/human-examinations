<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Console\Commands;

use HumanExaminations\HumanExaminations\Database\Seeders\HumanExaminationsSeeder;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    protected $signature = 'human-examinations:install
        {--migrate : Run the application\'s pending migrations after publishing}
        {--seed : Add sample examinations after migrating (skipped in production)}';

    protected $description = 'Publish the Human Examinations config and migrations';

    public function handle(): int
    {
        $status = $this->call('vendor:publish', [
            '--tag' => ['human-examinations-config', 'human-examinations-migrations'],
        ]);

        if ($status !== self::SUCCESS) {
            return $status;
        }

        if ($this->option('migrate') || ($this->input->isInteractive() && confirm('Run pending application migrations now?', default: false))) {
            $status = $this->call('migrate');

            if ($status === self::SUCCESS && ($this->option('seed') || ($this->input->isInteractive() && ! $this->laravel->environment('production') && confirm('Add sample examinations?', default: false)))) {
                return $this->call('db:seed', ['--class' => HumanExaminationsSeeder::class, '--force' => true]);
            }

            return $status;
        }

        $this->info('Run php artisan migrate when you are ready to create the examination tables.');

        return self::SUCCESS;
    }
}

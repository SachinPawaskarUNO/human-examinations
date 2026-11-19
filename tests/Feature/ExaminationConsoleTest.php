<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Tests\Feature;

use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Tests\TestCase;
use Illuminate\Support\Facades\Schema;

class ExaminationConsoleTest extends TestCase
{
    private string $installPath;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $this->installPath = sys_get_temp_dir().'/human-examinations-install-'.bin2hex(random_bytes(8));
        $app->useConfigPath($this->installPath.'/config');
        $app->useDatabasePath($this->installPath.'/database');
    }

    protected function tearDown(): void
    {
        foreach ([$this->installPath.'/config', $this->installPath.'/database/migrations'] as $directory) {
            if (is_dir($directory)) {
                foreach (glob($directory.'/*.php') ?: [] as $file) {
                    unlink($file);
                }
                rmdir($directory);
            }
        }
        foreach ([$this->installPath.'/database', $this->installPath] as $directory) {
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
        parent::tearDown();
    }

    public function test_install_publishes_files_without_overwriting_config_or_running_migrations_by_default(): void
    {
        $this->artisan('human-examinations:install', ['--no-interaction' => true])->assertSuccessful();
        $config = $this->installPath.'/config/human-examinations.php';
        $this->assertFileExists($config);
        $migrations = glob($this->installPath.'/database/migrations/*.php');
        $this->assertCount(count(glob(__DIR__.'/../../database/migrations/*.php') ?: []), $migrations);
        $this->assertFalse(Schema::hasTable('human_examinations'));

        file_put_contents($config, "<?php return ['host' => true];");
        $this->artisan('human-examinations:install', ['--migrate' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertSame(['host' => true], require $config);
        $this->assertSame($migrations, glob($this->installPath.'/database/migrations/*.php'));
        $this->assertTrue(Schema::hasTable('human_examinations'));
        $this->assertTrue(Schema::hasTable('human_examination_observations'));
    }

    public function test_interactive_install_can_publish_without_running_migrations(): void
    {
        $this->artisan('human-examinations:install')
            ->expectsConfirmation('Run pending application migrations now?', 'no')
            ->assertSuccessful();
        $this->assertFileExists($this->installPath.'/config/human-examinations.php');
        $this->assertFalse(Schema::hasTable('human_examinations'));
    }

    public function test_create_accepts_script_options_and_rejects_invalid_dates(): void
    {
        $this->migratePackage();
        $this->artisan('human-examinations:create', ['--reference' => 'SCRIPT-1', '--date' => '2026-02-30', '--no-interaction' => true])
            ->assertFailed();
        $this->assertDatabaseCount('human_examinations', 0);
        $this->artisan('human-examinations:create', ['--reference' => 'SCRIPT-1', '--date' => '2026-02-28', '--no-interaction' => true])
            ->expectsOutputToContain('Examination #1 created.')
            ->assertSuccessful();
        $this->assertSame('2026-02-28', Examination::query()->sole()->date?->format('Y-m-d'));
    }

    public function test_operators_can_create_with_prompts_or_cancel_without_a_write(): void
    {
        $this->migratePackage();
        foreach (['no', 'yes'] as $confirmation) {
            $this->artisan('human-examinations:create')
                ->expectsQuestion('Reference (optional)', 'INTERACTIVE')
                ->expectsQuestion('Examiner (optional)', 'Example Examiner')
                ->expectsQuestion('Date (YYYY-MM-DD, optional)', '')
                ->expectsQuestion('Notes (optional)', 'Sample note')
                ->expectsConfirmation('Create this examination?', $confirmation)
                ->assertSuccessful();
            $this->assertDatabaseCount('human_examinations', $confirmation === 'yes' ? 1 : 0);
        }
        $this->assertDatabaseHas('human_examinations', ['reference' => 'INTERACTIVE', 'examiner' => 'Example Examiner', 'notes' => 'Sample note', 'date' => null]);
    }

    public function test_list_and_show_only_include_active_examinations_and_observations(): void
    {
        $this->migratePackage();
        $exam = Examination::create(['reference' => 'ACTIVE-EXAM']);
        Examination::create(['reference' => 'DELETED-EXAM'])->delete();
        $exam->observations()->create(['number' => 1, 'type' => 'Scar', 'label' => 'Active observation']);
        $exam->observations()->create(['number' => 2, 'type' => 'Bruise', 'label' => 'Deleted observation'])->delete();

        $this->artisan('human-examinations:list', ['--limit' => '1'])
            ->expectsOutputToContain('ACTIVE-EXAM')->doesntExpectOutputToContain('DELETED-EXAM')->assertSuccessful();
        $this->artisan('human-examinations:list', ['--limit' => '0'])->assertFailed();
        $this->artisan('human-examinations:show')
            ->expectsQuestion('Examination ID', (string) $exam->id)
            ->expectsOutputToContain('Active observation')->doesntExpectOutputToContain('Deleted observation')->assertSuccessful();
        $this->artisan('human-examinations:show', ['examination' => 2, '--no-interaction' => true])->assertFailed();
        $this->artisan('human-examinations:show', ['--no-interaction' => true])->assertFailed();
    }

    private function migratePackage(): void
    {
        $this->artisan('migrate', [
            '--path' => realpath(__DIR__.'/../../database/migrations'),
            '--realpath' => true,
        ])->assertSuccessful();
    }
}

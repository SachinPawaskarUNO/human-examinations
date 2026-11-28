<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Tests\Feature;

use HumanExaminations\HumanExaminations\Actions\CreateObservation;
use HumanExaminations\HumanExaminations\Actions\ExportExamination;
use HumanExaminations\HumanExaminations\Actions\ImportExamination;
use HumanExaminations\HumanExaminations\Models\ExaminationObservation;
use HumanExaminations\HumanExaminations\Models\HumanExamination;
use HumanExaminations\HumanExaminations\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use RuntimeException;

class ExaminationTransferTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', str_repeat('t', 32));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('human-examinations.routes.enabled', true);
        $app['config']->set('human-examinations.routes.prefix', 'transfer/v1');
        $app['config']->set('human-examinations.routes.name_prefix', 'transfer.');
        $app['config']->set('human-examinations.workspace_route', 'host.examinations.show');
    }

    protected function defineRoutes($router): void
    {
        $router->get('/host/examinations/{examination}', fn (): string => 'Host examination')->name('host.examinations.show');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations'), '--realpath' => true])->assertSuccessful();
    }

    public function test_guests_and_unconfigured_policies_cannot_import_or_export(): void
    {
        $exam = HumanExamination::create([]);
        $this->getJson($this->exportUrl($exam))->assertUnauthorized();
        $this->upload($this->document())->assertUnauthorized();
        $this->actingAs((new User)->forceFill(['id' => 1]));
        $this->getJson($this->exportUrl($exam))->assertForbidden();
        $this->upload($this->document())->assertForbidden();
        $this->assertDatabaseCount('human_examinations', 1);
    }

    public function test_export_uses_view_permission_and_import_uses_create_permission(): void
    {
        $this->authorize(2);
        $exam = HumanExamination::create([]);
        $this->get($this->exportUrl($exam))->assertOk();
        $this->upload($this->document())->assertForbidden();
        $exam->update(['reference' => 'PRIVATE']);
        $this->get($this->exportUrl($exam))->assertForbidden();
    }

    public function test_json_round_trip_preserves_active_data_numbers_and_locations_with_new_database_ids(): void
    {
        $this->authorize();
        $exam = HumanExamination::create(['reference' => 'ROUND-TRIP', 'examiner' => 'Demo Examiner', 'date' => '2026-11-19', 'notes' => "Line one\nLine two"]);
        $first = $exam->observations()->create(['number' => 7, 'type' => 'Scar', 'region' => 'Custom precise region', 'length_mm' => '32.50', 'width_mm' => '0.00', 'sketch_id' => 'body-front', 'x' => 0, 'y' => 1]);
        $exam->observations()->create(['number' => 22, 'type' => 'Tattoo', 'label' => 'Unmapped finding']);
        $deleted = $exam->observations()->create(['number' => 30, 'type' => 'Bruise']);
        $deleted->delete();

        $response = $this->get($this->exportUrl($exam))->assertOk()->assertDownload('human-examination-'.$exam->id.'.json');
        $document = json_decode($response->streamedContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('spawaskar/human-examinations', $document['format']);
        $this->assertSame(1, $document['version']);
        $this->assertSame('2026-11-19', $document['examination']['date']);
        $this->assertSame([7, 22], array_column($document['observations'], 'number'));
        $this->assertArrayNotHasKey('id', $document['examination']);
        $this->assertArrayNotHasKey('id', $document['observations'][0]);
        $this->assertArrayNotHasKey('examination_id', $document['observations'][0]);
        $this->assertSame('0.000000', $document['observations'][0]['x']);

        $this->upload($document)->assertRedirect(route('host.examinations.show', 2));
        $imported = HumanExamination::findOrFail(2);
        $this->assertSame($document, app(ExportExamination::class)->handle($imported));
        $this->assertNotSame($first->id, $imported->observations()->where('number', 7)->sole()->id);
        $this->assertSame(23, app(CreateObservation::class)->handle($imported)->number);
        $this->assertSame(3, $exam->observations()->withTrashed()->count());
        $this->assertSame('ROUND-TRIP', $exam->fresh()->reference);
    }

    public function test_csv_export_quotes_multiline_fields_and_neutralizes_spreadsheet_formulas(): void
    {
        $this->authorize();
        $exam = HumanExamination::create(['reference' => '=1+1', 'notes' => "Quoted \"note\", with\nnew line"]);
        $exam->observations()->create(['number' => 2, 'type' => 'Scar', 'label' => '@SUM(1,2)', 'region' => 'Arm, near elbow', 'description' => "First line\nSecond line"]);
        $response = $this->get($this->exportUrl($exam).'?format=csv')->assertOk()->assertDownload('human-examination-'.$exam->id.'.csv');
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $response->streamedContent()));
        rewind($stream);
        $headers = fgetcsv($stream, escape: '');
        $values = fgetcsv($stream, escape: '');
        fclose($stream);
        $row = array_combine($headers, $values);
        $this->assertSame("'=1+1", $row['examination_reference']);
        $this->assertSame("'@SUM(1,2)", $row['label']);
        $this->assertSame("Quoted \"note\", with\nnew line", $row['examination_notes']);
        $this->assertSame("First line\nSecond line", $row['description']);
        $this->assertSame('Arm, near elbow', $row['region']);
    }

    public function test_empty_examinations_can_be_exported_and_imported(): void
    {
        $this->authorize();
        $exam = app(ImportExamination::class)->handle($this->document([]));
        $this->assertSame([], app(ExportExamination::class)->handle($exam)['observations']);
        $response = $this->get($this->exportUrl($exam).'?format=csv')->assertOk();
        $this->assertStringContainsString('examination_reference', $response->streamedContent());
    }

    public function test_unknown_versions_malformed_json_and_invalid_records_leave_no_partial_import(): void
    {
        $this->authorize();
        $this->post(route('transfer.examinations.import'), ['file' => UploadedFile::fake()->createWithContent('bad.json', '{invalid')], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');
        foreach ([
            array_replace($this->document(), ['version' => 99]),
            array_replace($this->document(), ['format' => 'other-package']),
            array_replace($this->document(), ['id' => 10]),
            $this->document([['number' => 1, 'type' => 'Scar'], ['number' => 1, 'type' => 'Bruise']]),
            $this->document([['number' => 0, 'type' => 'Scar']]),
            $this->document([['number' => '1', 'type' => 'Scar']]),
            $this->document([['number' => true, 'type' => 'Scar']]),
            $this->document([['number' => 1, 'type' => 'Unknown']]),
            $this->document([['number' => 1, 'type' => 'Scar', 'sketch_id' => 'body-front', 'x' => 0.5]]),
            $this->document([['number' => 1, 'type' => 'Scar', 'sketch_id' => 'body-front', 'x' => 1.1, 'y' => 0.5]]),
            $this->document([['number' => 1, 'type' => 'Scar', 'examination_id' => 10]]),
            $this->document([['number' => 1, 'type' => 'Scar', 'length_mm' => -1]]),
            $this->document([['number' => 1, 'type' => 'Scar', 'length_mm' => '   ']]),
            array_replace($this->document(), ['examination' => ['date' => '']]),
            $this->document([['number' => 1, 'type' => 'Scar', 'sketch_id' => '', 'x' => 0, 'y' => 0]]),
        ] as $document) {
            $this->upload($document)->assertUnprocessable();
            $this->assertDatabaseCount('human_examinations', 0);
            $this->assertDatabaseCount('human_examination_observations', 0);
        }
    }

    public function test_upload_distinguishes_json_objects_from_lists(): void
    {
        $this->authorize();
        $this->upload(array_replace($this->document(), ['examination' => []]))
            ->assertUnprocessable()->assertJsonValidationErrors('document.examination');
        $this->upload(array_replace($this->document(), ['observations' => (object) $this->document()['observations']]))
            ->assertUnprocessable()->assertJsonValidationErrors('document.observations');
        $this->upload(array_replace($this->document([]), ['examination' => (object) []]))
            ->assertRedirect();
        $this->assertDatabaseCount('human_examinations', 1);
    }

    public function test_import_limits_and_export_formats_are_validated(): void
    {
        $this->authorize();
        config()->set('human-examinations.transfer.max_observations', 1);
        $this->upload($this->document([['number' => 1, 'type' => 'Scar'], ['number' => 2, 'type' => 'Scar']]))->assertUnprocessable();
        config()->set('human-examinations.transfer.max_file_kilobytes', 1);
        $this->upload(array_replace($this->document(), ['examination' => ['notes' => str_repeat('x', 2048)]]))->assertUnprocessable()->assertJsonValidationErrors('file');
        $exam = HumanExamination::create([]);
        $this->getJson($this->exportUrl($exam).'?format=xml')->assertUnprocessable();
        $exam->delete();
        $this->getJson($this->exportUrl($exam))->assertNotFound();
    }

    public function test_import_rolls_back_the_parent_and_children_when_persistence_fails(): void
    {
        Event::listen('eloquent.creating: '.ExaminationObservation::class, function (ExaminationObservation $observation): void {
            if ($observation->number === 2) {
                throw new RuntimeException('Simulated persistence failure');
            }
        });

        try {
            app(ImportExamination::class)->handle($this->document([['number' => 1, 'type' => 'Scar'], ['number' => 2, 'type' => 'Scar']]));
            $this->fail('Import must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated persistence failure', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.ExaminationObservation::class);
        }
        $this->assertDatabaseCount('human_examinations', 0);
        $this->assertDatabaseCount('human_examination_observations', 0);
    }

    public function test_configured_types_actions_and_custom_sketches_are_supported(): void
    {
        config()->set('human-examinations.observation_type', TransferObservationType::class);
        config()->set('human-examinations.actions.import_examination', CustomImportExamination::class);
        config()->set('human-examinations.actions.export_examination', CustomExportExamination::class);
        $exam = app(ImportExamination::class)->handle($this->document([['number' => 8, 'type' => 'Custom finding', 'sketch_id' => 'host-custom-sketch', 'x' => 0.2, 'y' => 0.4]]));
        $this->assertSame('Custom import', $exam->reference);
        $document = app(ExportExamination::class)->handle($exam);
        $this->assertSame('Custom export', $document['examination']['notes']);
        $this->assertSame('host-custom-sketch', $document['observations'][0]['sketch_id']);
        $this->assertSame('Custom finding', $document['observations'][0]['type']);
    }

    private function authorize(int $id = 1): void
    {
        $this->actingAs((new User)->forceFill(['id' => $id]));
        Gate::policy(HumanExamination::class, TransferExaminationPolicy::class);
    }

    private function exportUrl(HumanExamination $exam): string
    {
        return route('transfer.examinations.export', $exam);
    }

    public function test_utf8_bom_files_are_supported_and_non_documents_are_rejected(): void
    {
        $this->authorize();
        $url = route('transfer.examinations.import');
        foreach (['null', '[]', '"text"'] as $contents) {
            $this->postJson($url, ['file' => UploadedFile::fake()->createWithContent('invalid.json', $contents)])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->post($url, ['file' => UploadedFile::fake()->createWithContent('utf8.json', "\xEF\xBB\xBF".json_encode($this->document(), JSON_THROW_ON_ERROR))])->assertRedirect(route('host.examinations.show', 1));
        $this->assertDatabaseCount('human_examinations', 1);
    }

    private function upload(array $document): TestResponse
    {
        return $this->post(route('transfer.examinations.import'), ['file' => UploadedFile::fake()->createWithContent('examination.json', json_encode($document, JSON_THROW_ON_ERROR))], ['Accept' => 'application/json']);
    }

    private function document(array $observations = [['number' => 1, 'type' => 'Scar']]): array
    {
        return ['format' => 'spawaskar/human-examinations', 'version' => 1, 'examination' => ['reference' => 'Imported', 'examiner' => null, 'date' => null, 'notes' => null], 'observations' => $observations];
    }
}

class TransferExaminationPolicy
{
    public function view(User $user, HumanExamination $examination): bool
    {
        return $examination->reference !== 'PRIVATE';
    }

    public function create(User $user): bool
    {
        return $user->getAuthIdentifier() === 1;
    }
}

enum TransferObservationType: string
{
    case Unspecified = 'Unspecified';
    case Finding = 'Custom finding';
}

class CustomImportExamination extends ImportExamination
{
    public function handle(array $document): HumanExamination
    {
        $document['examination']['reference'] = 'Custom import';

        return parent::handle($document);
    }
}

class CustomExportExamination extends ExportExamination
{
    public function handle(HumanExamination $examination): array
    {
        $document = parent::handle($examination);
        $document['examination']['notes'] = 'Custom export';

        return $document;
    }
}

<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Tests\Feature;

use HumanExaminations\HumanExaminations\Enums\BodyRegion;
use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Workbench\App\Policies\ExaminationPolicy;

class ConfiguredWorkbenchTest extends TestCase
{
    use WithWorkbench;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', str_repeat('w', 32));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('human-examinations.routes.prefix', 'demo/forensics');
        $app['config']->set('human-examinations.routes.name_prefix', 'demo.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->artisan('migrate', [
            '--path' => [
                realpath(__DIR__.'/../../database/migrations'),
                // The demo's own tables, such as `people`.
                realpath(__DIR__.'/../../workbench/database/migrations'),
            ],
            '--realpath' => true,
        ])->assertSuccessful();
    }

    public function test_the_blank_workspace_receives_the_configured_store_url(): void
    {
        $this->get('/')->assertOk()->assertInertia(function (AssertableInertia $page): void {
            $page->component('Workspace', false)
                ->where('examinationRoutes.store', route('demo.examinations.store'))
                ->where('examinationRoutes.import', route('demo.examinations.import'))
                ->where('examinationRoutes.export', [])
                ->where('transferLimits.maxFileBytes', 10485760)
                ->where('transferLimits.maxObservations', 10000)
                ->where('examinationRoutes.update', null);
        });
    }

    #[DefineEnvironment('configureWorkspaceFeatures')]
    public function test_workspace_settings_are_forwarded_and_disabled_transfer_routes_are_omitted(): void
    {
        $this->assertFalse(Route::has('demo.examinations.import'));
        $this->assertTrue(Route::has('demo.examinations.export'));
        $this->postJson('/demo/forensics/examinations/import')->assertStatus(405);

        $examination = Examination::create([]);
        foreach (['/', route('workbench.examinations.show', $examination)] as $url) {
            $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workspace', false)
                ->where('examinationRoutes.import', null)
                ->where('features.undo_redo', false)
                ->where('features.import', false)
                ->where('workspaceDefaults.default_view', 'head')
                ->where('workspaceDefaults.default_mode', 'density')
                ->where('workspaceDefaults.diagram_height', 640)
                ->where('markerColors.Scar.fill', '#315a81'));
        }
    }

    protected function configureWorkspaceFeatures($app): void
    {
        $app['config']->set('human-examinations.features', ['import' => false, 'undo_redo' => false]);
        $app['config']->set('human-examinations.marker_colors', ['Scar' => ['fill' => '#315a81']]);
        $app['config']->set('human-examinations.workspace', [
            'default_view' => 'head',
            'default_mode' => 'density',
            'diagram_height' => 640,
        ]);
    }

    public function test_an_examination_workspace_receives_the_configured_update_url(): void
    {
        $examination = Examination::create(['reference' => 'DEMO']);

        $this->get(route('workbench.examinations.show', $examination))
            ->assertOk()->assertInertia(function (AssertableInertia $page) use ($examination): void {
                $page->component('Workspace', false)
                    ->where('examinationRoutes.store', route('demo.examinations.store'))
                    ->where('examinationRoutes.export.json', route('demo.examinations.export', ['examination' => $examination, 'format' => 'json']))
                    ->where('examinationRoutes.export.csv', route('demo.examinations.export', ['examination' => $examination, 'format' => 'csv']))
                    ->where('examinationRoutes.update', route('demo.examinations.update', $examination));
            });
    }

    public function test_the_workbench_explicitly_allows_guest_create_and_update_for_its_demo(): void
    {
        $this->assertGuest();
        $this->post(route('demo.examinations.store'), ['reference' => 'DEMO'])
            ->assertRedirect(route('workbench.examinations.show', 1));

        $examination = Examination::query()->sole();
        $this->from(route('workbench.examinations.show', $examination))
            ->patch(route('demo.examinations.update', $examination), ['notes' => 'Saved in the demo'])
            ->assertRedirect(route('workbench.examinations.show', $examination));
        $this->assertSame('Saved in the demo', $examination->fresh()->notes);
    }

    public function test_the_workbench_imports_into_a_new_workspace_without_overwriting_the_source(): void
    {
        $original = Examination::create(['reference' => 'Source']);
        $original->observations()->create(['number' => 6, 'type' => 'Scar', 'region' => 'Manual region', 'sketch_id' => 'body-back', 'x' => 0.4, 'y' => 0.3]);
        $json = $this->get(route('demo.examinations.export', $original))->assertOk()->streamedContent();
        $this->post(route('demo.examinations.import'), ['file' => UploadedFile::fake()->createWithContent('export.json', $json)])
            ->assertRedirect(route('workbench.examinations.show', 2));
        $this->assertDatabaseCount('human_examinations', 2);
        $this->assertSame('Source', $original->fresh()->reference);
        $this->get(route('workbench.examinations.show', 2))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Workspace', false)->where('examination.id', 2)->has('observations', 1)
            ->where('observations.0.number', 6)->where('observations.0.region', 'Manual region')
            ->where('observations.0.sketch_id', 'body-back')->where('observations.0.x', '0.400000'));
    }

    public function test_the_workspace_loads_only_its_active_observations_in_number_order(): void
    {
        $exam = Examination::create(['date' => '2026-11-19']);
        $later = $exam->observations()->create(['number' => 8]);
        $earlier = $exam->observations()->create(['number' => 2, 'type' => 'Scar', 'length_mm' => 12.5]);
        $exam->observations()->create(['number' => 3])->delete();
        Examination::create([])->observations()->create(['number' => 1]);

        $this->get(route('workbench.examinations.show', $exam))->assertOk()
            ->assertInertia(function (AssertableInertia $page) use ($exam, $earlier, $later): void {
                $page->component('Workspace', false)->has('observations', 2)
                    ->where('examination.date', '2026-11-19')
                    ->missing('examination.created_at')
                    ->where('observations.0.id', $earlier->id)
                    ->where('observations.0.examination_id', $exam->id)
                    ->where('observations.0.type', 'Scar')
                    ->where('observations.0.length_mm', '12.50')
                    ->missing('observations.0.updated_at')
                    ->where('observations.1.id', $later->id)
                    ->where('observationRoutes.store', route('demo.observations.store', $exam))
                    ->where('observationRoutes.update.'.$earlier->id, route('demo.observations.update', [$exam, $earlier]))
                    ->where('observationRoutes.destroy.'.$earlier->id, route('demo.observations.destroy', [$exam, $earlier]));
            });
    }

    public function test_deleted_observations_can_be_restored_from_the_workbench_with_the_same_identity(): void
    {
        $exam = Examination::create([]);
        $workspace = route('workbench.examinations.show', $exam);
        $observation = $exam->observations()->create(['number' => 5, 'type' => 'Scar', 'region' => 'Manual location', 'sketch_id' => 'body-back', 'x' => 0.4, 'y' => 0.3]);
        $id = $observation->id;
        $this->from($workspace)->delete(route('demo.observations.destroy', [$exam, $observation]))->assertRedirect($workspace);
        $restore = route('demo.observations.restore', [$exam, $observation]);
        $this->get($workspace)->assertInertia(fn (AssertableInertia $page) => $page
            ->has('observations', 0)->where('observationRoutes.restore.'.$id, $restore));
        $this->from($workspace)->post($restore)->assertRedirect($workspace);
        $this->get($workspace)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('observations.0.id', $id)->where('observations.0.number', 5)
            ->where('observations.0.region', 'Manual location')->where('observations.0.sketch_id', 'body-back')
            ->where('observations.0.x', '0.400000')->where('observations.0.y', '0.300000'));
        $this->from($workspace)->delete(route('demo.observations.destroy', [$exam, $id]))->assertRedirect($workspace);
        $this->assertSoftDeleted('human_examination_observations', ['id' => $id]);
        $this->assertDatabaseCount('human_examination_observations', 1);
    }

    public function test_observation_creates_edits_and_deletes_survive_workspace_reloads(): void
    {
        $exam = Examination::create([]);
        $workspace = route('workbench.examinations.show', $exam);
        $this->from($workspace)->post(route('demo.observations.store', $exam), ['label' => 'Saved'])
            ->assertRedirect($workspace);
        $observation = $exam->observations()->sole();
        $this->get($workspace)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('observations.0.label', 'Saved'));
        $this->from($workspace)->patch(route('demo.observations.update', [$exam, $observation]), ['label' => 'Edited'])
            ->assertRedirect($workspace);
        $this->get($workspace)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('observations.0.label', 'Edited'));
        $this->from($workspace)->delete(route('demo.observations.destroy', [$exam, $observation]))
            ->assertRedirect($workspace);
        $this->get($workspace)->assertInertia(fn (AssertableInertia $page) => $page
            ->has('observations', 0));
    }

    public function test_inertia_shares_observation_validation_errors_without_creating_records(): void
    {
        $exam = Examination::create([]);
        $workspace = route('workbench.examinations.show', $exam);
        $this->from($workspace)->post(route('demo.observations.store', $exam), ['length_mm' => -1])
            ->assertRedirect($workspace)->assertSessionHasErrors('length_mm');
        $this->get($workspace)->assertInertia(fn (AssertableInertia $page) => $page
            ->has('errors.length_mm')->has('observations', 0));
        $this->assertDatabaseCount('human_examination_observations', 0);
    }

    public function test_workspace_reads_require_examination_view_permission(): void
    {
        Gate::policy(Examination::class, DeniedWorkbenchExaminationPolicy::class);
        $exam = Examination::create([]);
        $exam->observations()->create(['number' => 1, 'label' => 'Private finding']);
        $this->getJson(route('workbench.examinations.show', $exam))->assertForbidden();
    }

    public function test_standard_custom_and_cleared_regions_survive_workspace_reloads(): void
    {
        $exam = Examination::create([]);
        $workspace = route('workbench.examinations.show', $exam);
        $this->from($workspace)->post(route('demo.observations.store', $exam), [
            'type' => 'Scar', 'region' => BodyRegion::RightUpperArm->value,
            'sketch_id' => 'body-front', 'x' => 0.23, 'y' => 0.28,
        ])->assertRedirect($workspace);
        $observation = $exam->observations()->sole();

        foreach (['Right upper arm', 'Buttocks', 'Posterior right upper arm, near elbow', null] as $region) {
            $this->from($workspace)->patch(route('demo.observations.update', [$exam, $observation]), ['region' => $region])
                ->assertRedirect($workspace);
            $this->get($workspace)->assertInertia(fn (AssertableInertia $page) => $page
                ->where('observations.0.region', $region)
                ->where('observations.0.sketch_id', 'body-front')
                ->where('observations.0.x', '0.230000')->where('observations.0.y', '0.280000'));
        }
    }

    public function test_specific_body_regions_reload_with_their_marker_locations(): void
    {
        $exam = Examination::create([]);
        $workspace = route('workbench.examinations.show', $exam);
        $examples = [
            [BodyRegion::LeftButtock, 'body-back', 0.43, 0.46],
            [BodyRegion::RightRingFinger, 'hand-right-dorsal', 0.26, 0.82],
            [BodyRegion::LeftBigToe, 'foot-left-dorsal', 0.32, 0.92],
            [BodyRegion::Groin, 'body-front', 0.5, 0.48],
            [BodyRegion::LeftInnerThigh, 'body-back', 0.425, 0.6],
            [BodyRegion::RightNipple, 'body-front', 0.365033, 0.252963],
        ];

        foreach ($examples as $index => [$region, $sketch, $x, $y]) {
            $this->from($workspace)->post(route('demo.observations.store', $exam), [
                'type' => 'Scar', 'region' => $region->value,
                'sketch_id' => $sketch, 'x' => $x, 'y' => $y,
            ])->assertRedirect($workspace);
            $this->get($workspace)->assertInertia(fn (AssertableInertia $page) => $page
                ->where('observations.'.$index.'.region', $region->value)
                ->where('observations.'.$index.'.sketch_id', $sketch)
                ->where('observations.'.$index.'.x', number_format($x, 6, '.', ''))
                ->where('observations.'.$index.'.y', number_format($y, 6, '.', '')));
        }

        $this->assertSame(count($examples), $exam->observations()->count());
    }

    public function test_marker_creation_and_initial_placement_reload_with_their_observations(): void
    {
        $exam = Examination::create([]);
        $workspace = route('workbench.examinations.show', $exam);
        $this->from($workspace)->post(route('demo.observations.store', $exam), [
            'type' => 'Scar', 'label' => 'Back scar',
            'sketch_id' => 'body-back', 'x' => 0.25, 'y' => 0.75,
        ])->assertRedirect($workspace);

        $unplaced = $exam->observations()->create(['number' => 2, 'type' => 'Tattoo', 'label' => 'Existing tattoo']);
        $this->from($workspace)->patch(route('demo.observations.update', [$exam, $unplaced]), [
            'sketch_id' => 'hand-left-palmar', 'x' => 0, 'y' => 1,
        ])->assertRedirect($workspace);
        $this->get($workspace)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('observations.0.label', 'Back scar')
            ->where('observations.0.sketch_id', 'body-back')
            ->where('observations.0.x', '0.250000')->where('observations.0.y', '0.750000')
            ->where('observations.1.label', 'Existing tattoo')
            ->where('observations.1.sketch_id', 'hand-left-palmar')
            ->where('observations.1.x', '0.000000')->where('observations.1.y', '1.000000'));
        $this->assertSame(2, $unplaced->fresh()->number);
        $this->assertSame('Tattoo', $unplaced->fresh()->type->value);
    }
}

class DeniedWorkbenchExaminationPolicy extends ExaminationPolicy
{
    public function view(?Authenticatable $user, Examination $examination): bool
    {
        return false;
    }
}

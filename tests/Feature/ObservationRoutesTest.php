<?php

declare(strict_types=1);

namespace HumanExaminations\HumanExaminations\Tests\Feature;

use HumanExaminations\HumanExaminations\Actions\CreateObservation;
use HumanExaminations\HumanExaminations\Actions\DeleteObservation;
use HumanExaminations\HumanExaminations\Actions\UpdateObservation;
use HumanExaminations\HumanExaminations\Models\Examination;
use HumanExaminations\HumanExaminations\Models\Observation;
use HumanExaminations\HumanExaminations\Tests\TestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\Attributes\DefineEnvironment;
use PHPUnit\Framework\Attributes\DataProvider;

class ObservationRoutesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', str_repeat('o', 32));
        $app['config']->set('session.driver', 'array');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('human-examinations.routes.enabled', true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', [
            '--path' => realpath(__DIR__.'/../../database/migrations'),
            '--realpath' => true,
        ])->assertSuccessful();
    }

    public function test_guests_cannot_write_observations(): void
    {
        $exam = Examination::create([]);
        $observation = $exam->observations()->create(['number' => 1]);

        $this->postJson($this->url($exam))->assertUnauthorized();
        $this->patchJson($this->url($exam, $observation))->assertUnauthorized();
        $this->deleteJson($this->url($exam, $observation))->assertUnauthorized();
        $this->assertDatabaseCount('human_examination_observations', 1);
        $this->assertNull($observation->fresh()->deleted_at);
    }

    public function test_authenticated_users_without_a_host_policy_cannot_write_observations(): void
    {
        $this->actingAs((new User)->forceFill(['id' => 1]));
        $exam = Examination::create([]);
        $observation = $exam->observations()->create(['number' => 1]);

        $this->postJson($this->url($exam))->assertForbidden();
        $this->patchJson($this->url($exam, $observation))->assertForbidden();
        $this->deleteJson($this->url($exam, $observation))->assertForbidden();
        $this->assertNull($observation->fresh()->deleted_at);
    }

    public function test_parent_authorization_happens_before_validation_and_mutation(): void
    {
        $this->allowWrites();
        $exam = Examination::create(['reference' => 'PRIVATE']);
        $observation = $exam->observations()->create(['number' => 1, 'label' => 'Before']);

        $this->postJson($this->url($exam), ['type' => ['invalid']])->assertForbidden();
        $this->patchJson($this->url($exam, $observation), ['type' => ['invalid']])->assertForbidden();
        $this->deleteJson($this->url($exam, $observation))->assertForbidden();
        $this->assertSame('Before', $observation->fresh()->label);
        $this->assertNull($observation->fresh()->deleted_at);
        $this->assertDatabaseCount('human_examination_observations', 1);
    }

    public function test_authorized_crud_persists_fields_and_soft_deletes_records(): void
    {
        $this->allowWrites();
        $exam = Examination::create([]);
        $this->from('/host')->post($this->url($exam), [
            'type' => 'Scar', 'label' => 'Linear scar', 'region' => 'Right arm',
            'description' => 'Healed', 'length_mm' => '32.50', 'width_mm' => 0,
            'sketch_id' => 'body-front', 'x' => 0.31, 'y' => 0.32,
        ])->assertRedirect('/host');

        $observation = $exam->observations()->sole();
        $this->assertSame(1, $observation->number);
        $this->assertSame('Scar', $observation->type->value);
        $this->assertSame('32.50', $observation->length_mm);
        $this->assertSame('0.00', $observation->width_mm);
        $this->assertSame('0.310000', $observation->x);
        $this->assertSame('Healed', $observation->description);

        $this->from('/host')->patch($this->url($exam, $observation), ['label' => 'Updated'])
            ->assertRedirect('/host');
        $this->assertSame('Updated', $observation->fresh()->label);
        $this->assertSame('Healed', $observation->fresh()->description);
        $this->assertSame('body-front', $observation->fresh()->sketch_id);

        $this->from('/host')->delete($this->url($exam, $observation))->assertRedirect('/host');
        $this->assertSoftDeleted('human_examination_observations', ['id' => $observation->id]);
        $this->assertCount(0, $exam->observations()->get());
    }

    public function test_numbers_are_assigned_per_examination_and_never_reuse_soft_deleted_numbers(): void
    {
        $this->allowWrites();
        $first = Examination::create([]);
        $second = Examination::create([]);
        $deleted = $first->observations()->create(['number' => 7]);
        $deleted->delete();

        $this->postJson($this->url($first))->assertRedirect();
        $this->postJson($this->url($second))->assertRedirect();
        $this->assertSame(8, $first->observations()->sole()->number);
        $this->assertSame(1, $second->observations()->sole()->number);
        $this->assertSame('Unspecified', $second->observations()->sole()->type->value);
    }

    public function test_child_ids_are_scoped_to_the_parent_examination(): void
    {
        $this->allowWrites();
        $first = Examination::create([]);
        $second = Examination::create([]);
        $observation = $second->observations()->create(['number' => 1, 'label' => 'Before']);

        $this->patchJson($this->url($first, $observation), ['label' => 'Changed'])->assertNotFound();
        $this->deleteJson($this->url($first, $observation))->assertNotFound();
        $this->assertSame('Before', $observation->fresh()->label);
        $this->assertNull($observation->fresh()->deleted_at);
    }

    public function test_soft_deleted_parents_and_children_are_not_writable(): void
    {
        $this->allowWrites();
        $exam = Examination::create([]);
        $observation = $exam->observations()->create(['number' => 1]);
        $observation->delete();
        $this->patchJson($this->url($exam, $observation))->assertNotFound();
        $this->deleteJson($this->url($exam, $observation))->assertNotFound();
        $observation->restore();
        $exam->delete();
        $this->postJson($this->url($exam))->assertNotFound();
        $this->patchJson($this->url($exam, $observation))->assertNotFound();
        $this->deleteJson($this->url($exam, $observation))->assertNotFound();
        $this->assertNull($observation->fresh()->deleted_at);
    }

    public function test_identity_and_ownership_cannot_be_assigned_from_http_payloads(): void
    {
        $this->allowWrites();
        $exam = Examination::create([]);
        $observation = $exam->observations()->create(['number' => 1]);
        $payload = ['id' => 8, 'number' => 5, 'examination_id' => 9];

        $this->postJson($this->url($exam), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['id', 'number', 'examination_id']);
        $this->patchJson($this->url($exam, $observation), $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['id', 'number', 'examination_id']);
        $this->assertSame(1, $observation->fresh()->number);
        $this->assertSame($exam->id, $observation->fresh()->examination_id);
    }

    public function test_partial_location_updates_validate_the_merged_location(): void
    {
        $this->allowWrites();
        $exam = Examination::create([]);
        $observation = $exam->observations()->create([
            'number' => 1, 'sketch_id' => 'body-front', 'x' => 0.5, 'y' => 0.5,
        ]);
        $this->patchJson($this->url($exam, $observation), ['x' => 0])->assertRedirect();
        $this->assertSame('0.000000', $observation->fresh()->x);
        $this->patchJson($this->url($exam, $observation), ['sketch_id' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('sketch_id');
        $this->assertSame('body-front', $observation->fresh()->sketch_id);
        $this->patchJson($this->url($exam, $observation), ['sketch_id' => null, 'x' => null, 'y' => null])
            ->assertRedirect();
        $this->assertNull($observation->fresh()->sketch_id);
        $this->assertNull($observation->fresh()->x);
        $this->assertNull($observation->fresh()->y);
    }

    #[DataProvider('invalidPayloads')]
    public function test_invalid_payloads_return_validation_errors_without_changing_records(array $payload, string $field): void
    {
        $this->allowWrites();
        $exam = Examination::create([]);
        $observation = $exam->observations()->create(['number' => 1, 'label' => 'Before']);

        $this->postJson($this->url($exam), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->patchJson($this->url($exam, $observation), $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertSame('Before', $observation->fresh()->label);
        $this->assertDatabaseCount('human_examination_observations', 1);
    }

    public static function invalidPayloads(): array
    {
        return [
            'unknown type' => [['type' => 'invalid'], 'type'],
            'null type' => [['type' => null], 'type'],
            'null number' => [['number' => null], 'number'],
            'null owner' => [['examination_id' => null], 'examination_id'],
            'array label' => [['label' => ['invalid']], 'label'],
            'long region' => [['region' => str_repeat('r', 256)], 'region'],
            'negative length' => [['length_mm' => -0.001], 'length_mm'],
            'overflow width' => [['width_mm' => 100000000], 'width_mm'],
            'invalid measurement' => [['length_mm' => 'invalid'], 'length_mm'],
            'incomplete location' => [['sketch_id' => 'body-front', 'x' => 0.5], 'y'],
            'orphan coordinates' => [['x' => 0.5, 'y' => 0.5], 'sketch_id'],
            'out of range before rounding' => [['sketch_id' => 'body-front', 'x' => 1.0000001, 'y' => 0], 'x'],
            'negative coordinate' => [['sketch_id' => 'body-front', 'x' => 0, 'y' => -0.1], 'y'],
        ];
    }

    #[DefineEnvironment('customRoutes')]
    public function test_custom_route_configuration_retains_scoped_bindings_without_web_middleware(): void
    {
        $this->allowWrites();
        $exam = Examination::create([]);
        $this->assertFalse(Route::has('human-examinations.observations.store'));
        $this->postJson(route('custom.observations.store', $exam), ['label' => 'Custom'])->assertRedirect();
        $observation = $exam->observations()->sole();
        $this->patchJson(route('custom.observations.update', [$exam, $observation]), ['label' => 'Updated'])->assertRedirect();
        $this->assertSame('Updated', $observation->fresh()->label);
        $this->deleteJson(route('custom.observations.destroy', [$exam, $observation]))->assertRedirect();
        $this->assertSoftDeleted($observation);
    }

    protected function customRoutes($app): void
    {
        $app['config']->set('human-examinations.routes.prefix', 'tools/forensics');
        $app['config']->set('human-examinations.routes.name_prefix', 'custom.');
        $app['config']->set('human-examinations.routes.middleware', []);
    }

    public function test_the_configured_observation_enum_is_used_for_http_validation(): void
    {
        $this->allowWrites();
        config()->set('human-examinations.observation_type', RoutedObservationType::class);
        $exam = Examination::create([]);
        $this->postJson($this->url($exam), ['type' => 'Custom finding'])->assertRedirect();
        $observation = $exam->observations()->sole();
        $this->assertSame(RoutedObservationType::Finding, $observation->type);
        $this->patchJson($this->url($exam, $observation), ['type' => 'Scar'])
            ->assertUnprocessable()->assertJsonValidationErrors('type');
        $this->assertSame(RoutedObservationType::Finding, $observation->fresh()->type);
    }

    #[DefineEnvironment('customRoutes')]
    public function test_removing_auth_middleware_still_requires_parent_authorization(): void
    {
        Gate::policy(Examination::class, ObservationParentPolicy::class);
        $exam = Examination::create([]);
        $observation = $exam->observations()->create(['number' => 1]);
        $this->postJson(route('custom.observations.store', $exam))->assertForbidden();
        $this->patchJson(route('custom.observations.update', [$exam, $observation]))->assertForbidden();
        $this->deleteJson(route('custom.observations.destroy', [$exam, $observation]))->assertForbidden();
        $this->assertNull($observation->fresh()->deleted_at);
    }

    public function test_controllers_resolve_the_host_observation_actions(): void
    {
        $this->allowWrites();
        config()->set('human-examinations.actions.create_observation', RoutedCreateObservation::class);
        config()->set('human-examinations.actions.update_observation', RoutedUpdateObservation::class);
        config()->set('human-examinations.actions.delete_observation', RoutedDeleteObservation::class);
        $exam = Examination::create([]);
        $this->postJson($this->url($exam))->assertRedirect();
        $observation = $exam->observations()->sole();
        $this->assertSame('Custom create', $observation->label);
        $this->patchJson($this->url($exam, $observation))->assertRedirect();
        $this->assertSame('Custom update', $observation->fresh()->label);
        $this->deleteJson($this->url($exam, $observation))->assertRedirect();
        $this->assertSame('Custom delete', $observation->fresh()->label);
        $this->assertSoftDeleted($observation);
    }

    public function test_default_actions_preserve_observation_identity_and_parent(): void
    {
        $exam = Examination::create([]);
        $other = Examination::create([]);
        $observation = app(CreateObservation::class)->handle($exam, [
            'id' => 99, 'number' => 40, 'examination_id' => $other->id,
        ]);
        $this->assertSame(1, $observation->number);
        $this->assertSame($exam->id, $observation->examination_id);
        $this->assertNotSame(99, $observation->id);
        $originalId = $observation->id;
        app(UpdateObservation::class)->handle($observation, [
            'id' => 99, 'number' => 40, 'examination_id' => $other->id, 'label' => 'Updated',
        ]);
        $this->assertSame($originalId, $observation->fresh()->id);
        $this->assertSame(1, $observation->fresh()->number);
        $this->assertSame($exam->id, $observation->fresh()->examination_id);
        $this->assertSame('Updated', $observation->fresh()->label);
    }

    private function allowWrites(): void
    {
        Gate::policy(Examination::class, ObservationParentPolicy::class);
        $this->actingAs((new User)->forceFill(['id' => 1]));
    }

    private function url(Examination $exam, ?Observation $observation = null): string
    {
        return '/human-examinations/examinations/'.$exam->id.'/observations'
            .($observation === null ? '' : '/'.$observation->id);
    }
}

class ObservationParentPolicy
{
    public function update(User $user, Examination $examination): bool
    {
        return $user->getAuthIdentifier() === 1 && $examination->reference !== 'PRIVATE';
    }
}

enum RoutedObservationType: string
{
    case Unspecified = 'Unspecified';
    case Finding = 'Custom finding';
}

class RoutedCreateObservation extends CreateObservation
{
    public function handle(Examination $examination, array $attributes = []): Observation
    {
        return parent::handle($examination, [...$attributes, 'label' => 'Custom create']);
    }
}

class RoutedUpdateObservation extends UpdateObservation
{
    public function handle(Observation $observation, array $attributes): Observation
    {
        return parent::handle($observation, [...$attributes, 'label' => 'Custom update']);
    }
}

class RoutedDeleteObservation extends DeleteObservation
{
    public function handle(Observation $observation): void
    {
        $observation->update(['label' => 'Custom delete']);
        parent::handle($observation);
    }
}

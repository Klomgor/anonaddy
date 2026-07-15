<?php

namespace Tests\Feature\Api;

use App\Models\Alias;
use App\Models\Label;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LabelsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        parent::setUpSanctum();
    }

    #[Test]
    public function user_can_get_all_labels(): void
    {
        Label::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->json('GET', '/api/v1/labels');

        $response->assertSuccessful();
        $this->assertCount(2, $response->json()['data']);
        $this->assertArrayHasKey('aliases_count', $response->json()['data'][0]);
    }

    #[Test]
    public function user_can_filter_labels_by_search(): void
    {
        Label::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'shopping',
        ]);
        Label::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'work',
        ]);

        $response = $this->json('GET', '/api/v1/labels?filter[search]=shop');

        $response->assertSuccessful();
        $this->assertCount(1, $response->json()['data']);
        $this->assertEquals('shopping', $response->json()['data'][0]['name']);
    }

    #[Test]
    public function user_can_get_individual_label(): void
    {
        $label = Label::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->json('GET', '/api/v1/labels/'.$label->id);

        $response->assertSuccessful();
        $this->assertEquals($label->name, $response->json()['data']['name']);
    }

    #[Test]
    public function user_can_create_label(): void
    {
        $response = $this->json('POST', '/api/v1/labels', [
            'name' => 'Shopping',
            'colour' => '#06b6d4',
        ]);

        $response->assertCreated();
        $this->assertEquals('shopping', $response->json()['data']['name']);
        $this->assertEquals('#06b6d4', $response->json()['data']['colour']);
        $this->assertDatabaseHas('labels', [
            'user_id' => $this->user->id,
            'name' => 'shopping',
            'colour' => '#06b6d4',
        ]);
    }

    #[Test]
    public function user_cannot_create_label_with_invalid_colour(): void
    {
        $response = $this->json('POST', '/api/v1/labels', [
            'name' => 'Shopping',
            'colour' => '#ffffff',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function user_cannot_create_duplicate_label_name(): void
    {
        Label::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'shopping',
        ]);

        $response = $this->json('POST', '/api/v1/labels', [
            'name' => 'Shopping',
            'colour' => '#22c55e',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function user_can_update_label(): void
    {
        $label = Label::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'shopping',
            'colour' => '#06b6d4',
        ]);

        $response = $this->json('PATCH', '/api/v1/labels/'.$label->id, [
            'name' => 'Work',
            'colour' => '#22c55e',
        ]);

        $response->assertSuccessful();
        $this->assertEquals('work', $response->json()['data']['name']);
        $this->assertEquals('#22c55e', $response->json()['data']['colour']);
    }

    #[Test]
    public function user_can_delete_label(): void
    {
        $label = Label::factory()->create([
            'user_id' => $this->user->id,
        ]);
        $alias = Alias::factory()->create(['user_id' => $this->user->id]);
        $alias->labels()->attach($label->id);

        $response = $this->json('DELETE', '/api/v1/labels/'.$label->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('labels', ['id' => $label->id]);
        $this->assertDatabaseMissing('alias_label', [
            'alias_id' => $alias->id,
            'label_id' => $label->id,
        ]);
    }

    #[Test]
    public function user_cannot_access_another_users_label(): void
    {
        $otherUser = $this->createUser('otheruser');
        $label = Label::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->json('GET', '/api/v1/labels/'.$label->id)->assertNotFound();
        $this->json('PATCH', '/api/v1/labels/'.$label->id, [
            'name' => 'Hacked',
            'colour' => '#06b6d4',
        ])->assertNotFound();
        $this->json('DELETE', '/api/v1/labels/'.$label->id)->assertNotFound();
    }

    #[Test]
    public function user_cannot_exceed_label_limit(): void
    {
        Label::factory()->count(Label::LABEL_LIMIT)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->json('POST', '/api/v1/labels', [
            'name' => 'one-too-many',
            'colour' => '#06b6d4',
        ]);

        $response->assertForbidden();
    }
}

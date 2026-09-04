<?php

namespace Tests\Feature;

use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportStoreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Supplier::factory()->create(['external_id' => 'supplier-a']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'supplier' => 'supplier-a',
            'external_id' => 'import-2026-09-01-001',
            'sent_at' => '2026-09-01T10:00:00Z',
            'offers' => [[
                'external_id' => 'offer-a-10001',
                'property' => ['code' => 'BCN-0001', 'name' => 'Apt', 'city' => 'Barcelona'],
                'check_in' => '2026-10-10',
                'check_out' => '2026-10-15',
                'max_guests' => 4,
                'price' => 72500,
                'currency' => 'EUR',
                'available_units' => 2,
                'expires_at' => '2026-09-10T23:59:59Z',
            ]],
        ], $overrides);
    }

    public function test_it_accepts_valid_import_and_returns_202(): void
    {
        Queue::fake();

        $this->postJson('/api/imports', $this->payload())
            ->assertStatus(202)
            ->assertJsonPath('data.status', fn ($status) => $status !== null);

        $this->assertDatabaseHas('imports', [
            'external_id' => 'import-2026-09-01-001',
        ]);
    }

    public function test_it_dispatches_processing_job(): void
    {
        Queue::fake();

        $this->postJson('/api/imports', $this->payload());

        Queue::assertPushed(ProcessImport::class);
    }

    public function test_it_rejects_unknown_supplier(): void
    {
        $this->postJson('/api/imports', $this->payload(['supplier' => 'unknown']))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('supplier');
    }

    public function test_it_validates_request_structure(): void
    {
        $this->postJson('/api/imports', ['supplier' => 'supplier-a'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['external_id', 'sent_at', 'offers']);
    }

    public function test_it_validates_nested_offer_fields(): void
    {
        $payload = $this->payload();
        unset($payload['offers'][0]['price']);

        $this->postJson('/api/imports', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['offers.0.price']);
    }

    public function test_resending_same_import_is_idempotent(): void
    {
        Queue::fake();

        $this->postJson('/api/imports', $this->payload())->assertStatus(202);
        $this->postJson('/api/imports', $this->payload())->assertStatus(202);

        $this->assertSame(1, Import::where('external_id', 'import-2026-09-01-001')->count());
        Queue::assertPushed(ProcessImport::class, 1);
    }

    public function test_same_external_id_from_different_suppliers_is_allowed(): void
    {
        Queue::fake();
        Supplier::factory()->create(['external_id' => 'supplier-b']);

        $this->postJson('/api/imports', $this->payload(['supplier' => 'supplier-a']))->assertStatus(202);
        $this->postJson('/api/imports', $this->payload(['supplier' => 'supplier-b']))->assertStatus(202);

        $this->assertSame(2, Import::where('external_id', 'import-2026-09-01-001')->count());
    }
}

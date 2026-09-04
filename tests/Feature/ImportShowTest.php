<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_import_state(): void
    {
        $supplier = Supplier::factory()->create(['external_id' => 'supplier-a']);

        $import = Import::factory()->create([
            'supplier_id'    => $supplier->id,
            'external_id'    => 'import-2026-09-01-001',
            'status'         => ImportStatus::Completed,
            'total_imported' => 5,
            'error'          => null,
            'raw_offers'     => [],
        ]);

        $response = $this->getJson("/api/imports/{$import->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $import->id)
            ->assertJsonPath('data.external_id', 'import-2026-09-01-001')
            ->assertJsonPath('data.status', $import->status->asText())
            ->assertJsonPath('data.total_imported', 5)
            ->assertJsonPath('data.error', null);
    }

    public function test_it_includes_the_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'external_id' => 'supplier-a',
            'name'        => 'Supplier A',
        ]);

        $import = Import::factory()->create([
            'supplier_id' => $supplier->id,
            'raw_offers'  => [],
        ]);

        $this->getJson("/api/imports/{$import->id}")
            ->assertOk()
            ->assertJsonPath('data.supplier.external_id', 'supplier-a');
    }

    public function test_it_exposes_the_expected_structure(): void
    {
        $supplier = Supplier::factory()->create(['external_id' => 'supplier-a']);
        $import = Import::factory()->create([
            'supplier_id' => $supplier->id,
            'raw_offers'  => [],
        ]);

        $this->getJson("/api/imports/{$import->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'supplier',
                    'external_id',
                    'sent_at',
                    'status',
                    'total_offers',
                    'total_imported',
                    'error',
                    'created_at',
                    'completed_at',
                ],
            ]);
    }

    public function test_it_returns_404_for_missing_import(): void
    {
        $this->getJson('/api/imports/999999')->assertNotFound();
    }
}

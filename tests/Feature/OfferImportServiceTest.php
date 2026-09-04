<?php

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImport;
use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use App\Services\Import\OfferImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfferImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private OfferImportService $service;
    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OfferImportService::class);
        $this->supplier = Supplier::factory()->create(['external_id' => 'supplier-a']);
    }

    private function data(array $o = []): array
    {
        return array_merge([
            'external_id' => 'offer-a-10001',
            'property' => ['code' => 'BCN-0001', 'name' => 'Apt', 'city' => 'Barcelona'],
            'check_in' => '2026-10-10',
            'check_out' => '2026-10-15',
            'max_guests' => 4,
            'price' => 72500,
            'currency' => 'EUR',
            'available_units' => 2,
            'expires_at' => '2026-09-10T23:59:59Z',
        ], $o);
    }

    private function makeImport(array $rawOffers, string $externalId = 'import-1', ?int $supplierId = null): Import
    {
        return Import::factory()->create([
            'supplier_id' => $supplierId ?? $this->supplier->id,
            'external_id' => $externalId,
            'status' => ImportStatus::Created,
            'raw_offers' => $rawOffers,
        ]);
    }

    private function process(Import $import): Import
    {
        (new ProcessImport($import->id))->handle();

        return $import->refresh();
    }

    public function test_service_creates_property_and_offer(): void
    {
        $import = $this->makeImport([]);

        $this->service->populateDatabase($this->data(), $import);

        $this->assertDatabaseHas('properties', ['code' => 'BCN-0001']);
        $this->assertDatabaseHas('offers', [
            'external_id' => 'offer-a-10001',
            'supplier_id' => $this->supplier->id,
            'import_id'   => $import->id,
        ]);
    }

    public function test_service_links_offer_to_property(): void
    {
        $import = $this->makeImport([]);

        $this->service->populateDatabase($this->data(), $import);

        $property = Property::where('code', 'BCN-0001')->first();
        $offer = Offer::where('external_id', 'offer-a-10001')->first();

        $this->assertSame($property->id, $offer->property_id);
    }

    public function test_service_persists_offer_field_values(): void
    {
        $import = $this->makeImport([]);

        $this->service->populateDatabase($this->data(), $import);

        $offer = Offer::first();
        $this->assertSame(72500, (int) $offer->price);
        $this->assertSame('EUR', $offer->currency);
        $this->assertSame(4, (int) $offer->max_guests);
        $this->assertSame(2, (int) $offer->available_units);
    }

    public function test_service_reuses_property_by_code(): void
    {
        $import = $this->makeImport([]);

        $this->service->populateDatabase($this->data(['external_id' => 'o1']), $import);
        $this->service->populateDatabase($this->data(['external_id' => 'o2']), $import);

        $this->assertSame(1, Property::where('code', 'BCN-0001')->count());
        $this->assertSame(2, Offer::count());
    }

    /** @param string $path */
    #[DataProvider('invalidProvider')]
    public function test_service_rejects_invalid_offer(string $path): void
    {
        $this->expectException(ValidationException::class);

        $import = $this->makeImport([]);
        $data = $this->data();
        data_forget($data, $path);

        $this->service->populateDatabase($data, $import);
    }

    public static function invalidProvider(): array
    {
        return [
            'no external_id'     => ['external_id'],
            'no max_guests'      => ['max_guests'],
            'no price'           => ['price'],
            'no currency'        => ['currency'],
            'no available_units' => ['available_units'],
            'no property.name'   => ['property.name'],
            'no property.code'   => ['property.code'],
            'no property.city'   => ['property.city'],
        ];
    }

    public function test_same_offer_in_two_imports_is_updated_not_duplicated(): void
    {
        $import1 = $this->makeImport([], 'import-1');
        $this->service->populateDatabase($this->data(['price' => 72500]), $import1);

        $import2 = $this->makeImport([], 'import-2');
        $this->service->populateDatabase($this->data(['price' => 68000]), $import2);

        $this->assertSame(1, Offer::where('external_id', 'offer-a-10001')->count());

        $offer = Offer::first();
        $this->assertSame(68000, (int) $offer->price);
        $this->assertSame($import2->id, $offer->import_id);
    }

    public function test_same_offer_id_from_different_suppliers_stays_separate(): void
    {
        $supplierB = Supplier::factory()->create(['external_id' => 'supplier-b']);

        $importA = $this->makeImport([], 'imp-a');
        $this->service->populateDatabase($this->data(), $importA);

        $importB = $this->makeImport([], 'imp-b', $supplierB->id);
        $this->service->populateDatabase($this->data(), $importB);

        $this->assertSame(2, Offer::where('external_id', 'offer-a-10001')->count());
    }

    public function test_job_partial_processing_counts_only_valid_offers(): void
    {
        $import = $this->makeImport([
            $this->data(['external_id' => 'ok-1', 'property' => ['code' => 'P1', 'name' => 'A', 'city' => 'BCN']]),
            ['external_id' => 'broken'], // invalid -> skipped by the job
            $this->data(['external_id' => 'ok-2', 'property' => ['code' => 'P2', 'name' => 'B', 'city' => 'BCN']]),
        ]);

        $fresh = $this->process($import);

        $this->assertSame(2, Offer::count());
        $this->assertSame(2, $fresh->total_imported);
        $this->assertSame(ImportStatus::Completed, $fresh->status);
    }

    public function test_job_imports_all_valid_offers(): void
    {
        $import = $this->makeImport([
            $this->data(['external_id' => 'o1', 'property' => ['code' => 'P1', 'name' => 'A', 'city' => 'BCN']]),
            $this->data(['external_id' => 'o2', 'property' => ['code' => 'P2', 'name' => 'B', 'city' => 'BCN']]),
        ]);

        $fresh = $this->process($import);

        $this->assertSame(2, $fresh->total_imported);
        $this->assertSame(ImportStatus::Completed, $fresh->status);
    }

    public function test_job_second_run_on_completed_import_is_noop(): void
    {
        $import = $this->makeImport([$this->data()]);

        $this->process($import);
        $this->assertSame(1, Offer::count());
        $this->assertSame(ImportStatus::Completed, $import->refresh()->status);

        // second run must not re-process
        $this->process($import);

        $this->assertSame(1, Offer::count());
        $this->assertSame(1, $import->refresh()->total_imported);
    }

    public function test_job_guard_skips_import_not_in_created_state(): void
    {
        $import = $this->makeImport([$this->data()]);
        $import->update(['status' => ImportStatus::Processing]);

        $this->process($import);

        $this->assertSame(0, Offer::count());
    }
}

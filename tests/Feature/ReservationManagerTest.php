<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Supplier;
use App\Services\Reservation\ReservationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Exceptions\OfferUnavailableException;
use Tests\TestCase;

class ReservationManagerTest extends TestCase
{
    use RefreshDatabase;

    private ReservationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(ReservationManager::class);
    }

    private function offer(int $availableUnits = 2): Offer
    {
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create(['code' => 'BCN-'.uniqid(), 'city' => 'Barcelona']);
        $import = Import::factory()->create(['supplier_id' => $supplier->id, 'raw_offers' => []]);

        return Offer::factory()->create([
            'supplier_id'     => $supplier->id,
            'property_id'     => $property->id,
            'import_id'       => $import->id,
            'external_id'     => 'offer-'.uniqid(),
            'check_in'        => '2026-10-10',
            'check_out'       => '2026-10-15',
            'max_guests'      => 4,
            'price'           => 72500,
            'currency'        => 'EUR',
            'available_units' => $availableUnits,
            'expires_at'      => now()->addDays(10),
        ]);
    }

    private function client(array $overrides = []): array
    {
        return array_merge([
            'client_reference' => 'web-order-'.uniqid(),
            'customer_name'    => 'John Smith',
            'customer_email'   => 'john@example.com',
        ], $overrides);
    }

    public function test_the_last_unit_is_sold_only_once(): void
    {
        $offer = $this->offer(availableUnits: 1);

        $this->manager->reserve($this->client(), $offer);

        $this->expectException(OfferUnavailableException::class);

        try {
            $this->manager->reserve($this->client(), $offer);
        } finally {
            $this->assertSame(0, $offer->fresh()->available_units);
            $this->assertSame(1, Reservation::count());
        }
    }

    public function test_a_sold_out_offer_cannot_be_reserved(): void
    {
        $offer = $this->offer(availableUnits: 0);

        $this->expectException(OfferUnavailableException::class);

        try {
            $this->manager->reserve($this->client(), $offer);
        } finally {
            $this->assertSame(0, Reservation::count());
        }
    }

    public function test_units_are_never_oversold(): void
    {
        $offer = $this->offer(availableUnits: 3);
        $rejected = 0;

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->manager->reserve($this->client(), $offer);
            } catch (OfferUnavailableException) {
                $rejected++;
            }
        }

        $this->assertSame(3, Reservation::count());
        $this->assertSame(2, $rejected);
        $this->assertSame(0, $offer->fresh()->available_units);
    }

    public function test_a_repeated_reference_returns_the_original_booking(): void
    {
        $offer = $this->offer(availableUnits: 2);
        $client = $this->client();

        $first = $this->manager->reserve($client, $offer);
        $again = $this->manager->reserve($client, $offer);

        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($again->wasRecentlyCreated);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Reservation::count());
        $this->assertSame(1, $offer->fresh()->available_units);
    }

    public function test_valid_data_is_persisted(): void
    {
        $offer = $this->offer();
        $client = $this->client(['customer_name' => 'Jane Doe', 'customer_email' => 'jane@example.com']);

        $reservation = $this->manager->reserve($client, $offer);

        $this->assertTrue($reservation->wasRecentlyCreated);
        $this->assertDatabaseHas('reservations', [
            'id'               => $reservation->id,
            'offer_id'         => $offer->id,
            'client_reference' => $client['client_reference'],
            'customer_name'    => 'Jane Doe',
            'customer_email'   => 'jane@example.com',
        ]);
        $this->assertSame(1, $offer->fresh()->available_units);
    }

    #[DataProvider('invalidClientProvider')]
    public function test_invalid_data_is_rejected(array $overrides): void
    {
        $offer = $this->offer();

        try {
            $this->manager->reserve($this->client($overrides), $offer);
            $this->fail('Invalid client data should have been rejected.');
        } catch (ValidationException) {
            // expected
        }

        // nothing is written and no unit is spent
        $this->assertSame(0, Reservation::count());
        $this->assertSame(2, $offer->fresh()->available_units);
    }

    public static function invalidClientProvider(): array
    {
        return [
            'no client_reference' => [['client_reference' => null]],
            'no customer_name'    => [['customer_name' => null]],
            'no customer_email'   => [['customer_email' => null]],
        ];
    }
}

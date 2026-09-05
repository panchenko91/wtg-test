<?php

namespace Tests\Feature;

use App\Models\Import;
use App\Models\Offer;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OfferReservationTest extends TestCase
{
    use RefreshDatabase;

    private function offer(array $overrides = [], string $code = 'BCN-0001'): Offer
    {
        $supplier = Supplier::factory()->create();
        $property = Property::factory()->create(['code' => $code, 'city' => 'Barcelona']);
        $import = Import::factory()->create(['supplier_id' => $supplier->id, 'raw_offers' => []]);

        return Offer::factory()->create(array_merge([
            'supplier_id'     => $supplier->id,
            'property_id'     => $property->id,
            'import_id'       => $import->id,
            'check_in'        => '2026-10-10',
            'check_out'       => '2026-10-15',
            'max_guests'      => 4,
            'price'           => 72500,
            'currency'        => 'EUR',
            'available_units' => 2,
            'expires_at'      => now()->addDays(10),
        ], $overrides));
    }

    private function book(Offer $offer, array $overrides = []): TestResponse
    {
        return $this->postJson("/api/offers/{$offer->id}/reservations", array_merge([
            'client_reference' => 'web-order-9f782b1c',
            'customer_name'    => 'John Smith',
            'customer_email'   => 'john@example.com',
        ], $overrides));
    }

    public function test_it_creates_a_reservation_and_returns_201(): void
    {
        $offer = $this->offer(['available_units' => 2]);

        $this->book($offer)
            ->assertStatus(201)
            ->assertJsonPath('data.client_reference', 'web-order-9f782b1c')
            ->assertJsonPath('data.customer_name', 'John Smith')
            ->assertJsonPath('data.customer_email', 'john@example.com')
            ->assertJsonPath('data.property.code', 'BCN-0001');

        $this->assertDatabaseHas('reservations', [
            'offer_id'         => $offer->id,
            'client_reference' => 'web-order-9f782b1c',
        ]);
    }

    public function test_it_decrements_available_units(): void
    {
        $offer = $this->offer(['available_units' => 2]);

        $this->book($offer)->assertStatus(201);

        $this->assertSame(1, $offer->fresh()->available_units);
    }

    public function test_it_returns_404_for_a_missing_offer(): void
    {
        $this->postJson('/api/offers/999999/reservations', [
            'client_reference' => 'web-order-1',
            'customer_name'    => 'John Smith',
            'customer_email'   => 'john@example.com',
        ])->assertNotFound();
    }

    public function test_it_validates_the_payload(): void
    {
        $offer = $this->offer();

        $this->postJson("/api/offers/{$offer->id}/reservations", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['client_reference', 'customer_name', 'customer_email']);

        $this->book($offer, ['customer_email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('customer_email');
    }

    public function test_the_last_unit_cannot_be_booked_twice(): void
    {
        $offer = $this->offer(['available_units' => 1]);

        $this->book($offer, ['client_reference' => 'first'])->assertStatus(201);
        $this->book($offer, ['client_reference' => 'second'])->assertStatus(409);

        $this->assertSame(0, $offer->fresh()->available_units);
        $this->assertSame(1, Reservation::count());
    }

    public function test_a_sold_out_offer_cannot_be_booked(): void
    {
        $offer = $this->offer(['available_units' => 0]);

        $this->book($offer)->assertStatus(409);

        $this->assertSame(0, Reservation::count());
    }

    public function test_units_are_never_oversold(): void
    {
        $offer = $this->offer(['available_units' => 3]);

        for ($i = 1; $i <= 5; $i++) {
            $this->book($offer, ['client_reference' => "ref-{$i}"]);
        }

        $this->assertSame(0, $offer->fresh()->available_units);
        $this->assertSame(3, Reservation::count());
    }

    public function test_repeating_the_same_request_does_not_book_twice(): void
    {
        $offer = $this->offer(['available_units' => 2]);

        $first = $this->book($offer)->assertStatus(201);
        $again = $this->book($offer)->assertOk();

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, $offer->fresh()->available_units);
        $this->assertSame(1, Reservation::count());
    }

    public function test_retrying_returns_the_booking_even_when_sold_out(): void
    {
        $offer = $this->offer(['available_units' => 1]);

        $first = $this->book($offer)->assertStatus(201);
        $this->assertSame(0, $offer->fresh()->available_units);

        $again = $this->book($offer)->assertOk();

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame(1, Reservation::count());
    }
}

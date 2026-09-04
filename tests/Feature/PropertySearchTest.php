<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\Property;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PropertySearchTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supplier = Supplier::factory()->create(['external_id' => 'supplier-a']);
    }

    private function offer(Property $property, array $overrides = []): Offer
    {
        return Offer::factory()->create(array_merge([
            'supplier_id'     => $this->supplier->id,
            'property_id'     => $property->id,
            'check_in'        => '2026-10-10',
            'check_out'       => '2026-10-15',
            'max_guests'      => 4,
            'price'           => 72500,
            'currency'        => 'EUR',
            'available_units' => 2,
            'expires_at'      => now()->addDays(10),
        ], $overrides));
    }

    private function property(array $overrides = []): Property
    {
        return Property::factory()->create(array_merge([
            'code' => 'BCN-0001',
            'city' => 'Barcelona',
        ], $overrides));
    }

    private function search(array $params = []): TestResponse
    {
        return $this->getJson('/api/properties?' . http_build_query(array_merge([
                'city'      => 'Barcelona',
                'check_in'  => '2026-10-10',
                'check_out' => '2026-10-15',
                'guests'    => 2,
            ], $params)));
    }

    // =====================================================================
    //  best_offer is always the cheapest qualifying offer
    // =====================================================================

    public function test_best_offer_is_the_cheapest_one(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'a', 'price' => 72500]);
        $this->offer($p, ['external_id' => 'b', 'price' => 68000]); // cheapest
        $this->offer($p, ['external_id' => 'c', 'price' => 90000]);

        $this->search()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0001')
            ->assertJsonPath('data.0.best_offer.price', 68000);
    }

    public function test_best_offer_is_cheapest_among_qualifying_only(): void
    {
        // the absolute cheapest is disqualified (expired) -> next cheapest wins
        $p = $this->property();
        $this->offer($p, ['external_id' => 'expired-cheap', 'price' => 10000, 'expires_at' => now()->subDay()]);
        $this->offer($p, ['external_id' => 'valid',        'price' => 68000]);
        $this->offer($p, ['external_id' => 'pricey',       'price' => 90000]);

        $this->search()
            ->assertJsonPath('data.0.best_offer.price', 68000);
    }

    public function test_each_property_gets_its_own_cheapest(): void
    {
        $a = $this->property(['code' => 'BCN-0001']);
        $b = $this->property(['code' => 'BCN-0002']);

        $this->offer($a, ['external_id' => 'a1', 'price' => 70000]);
        $this->offer($a, ['external_id' => 'a2', 'price' => 65000]); // cheapest for A
        $this->offer($b, ['external_id' => 'b1', 'price' => 50000]); // cheapest for B
        $this->offer($b, ['external_id' => 'b2', 'price' => 55000]);

        $response = $this->search()->assertOk()->assertJsonCount(2, 'data');

        $byCode = collect($response->json('data'))->keyBy('code');
        $this->assertSame(65000, $byCode['BCN-0001']['best_offer']['price']);
        $this->assertSame(50000, $byCode['BCN-0002']['best_offer']['price']);
    }

    // =====================================================================
    //  Filter 1 — dates must match
    // =====================================================================

    public function test_filter_dates_excludes_non_matching(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'other', 'price' => 10000, 'check_in' => '2026-12-01', 'check_out' => '2026-12-05']);
        $this->offer($p, ['external_id' => 'match', 'price' => 68000]);

        $this->search()->assertJsonPath('data.0.best_offer.price', 68000);
    }

    public function test_filter_dates_hides_property_when_none_match(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'only', 'check_in' => '2026-12-01', 'check_out' => '2026-12-05']);

        $this->search()->assertOk()->assertJsonCount(0, 'data');
    }

    // =====================================================================
    //  Filter 2 — max_guests >= guests
    // =====================================================================

    public function test_filter_guests_excludes_too_small(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'small', 'price' => 10000, 'max_guests' => 1]);
        $this->offer($p, ['external_id' => 'fits',  'price' => 68000, 'max_guests' => 4]);

        $this->search(['guests' => 3])->assertJsonPath('data.0.best_offer.price', 68000);
    }

    public function test_filter_guests_hides_property_when_none_fit(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'small', 'max_guests' => 1]);

        $this->search(['guests' => 5])->assertOk()->assertJsonCount(0, 'data');
    }

    // =====================================================================
    //  Filter 3 — available_units > 0
    // =====================================================================

    public function test_filter_units_excludes_sold_out(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'sold', 'price' => 10000, 'available_units' => 0]);
        $this->offer($p, ['external_id' => 'ok',   'price' => 68000, 'available_units' => 1]);

        $this->search()->assertJsonPath('data.0.best_offer.price', 68000);
    }

    public function test_filter_units_hides_property_when_all_sold_out(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'sold', 'available_units' => 0]);

        $this->search()->assertOk()->assertJsonCount(0, 'data');
    }

    // =====================================================================
    //  Filter 4 — expires_at > now
    // =====================================================================

    public function test_filter_expiry_excludes_expired(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'expired', 'price' => 10000, 'expires_at' => now()->subDay()]);
        $this->offer($p, ['external_id' => 'valid',   'price' => 68000, 'expires_at' => now()->addDay()]);

        $this->search()->assertJsonPath('data.0.best_offer.price', 68000);
    }

    public function test_filter_expiry_hides_property_when_all_expired(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'expired', 'expires_at' => now()->subDay()]);

        $this->search()->assertOk()->assertJsonCount(0, 'data');
    }

    // =====================================================================
    //  City filter (optional)
    // =====================================================================

    public function test_filter_city_limits_results(): void
    {
        $bcn = $this->property(['code' => 'BCN-0001', 'city' => 'Barcelona']);
        $mad = $this->property(['code' => 'MAD-0001', 'city' => 'Madrid']);
        $this->offer($bcn, ['external_id' => 'bcn', 'price' => 68000]);
        $this->offer($mad, ['external_id' => 'mad', 'price' => 50000]);

        $this->search(['city' => 'Barcelona'])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'BCN-0001');
    }

    private function searchWithoutGuests(array $params = []): TestResponse
    {
        return $this->getJson('/api/properties?' . http_build_query(array_merge([
                'city'      => 'Barcelona',
                'check_in'  => '2026-10-10',
                'check_out' => '2026-10-15',
            ], $params)));
    }

    private function searchWithoutCity(array $params = []): TestResponse
    {
        return $this->getJson('/api/properties?' . http_build_query(array_merge([
                'check_in'  => '2026-10-10',
                'check_out' => '2026-10-15',
                'guests'    => 2,
            ], $params)));
    }

    public function test_without_city_returns_all_cities(): void
    {
        $bcn = $this->property(['code' => 'BCN-0001', 'city' => 'Barcelona']);
        $mad = $this->property(['code' => 'MAD-0001', 'city' => 'Madrid']);
        $this->offer($bcn, ['external_id' => 'bcn', 'price' => 68000]);
        $this->offer($mad, ['external_id' => 'mad', 'price' => 50000]);

        $this->searchWithoutCity()
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // =====================================================================
    //  guests parameter is optional
    // =====================================================================

    /**
     * The `guests` filter is applied only when a positive number is given,
     * so omitting it must not hide offers with a small max_guests.
     */
    public function test_without_guests_the_capacity_filter_is_not_applied(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'small', 'price' => 10000, 'max_guests' => 1]);
        $this->offer($p, ['external_id' => 'big',   'price' => 60000, 'max_guests' => 8]);

        $this->searchWithoutGuests()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.best_offer.price', 10000);
    }

    public function test_property_is_still_hidden_without_guests_when_no_offer_qualifies(): void
    {
        // capacity is the only filter dropped — the others still apply
        $p = $this->property();
        $this->offer($p, ['external_id' => 'expired', 'max_guests' => 1, 'expires_at' => now()->subDay()]);

        $this->searchWithoutGuests()->assertOk()->assertJsonCount(0, 'data');
    }

    /**
     * Creates $count properties in Barcelona, each with one qualifying offer.
     * Prices ascend with the index so the ordering is predictable.
     */
    private function properties(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $code = sprintf('BCN-%04d', $i);
            $property = $this->property(['code' => $code]);
            $this->offer($property, [
                'external_id' => 'offer-' . $i,
                'price'       => 50000 + $i * 1000,
            ]);
        }
    }

    public function test_pagination_exposes_per_page_next_and_prev(): void
    {
        $this->properties(12);

        $this->search()
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('links.prev', null)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);

        $this->assertStringContainsString('page=2', $this->search()->json('links.next'));
    }

    public function test_second_page_returns_the_remainder(): void
    {
        $this->properties(12);

        $response = $this->search(['page' => 2])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('links.next', null);

        $this->assertStringContainsString('page=1', $response->json('links.prev'));
    }

    /**
     * Pagination must count properties, not offers: a property with several
     * qualifying offers still occupies exactly one slot on the page.
     */
    public function test_pagination_counts_properties_not_offers(): void
    {
        $p = $this->property();
        $this->offer($p, ['external_id' => 'x', 'price' => 60000]);
        $this->offer($p, ['external_id' => 'y', 'price' => 70000]);
        $this->offer($p, ['external_id' => 'z', 'price' => 80000]);

        $this->search()
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');
    }
}

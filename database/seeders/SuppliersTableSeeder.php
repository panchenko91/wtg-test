<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SuppliersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->getItems() as $item) {
            if ($this->exists($item)) {
                continue;
            }

            Supplier::factory()->create($item);
        }
    }

    /**
     * @param $item
     * @return bool
     */
    protected function exists($item)
    {
        return Supplier::query()->where('external_id', data_get($item, 'external_id'))
            ->exists();
    }

    /**
     * @return array
     */
    protected function getItems()
    {
        return [
            [
                'name' => 'supplier-a',
                'external_id' => 'supplier-a',
            ],
        ];
    }
}

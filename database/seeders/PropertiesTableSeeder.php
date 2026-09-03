<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PropertiesTableSeeder extends Seeder
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

            Property::factory()->create($item);
        }
    }

    /**
     * @param $item
     * @return bool
     */
    protected function exists($item)
    {
        return Property::query()->where('code', data_get($item, 'code'))
            ->exists();
    }

    /**
     * @return array
     */
    protected function getItems()
    {
        return [
            [
                'name' => 'Test Property',
                'code' => 'test-property',
                'city' => 'Barcelona',
            ],
        ];
    }
}

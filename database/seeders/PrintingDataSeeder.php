<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PrintingProductType;

class PrintingDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dataPath = database_path('data/printing');
        $files = glob($dataPath . '/*.json');
        sort($files);

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->command->error("❌ JSON Error in " . basename($file) . ": " . json_last_error_msg());
                continue;
            }
            $this->seedCategory($data);
            $this->command->info("✅ " . $data['category']['name'] . " (" . count($data['types']) . " types)");
        }
    }

    private function seedCategory(array $data): void
    {
        $category = PrintingProductType::create($data['category']);
        foreach ($data['types'] as $typeData) {
            $attributes = $typeData['attributes'] ?? [];
            $discounts = $typeData['volume_discounts'] ?? [];
            unset($typeData['attributes'], $typeData['volume_discounts'], $typeData['allow_custom_name']);
            $type = $category->printingTypes()->create($typeData);
            
            foreach ($attributes as $attrData) {
                $values = $attrData['values'] ?? [];
                unset($attrData['values']);
                $attr = $type->attributes()->create($attrData);
                
                foreach ($values as $valueData) {
                    $attr->values()->create($valueData);
                }
            }
            
            foreach ($discounts as $discountData) {
                $type->volumeDiscounts()->create($discountData);
            }
        }
    }
}

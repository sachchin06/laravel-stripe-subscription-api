<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'stripe_product_id' => 'prod_'.Str::random(14),
            'is_active' => true,
        ];
    }

    /**
     * A plan configured in config/features.php ("basic" or "pro").
     */
    public function slug(string $slug): static
    {
        return $this->state(fn () => ['name' => ucfirst($slug), 'slug' => $slug]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function notInStripe(): static
    {
        return $this->state(fn () => ['stripe_product_id' => null]);
    }
}

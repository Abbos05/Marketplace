<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Category;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'price' => fake()->numberBetween(1000, 10000),
            'image' => 'nfts/' . fake()->uuid() . '.jpg',
            'status' => 'moderation',
            'tags' => 'art,digital',
            'category_id' => Category::factory(), // ← ДОБАВЬ
            'user_id' => User::factory(),
        ];
    }
}
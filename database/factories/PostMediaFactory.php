<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PostMedia;
use App\Models\Post;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostMedia>
 */
class PostMediaFactory extends Factory
{
    protected $model = PostMedia::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id'    => Post::factory(),
            'media_url'  => 'posts/'.$this->faker->uuid().'.jpg', // simpan PATH (bukan URL)
            'media_type' => 'image',
            'sort_order' => 1,
        ];
    }
}

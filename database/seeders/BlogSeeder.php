<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\User;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        // Make sure you have users, categories, and tags first
        $authors = User::whereIn('role', ['superadmin', 'admin'])->get();
        $categories = Category::all();
        $tags = Tag::all();

        if ($authors->isEmpty() || $categories->isEmpty() || $tags->isEmpty()) {
            $this->command->warn("Make sure users, categories, and tags exist before seeding blogs.");
            return;
        }

        // Create 10 blog posts
        for ($i = 1; $i <= 10; $i++) {
            $title = "Contoh Blog $i";
            $slug = Str::slug($title);

            $blog = Blog::create([
                'title' => $title,
                'slug' => $slug,
                'excerpt' => fake()->sentence(),
                'content' => fake()->paragraph(10),
                'featured_image' => null,
                'status' => fake()->randomElement(['draft', 'published']),
                'author_id' => $authors->random()->id,
            ]);

            // Attach random categories and tags
            $blog->categories()->attach($categories->random(2)->pluck('id'));
            $blog->tags()->attach($tags->random(3)->pluck('id'));
        }
    }
}

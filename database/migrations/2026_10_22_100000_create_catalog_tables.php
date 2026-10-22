<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->string('photo_credit')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->string('country', 80)->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('season', 40)->nullable();
            $table->string('city', 80)->nullable();
            $table->date('event_date')->nullable();
            $table->text('description')->nullable();
            $table->string('cover')->nullable();
            $table->string('photo_credit')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->string('photo_credit')->nullable();
            $table->string('instagram')->nullable();
            $table->string('city', 80)->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->timestamp('consent_confirmed_at')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('collection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('published');
            $table->timestamp('published_at')->nullable();
            $table->text('credits')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('preview')->nullable();
            $table->unsignedSmallInteger('thumbnail_time')->nullable();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
            $table->index(['brand_id', 'status']);
        });

        Schema::create('model_video', function (Blueprint $table) {
            $table->foreignId('model_id')->constrained('models')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->primary(['model_id', 'video_id']);
        });

        Schema::create('tag_video', function (Blueprint $table) {
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->primary(['tag_id', 'video_id']);
        });

        Schema::create('view_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 80)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['video_id', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->string('status', 20)->default('approved');
            $table->index('status');
        });

        // Переносим старую строковую категорию в нормализованную таблицу.
        $now = now();
        $categories = DB::table('videos')
            ->select('category')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');

        $usedSlugs = [];

        foreach ($categories as $name) {
            $slug = Str::slug((string) $name) ?: 'category-'.count($usedSlugs);

            while (in_array($slug, $usedSlugs, true)) {
                $slug .= '-'.count($usedSlugs);
            }

            $usedSlugs[] = $slug;

            DB::table('categories')->insert([
                'name' => Str::headline((string) $name),
                'slug' => $slug,
                'sort_order' => count($usedSlugs),
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('videos')->update([
            'status' => 'published',
            'published_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
        ]);

        // SQLite/MySQL обе понимают коррелированный подзапрос.
        DB::statement(
            'UPDATE videos SET category_id = (SELECT id FROM categories WHERE categories.slug = videos.category) WHERE category_id IS NULL'
        );
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });

        Schema::dropIfExists('view_events');
        Schema::dropIfExists('tag_video');
        Schema::dropIfExists('model_video');

        Schema::table('videos', function (Blueprint $table) {
            $table->dropIndex(['status', 'published_at']);
            $table->dropIndex(['category_id', 'status']);
            $table->dropIndex(['brand_id', 'status']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('brand_id');
            $table->dropConstrainedForeignId('collection_id');
            $table->dropColumn([
                'status', 'published_at', 'credits', 'width', 'height',
                'preview', 'thumbnail_time',
            ]);
        });

        Schema::dropIfExists('tags');
        Schema::dropIfExists('models');
        Schema::dropIfExists('collections');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};

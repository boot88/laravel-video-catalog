<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FashionCollection;
use App\Models\ModelProfile;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('model_video')->delete();
        DB::table('tag_video')->delete();
        DB::table('collections')->delete();
        DB::table('brands')->delete();
        DB::table('models')->delete();
        DB::table('tags')->delete();
        DB::table('categories')->delete();

        $photos = config('media_credits.photos', []);

        $categories = [
            ['Подиум', 'runway', 'Показы и презентации новых коллекций: от дебютов до сезонных манифестов.'],
            ['Стритвир', 'streetwear', 'Уличная мода, коллаборации и культура повседневного образа.'],
            ['От кутюр', 'haute-couture', 'Ручная работа, архитектурный крой и экспериментальные силуэты.'],
            ['Аксессуары', 'accessories', 'Сумки, обувь и украшения как самостоятельный герой кадра.'],
            ['Бьюти', 'beauty', 'Макияж, уход и бьюти-образы в движении.'],
            ['Лукбуки', 'lookbook', 'Съёмки коллекций в формате короткого fashion-фильма.'],
            ['Backstage', 'backstage', 'За кулисами: примерки, закадровые разговоры и подготовка к выходу.'],
            ['Editorial', 'editorial', 'Авторские истории и редакционные съёмки о моде.'],
            ['Мужская мода', 'menswear', 'Костюм, аутдор и новые формы мужского гардероба.'],
            ['Sustainable', 'sustainable', 'Ответственная мода, апсайклинг и локальное производство.'],
            ['Kids fashion', 'kids-fashion', 'Детская мода и семейные кампании.'],
        ];

        foreach ($categories as $index => [$name, $slug, $description]) {
            Category::query()->create([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'cover' => 'media/models/model-'.str_pad((string) (($index % 8) + 1), 2, '0', STR_PAD_LEFT).'.webp',
                'photo_credit' => $this->credit('model-'.str_pad((string) (($index % 8) + 1), 2, '0', STR_PAD_LEFT), $photos),
                'sort_order' => $index,
                'is_visible' => true,
            ]);
        }

        $brands = [
            ['Atelier Nord', 'atelier-nord', 'Копенгаген', 'Скульптурный минимализм и медленная мода из локальных материалов.'],
            ['Mirae', 'mirae', 'Сеул', 'Гибрид уличной одежды и традиционного кроя, собранный вручную.'],
            ['Vestige', 'vestige', 'Париж', 'Архивные силуэты, переосмысленные в современных тканях.'],
            ['Noir Studio', 'noir-studio', 'Берлин', 'Монохром, кожа и индустриальная эстетика без лишних деталей.'],
            ['Lumen', 'lumen', 'Милан', 'Свет, драпировка и вечерний образ как форма движения.'],
            ['Serein', 'serein', 'Тбилиси', 'Тихая роскошь и ручная отделка для повседневного гардероба.'],
        ];

        foreach ($brands as [$name, $slug, $country, $description]) {
            Brand::query()->create([
                'name' => $name,
                'slug' => $slug,
                'country' => $country,
                'description' => $description,
                'is_visible' => true,
            ]);
        }

        $brands = Brand::query()->get()->keyBy('slug');

        $collections = [
            ['atelier-nord', 'SS26: Northern Light', 'ss26', 'Копенгаген', '2026-02-04', 'Коллекция о северном свете, мягкой шерсти и геометрии тела.'],
            ['mirae', 'FW26: Afterimage', 'fw26', 'Сеул', '2026-03-12', 'Остаточные образы города: деним, шёлк и ручная вышивка.'],
            ['vestige', 'Archive 03', 'archive', 'Париж', '2025-11-18', 'Переиздание архивных моделей в новых пропорциях.'],
            ['noir-studio', 'Black Matter', 'fw25', 'Берлин', '2025-10-02', 'Кожа, металл и чёрный цвет как материал, а не фон.'],
            ['lumen', 'Evening Study', 'ss26', 'Милан', '2026-02-21', 'Драпировка и вечерние образы, снятые в движении.'],
            ['serein', 'Quiet Form', 'resort', 'Тбилиси', '2026-01-16', 'Тихая форма: лён, шёлк и ручная отделка.'],
        ];

        foreach ($collections as $index => [$brandSlug, $title, $season, $city, $date, $description]) {
            FashionCollection::query()->create([
                'brand_id' => $brands[$brandSlug]->id,
                'title' => $title,
                'slug' => str($title)->slug(),
                'season' => strtoupper($season),
                'city' => $city,
                'event_date' => $date,
                'description' => $description,
                'cover' => 'media/models/model-'.str_pad((string) (($index % 8) + 1), 2, '0', STR_PAD_LEFT).'.webp',
                'photo_credit' => $this->credit('model-'.str_pad((string) (($index % 8) + 1), 2, '0', STR_PAD_LEFT), $photos),
                'is_visible' => true,
            ]);
        }

        $modelData = [
            ['Александра Волкова', 'alexandra-volkova', 'Москва', 178, 'Демонстрационный профиль. Показы, кампании и editorial.'],
            ['Майя Лебедева', 'maya-lebedeva', 'Санкт-Петербург', 175, 'Демонстрационный профиль. Минимализм и архитектурный крой.'],
            ['Ева Морозова', 'eva-morozova', 'Тбилиси', 180, 'Демонстрационный профиль. Подиум и fashion-фильмы.'],
            ['Нина Соколова', 'nina-sokolova', 'Казань', 174, 'Демонстрационный профиль. Стритвир и деним.'],
            ['Лера Орлова', 'lera-orlova', 'Екатеринбург', 177, 'Демонстрационный профиль. Вечерние образы и свет.'],
            ['София Ким', 'sofia-kim', 'Сеул', 176, 'Демонстрационный профиль. Гибридный крой и кожа.'],
            ['Алиса Браун', 'alisa-brown', 'Берлин', 179, 'Демонстрационный профиль. Монохром и индустриальная эстетика.'],
            ['Мария Чен', 'maria-chen', 'Милан', 173, 'Демонстрационный профиль. Editorial и аксессуары.'],
        ];

        foreach ($modelData as $index => [$name, $slug, $city, $height, $bio]) {
            ModelProfile::query()->create([
                'name' => $name,
                'slug' => $slug,
                'city' => $city,
                'height' => $height,
                'bio' => $bio,
                'photo' => 'media/models/model-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'.webp',
                'photo_credit' => $this->credit('model-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), $photos),
                'is_visible' => true,
            ]);
        }

        $tags = [
            ['минимализм', 'minimalism'],
            ['архитектурный крой', 'architectural-cut'],
            ['деним', 'denim'],
            ['кожа', 'leather'],
            ['вечерний образ', 'evening'],
            ['подиум', 'runway'],
            ['backstage', 'backstage'],
            ['устойчивая мода', 'sustainable'],
            ['стритстайл', 'street-style'],
            ['монохром', 'monochrome'],
        ];

        foreach ($tags as [$name, $slug]) {
            Tag::query()->create(['name' => $name, 'slug' => $slug]);
        }
    }

    /** @param array<int, array<string, string>> $photos */
    private function credit(string $file, array $photos): string
    {
        foreach ($photos as $photo) {
            if (($photo['file'] ?? '') === $file.'.webp') {
                return $photo['author'].' / '.$photo['license'];
            }
        }

        return 'CC0 / CC BY';
    }
}

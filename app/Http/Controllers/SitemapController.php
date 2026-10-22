<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FashionCollection;
use App\Models\ModelProfile;
use App\Models\Tag;
use App\Models\Video;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = collect([
            ['loc' => route('video.index'), 'changefreq' => 'daily', 'priority' => '1.0', 'lastmod' => now()],
            ['loc' => route('videos.browse'), 'changefreq' => 'daily', 'priority' => '0.9', 'lastmod' => now()],
            ['loc' => route('models.index'), 'changefreq' => 'weekly', 'priority' => '0.8', 'lastmod' => now()],
            ['loc' => route('brands.index'), 'changefreq' => 'weekly', 'priority' => '0.7', 'lastmod' => now()],
            ['loc' => route('collections.index'), 'changefreq' => 'weekly', 'priority' => '0.7', 'lastmod' => now()],
            ['loc' => route('about'), 'changefreq' => 'monthly', 'priority' => '0.4', 'lastmod' => now()],
            ['loc' => route('credits'), 'changefreq' => 'yearly', 'priority' => '0.2', 'lastmod' => now()],
            ['loc' => route('privacy'), 'changefreq' => 'yearly', 'priority' => '0.2', 'lastmod' => now()],
        ]);

        Category::query()->visible()->get(['slug', 'updated_at'])->each(
            fn (Category $category) => $urls->push([
                'loc' => route('categories.show', $category),
                'changefreq' => 'weekly',
                'priority' => '0.8',
                'lastmod' => $category->updated_at,
            ])
        );

        ModelProfile::query()->visible()->get(['slug', 'updated_at'])->each(
            fn (ModelProfile $model) => $urls->push([
                'loc' => route('models.show', $model),
                'changefreq' => 'weekly',
                'priority' => '0.6',
                'lastmod' => $model->updated_at,
            ])
        );

        Brand::query()->visible()->get(['slug', 'updated_at'])->each(
            fn (Brand $brand) => $urls->push([
                'loc' => route('brands.show', $brand),
                'changefreq' => 'weekly',
                'priority' => '0.6',
                'lastmod' => $brand->updated_at,
            ])
        );

        FashionCollection::query()->visible()->get(['slug', 'updated_at'])->each(
            fn (FashionCollection $collection) => $urls->push([
                'loc' => route('collections.show', $collection),
                'changefreq' => 'weekly',
                'priority' => '0.6',
                'lastmod' => $collection->updated_at,
            ])
        );

        Tag::query()->get(['slug', 'updated_at'])->each(
            fn (Tag $tag) => $urls->push([
                'loc' => route('tags.show', $tag),
                'changefreq' => 'monthly',
                'priority' => '0.4',
                'lastmod' => $tag->updated_at,
            ])
        );

        Video::query()->published()->get(['slug', 'updated_at'])->each(
            fn (Video $video) => $urls->push([
                'loc' => route('video.show', $video),
                'changefreq' => 'monthly',
                'priority' => '0.7',
                'lastmod' => $video->updated_at,
            ])
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $xml .= '<url>'
                .'<loc>'.htmlspecialchars($url['loc'], ENT_XML1).'</loc>'
                .($url['lastmod'] ? '<lastmod>'.$url['lastmod']->toAtomString().'</lastmod>' : '')
                .'<changefreq>'.$url['changefreq'].'</changefreq>'
                .'<priority>'.$url['priority'].'</priority>'
                .'</url>';
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}

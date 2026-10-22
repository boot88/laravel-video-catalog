<?php

namespace App\Http\Controllers;

use App\Models\ModelProfile;
use App\Models\Video;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('pages.about', ['stats' => $this->stats()]);
    }

    public function privacy(): View
    {
        return view('pages.privacy', ['stats' => $this->stats()]);
    }

    public function credits(): View
    {
        return view('pages.credits', [
            'photos' => config('media_credits.photos', []),
            'videos' => config('media_credits.videos', []),
            'stats' => $this->stats(),
        ]);
    }

    /** @return array{videos:int,views:int,models:int} */
    private function stats(): array
    {
        return [
            'videos' => Video::query()->published()->count(),
            'views' => (int) Video::query()->published()->sum('views'),
            'models' => ModelProfile::query()->visible()->count(),
        ];
    }
}

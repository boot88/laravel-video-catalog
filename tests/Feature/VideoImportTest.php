<?php

namespace Tests\Feature;

use App\Models\Video;
use App\Services\VideoProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Импорт видео из папки (php artisan videos:import).
 * Раньше видео можно было добавить только сидером, который сначала чистит таблицу.
 */
class VideoImportTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceDir;

    /** Файлы, созданные тестом вне временной папки (их надо удалить). */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDir = storage_path('framework/testing/import/blondinki');
        File::ensureDirectoryExists($this->sourceDir);

        // Заполнитель с «взрослым» ID: иначе тестовые видео получат ID 1, 2, ... и их превью
        // (thumbnails/video_thumb_1.jpg) перезапишут превью реальных видео в storage/app/public.
        DB::table('videos')->insert([
            'id' => 90000,
            'title' => 'Filler',
            'slug' => 'filler',
            'filename' => 'filler.mp4',
            'category' => 'filler',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/import'));

        foreach ($this->createdFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    /** Настоящий mp4 из репозитория (самый маленький) — чтобы ffprobe дал длительность. */
    private function putVideo(string $name, ?string $dir = null): string
    {
        $source = public_path('images/m2-res_1920p.mp4');
        $path = ($dir ?? $this->sourceDir).'/'.$name;

        File::ensureDirectoryExists(dirname($path));
        copy($source, $path);

        return $path;
    }

    private function trackStoredVideo(string $filename, ?string $thumbnail = null): void
    {
        $this->createdFiles[] = storage_path('app/public/videos/'.$filename);

        if ($thumbnail !== null && str_starts_with($thumbnail, 'thumbnails/')) {
            $this->createdFiles[] = storage_path('app/public/'.$thumbnail);
        }
    }

    public function test_it_imports_video_from_folder_and_moves_file(): void
    {
        $this->putVideo('блондинка на пляже.mp4');

        $this->artisan('videos:import', ['folder' => $this->sourceDir])
            ->assertSuccessful();

        $video = Video::query()->where('category', 'blondinki')->firstOrFail();

        $this->assertSame('blondinki', $video->category);
        $this->assertSame('Блондинка на пляже', $video->title);
        $this->assertSame('blondinka-na-pliaze', $video->slug);
        $this->assertSame('blondinka-na-pliaze.mp4', $video->filename);
        $this->assertSame(0, (int) $video->views);
        $this->assertSame(0, (int) $video->rating);

        // Файл переехал в storage и доступен по URL из модели
        $this->assertFileDoesNotExist($this->sourceDir.'/блондинка на пляже.mp4');
        $this->assertFileExists(storage_path('app/public/videos/blondinka-na-pliaze.mp4'));
        $this->assertStringContainsString('storage/videos/blondinka-na-pliaze.mp4', $video->video_url);

        $this->trackStoredVideo($video->filename, $video->thumbnail);

        if (VideoProbeService::ffprobeBinary() !== null) {
            $this->assertGreaterThan(0, (int) $video->duration, 'длительность должна читаться через ffprobe');
            $this->assertSame('Full HD', $video->quality, '1080x1920 — Full HD');
            $this->assertNotNull($video->thumbnail, 'превью должно быть создано');
        }
    }

    public function test_second_run_skips_known_files_and_keeps_source(): void
    {
        $this->putVideo('блондинка_2.mp4');

        $this->artisan('videos:import', ['folder' => $this->sourceDir])->assertSuccessful();

        // Кладём тот же файл снова и запускаем без --force
        $this->putVideo('блондинка_2.mp4');

        $this->artisan('videos:import', ['folder' => $this->sourceDir])
            ->expectsOutputToContain('уже в базе')
            ->assertSuccessful();

        $this->assertSame(1, Video::query()->where('category', 'blondinki')->count());
        $this->assertFileExists($this->sourceDir.'/блондинка_2.mp4', 'без --force исходник не переносится');

        $video = Video::query()->where('category', 'blondinki')->firstOrFail();
        $this->trackStoredVideo($video->filename, $video->thumbnail);
    }

    public function test_same_filename_in_different_category_gets_unique_storage_name(): void
    {
        $runwayDir = storage_path('framework/testing/import/runway');

        $this->putVideo('collision-test.mp4');
        $this->putVideo('collision-test.mp4', $runwayDir);

        $this->artisan('videos:import', ['folder' => $this->sourceDir, '--skip-thumbnails' => true])
            ->assertSuccessful();
        $this->artisan('videos:import', ['folder' => $runwayDir, '--skip-thumbnails' => true])
            ->assertSuccessful();

        $this->assertSame(2, Video::query()->whereIn('category', ['blondinki', 'runway'])->count());

        $first = Video::query()->where('category', 'blondinki')->firstOrFail();
        $second = Video::query()->where('category', 'runway')->firstOrFail();

        $this->assertSame('collision-test.mp4', $first->filename);
        $this->assertSame('collision-test-runway.mp4', $second->filename);
        $this->assertFileExists(storage_path('app/public/videos/collision-test.mp4'));
        $this->assertFileExists(storage_path('app/public/videos/collision-test-runway.mp4'));

        $this->trackStoredVideo($first->filename, $first->thumbnail);
        $this->trackStoredVideo($second->filename, $second->thumbnail);
    }

    public function test_copy_option_keeps_source_file(): void
    {
        $this->putVideo('блондинка_3.mp4');

        $this->artisan('videos:import', ['folder' => $this->sourceDir, '--copy' => true])
            ->assertSuccessful();

        $this->assertFileExists($this->sourceDir.'/блондинка_3.mp4');
        $this->assertFileExists(storage_path('app/public/videos/blondinka-3.mp4'));

        $video = Video::query()->where('category', 'blondinki')->firstOrFail();
        $this->trackStoredVideo($video->filename, $video->thumbnail);
    }

    public function test_dry_run_does_not_change_anything(): void
    {
        $this->putVideo('блондинка на пляже.mp4');

        $this->artisan('videos:import', ['folder' => $this->sourceDir, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, Video::query()->where('category', 'blondinki')->count());
        $this->assertFileExists($this->sourceDir.'/блондинка на пляже.mp4');
        $this->assertFileDoesNotExist(storage_path('app/public/videos/blondinka-na-pliaze.mp4'));
    }

    public function test_non_video_files_are_ignored_and_broken_video_reports_error(): void
    {
        file_put_contents($this->sourceDir.'/заметка.txt', 'это не видео');
        file_put_contents($this->sourceDir.'/broken.mp4', 'это тоже не видео');

        // .txt вообще не рассматривается, а битый .mp4 даёт ошибку (exit code 1)
        $this->artisan('videos:import', ['folder' => $this->sourceDir])
            ->expectsOutputToContain('broken.mp4')
            ->assertExitCode(1);

        $this->assertSame(0, Video::query()->where('category', 'blondinki')->count());
    }

    public function test_slug_stays_unique(): void
    {
        Video::query()->create([
            'title' => 'Занятый slug',
            'slug' => 'blondinka-2',
            'filename' => 'other-video.mp4',
            'duration' => 10,
            'category' => 'blondinki',
        ]);

        $this->putVideo('блондинка_2.mp4');

        $this->artisan('videos:import', ['folder' => $this->sourceDir])->assertSuccessful();

        $imported = Video::query()->where('filename', 'blondinka-2.mp4')->firstOrFail();

        $this->assertSame('blondinka-2-2', $imported->slug);

        $this->trackStoredVideo($imported->filename, $imported->thumbnail);
    }
}

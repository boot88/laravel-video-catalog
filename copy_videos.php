<?php

// copy_videos.php

$sourceDir = __DIR__.'/public/images';
$targetDir = __DIR__.'/storage/app/public/videos';

echo "Копирование видео файлов...\n";

// Создаем целевую папку
if (! file_exists($targetDir)) {
    mkdir($targetDir, 0755, true);
    echo "Создана папка: $targetDir\n";
}

// Ищем все mp4 файлы в исходной папке
$videoFiles = glob($sourceDir.'/*.mp4');

if (empty($videoFiles)) {
    echo "Не найдены mp4 файлы в $sourceDir\n";
    echo "Создаем тестовые файлы...\n";

    // Создаем тестовый файл
    $testContent = "RIFF\x00\x00\x00\x00WEBPVP8 ";
    for ($i = 1; $i <= 50; $i++) {
        $filename = "funniest_home_videos_part_$i.mp4";
        $filePath = $targetDir.'/'.$filename;

        file_put_contents($filePath, $testContent." - Тестовое видео $i");
        echo "Создан тестовый файл: $filename\n";
    }
} else {
    // Копируем существующие файлы
    foreach ($videoFiles as $key => $file) {
        $filename = 'funniest_home_videos_part_'.($key + 1).'.mp4';
        $targetPath = $targetDir.'/'.$filename;

        if (copy($file, $targetPath)) {
            echo 'Скопировано: '.basename($file)." -> $filename\n";
        } else {
            echo 'Ошибка копирования: '.basename($file)."\n";
        }
    }

    // Если файлов меньше 50, создаем недостающие
    $existingCount = count($videoFiles);
    if ($existingCount < 50) {
        for ($i = $existingCount + 1; $i <= 50; $i++) {
            $filename = "funniest_home_videos_part_$i.mp4";
            $filePath = $targetDir.'/'.$filename;

            // Копируем последний существующий файл или создаем новый
            $sourceFile = $videoFiles[count($videoFiles) - 1];
            copy($sourceFile, $filePath);
            echo "Создан на основе существующего: $filename\n";
        }
    }
}

echo "Готово!\n";

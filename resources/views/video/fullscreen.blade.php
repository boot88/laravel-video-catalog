{{-- resources/views/video/fullscreen.blade.php --}}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $video->title }} | Fullscreen</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #000;
            font-family: 'Segoe UI', sans-serif;
            height: 100vh;
            overflow: hidden;
        }
        
        .fullscreen-player {
            width: 100%;
            height: 100%;
            position: relative;
        }
        
        #fullscreenVideo {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            z-index: 100;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .fullscreen-empty {
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #f4f0ea;
            text-align: center;
        }

        .fullscreen-empty h1 {
            font-family: Georgia, serif;
            font-weight: 400;
        }

        .fullscreen-empty a {
            color: #d8ff5f;
        }
    </style>
</head>
<body>
    <div class="fullscreen-player">
        <button class="back-btn" onclick="window.history.back()">← Назад</button>
        @if ($video->has_video)
            <video id="fullscreenVideo" controls autoplay playsinline poster="{{ $video->thumbnail_url }}">
                <source src="{{ $video->video_url }}" type="{{ $video->mime_type }}">
                Ваш браузер не поддерживает видео.
            </video>
        @else
            <div class="fullscreen-empty">
                <h1>{{ $video->title }}</h1>
                <p>Видеофайл ещё не загружен.</p>
                <a href="{{ route('video.show', $video) }}">Вернуться на страницу видео</a>
            </div>
        @endif
    </div>
    
    <script>
        const video = document.getElementById('fullscreenVideo');
        
        // Выход по ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                window.history.back();
            }
        });
        
        // Клик на видео для паузы/воспроизведения
        video.addEventListener('click', () => {
            if (video.paused) {
                video.play();
            } else {
                video.pause();
            }
        });
    </script>
</body>
</html>
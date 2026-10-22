{{-- resources/views/video/show.blade.php --}}
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $video->title }} | Fashion Video</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', -apple-system, system-ui, sans-serif;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            min-height: 100vh;
            color: #fff;
        }
        
        /* Fashion Header */
        .fashion-header {
            background: rgba(20, 20, 20, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px 40px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }
        
        .logo-icon {
            font-size: 28px;
            background: linear-gradient(45deg, #ff6b9d, #4ecdc4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .logo-text {
            font-size: 1.8rem;
            font-weight: 300;
            letter-spacing: 2px;
            background: linear-gradient(45deg, #ff6b9d, #4ecdc4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .back-btn {
            background: linear-gradient(45deg, #ff6b9d, #4ecdc4);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 157, 0.3);
        }
        
        /* Main Content */
        .main-content {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 40px;
        }
        
        /* Video Player Container */
        .video-player-section {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 40px;
            margin-bottom: 50px;
        }
        
        @media (max-width: 1024px) {
            .video-player-section {
                grid-template-columns: 1fr;
            }
        }
        
        /* Fashion Video Player */
        .fashion-video-player {
            background: rgba(20, 20, 20, 0.9);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .video-wrapper {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            background: #000;
        }
        
        .video-wrapper video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 24px 24px 0 0;
        }
        
        .player-controls {
            padding: 25px;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.9));
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .control-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 18px;
        }
        
        .control-btn:hover {
            background: linear-gradient(45deg, #ff6b9d, #4ecdc4);
            transform: scale(1.1);
        }
        
        .play-btn {
            background: linear-gradient(45deg, #ff6b9d, #4ecdc4);
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.4);
            width: 55px;
            height: 55px;
            font-size: 20px;
        }
        
        .control-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Video Info Sidebar */
        .video-info-sidebar {
            background: rgba(20, 20, 20, 0.9);
            border-radius: 24px;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }
        
        .video-title {
            font-size: 1.8rem;
            font-weight: 400;
            margin-bottom: 15px;
            color: #fff;
            line-height: 1.4;
        }
        
        .video-description {
            color: #aaa;
            line-height: 1.6;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        
        /* Stats Grid - One Column Version */
.stats-grid {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 30px;
}

        
        .stat-item {
    background: rgba(40, 40, 40, 0.7);
    border-radius: 15px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.05);
    min-height: 70px;
}
        
        .stat-item:hover {
    transform: translateX(5px);
    background: rgba(50, 50, 50, 0.8);
    border-color: rgba(255, 107, 157, 0.2);
}
        
        .stat-icon {
    width: 45px;
    height: 45px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    background: linear-gradient(45deg, #ff6b9d, #4ecdc4);
    flex-shrink: 0;
    box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
}
        
       .stat-info {
    flex: 1;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-width: 0;
}
        
  .stat-label {
    font-size: 0.95rem;
    color: #aaa;
    text-transform: uppercase;
    font-weight: 500;
    letter-spacing: 0.8px;
    white-space: nowrap;
    margin-right: 20px;
}       
      .stat-value {
    font-size: 1.3rem;
    font-weight: 600;
    color: #fff;
    white-space: nowrap;
    text-align: right;
    min-width: 80px;
}


       /* For long values like "8,496" */
.stat-value.long {
    font-size: 1.2rem;
}

/* Progress bar for rating (optional) */
.rating-progress {
    width: 100px;
    height: 6px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 3px;
    overflow: hidden;
    margin-left: 15px;
}

.rating-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff6b9d, #4ecdc4);
    border-radius: 3px;
    transition: width 0.5s ease;
}
        
        .action-buttons {
            display: flex;
            gap: 15px;
        }
        
        .action-btn {
            flex: 1;
            padding: 16px;
            border-radius: 15px;
            border: none;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .fullscreen-btn {
            background: linear-gradient(45deg, #ff6b9d, #4ecdc4);
            color: white;
        }
        
        .fullscreen-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 157, 0.3);
        }
        
        .like-btn {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .like-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        /* Comments Section */
        .comments-section {
            background: rgba(20, 20, 20, 0.9);
            border-radius: 24px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        }
        
        .section-title {
            font-size: 1.8rem;
            margin-bottom: 30px;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .comment-form {
            background: rgba(40, 40, 40, 0.7);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 10px;
            color: #aaa;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .form-input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #ff6b9d;
            background: rgba(255, 255, 255, 0.08);
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.1);
        }
        
        .form-textarea {
            min-height: 140px;
            resize: vertical;
        }
        
        .submit-btn {
            padding: 16px 35px;
            background: linear-gradient(45deg, #ff6b9d, #4ecdc4);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 107, 157, 0.3);
        }
        
        .comments-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .comment-card {
            background: rgba(40, 40, 40, 0.7);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        
        .comment-card:hover {
            transform: translateX(5px);
            background: rgba(50, 50, 50, 0.8);
            border-color: rgba(255, 107, 157, 0.2);
        }
        
        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .comment-author {
            font-weight: 600;
            color: #ff6b9d;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ff6b9d, #4ecdc4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .comment-date {
            font-size: 0.9rem;
            color: #888;
        }
        
        .comment-content {
            color: #ddd;
            line-height: 1.7;
            font-size: 1.05rem;
        }
        
        .no-comments {
            text-align: center;
            padding: 60px 40px;
            color: #888;
            font-size: 1.2rem;
        }
        
        .alert {
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 25px;
            border-left: 5px solid;
            background: rgba(40, 40, 40, 0.7);
        }
        
        .alert-success {
            border-color: #4ecdc4;
            color: #4ecdc4;
        }
        
        .alert-danger {
            border-color: #ff6b9d;
            color: #ff6b9d;
        }
        
        /* Fashion Footer */
        .fashion-footer {
            background: rgba(20, 20, 20, 0.95);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 40px;
            margin-top: 60px;
        }
        
        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 15px;
            text-decoration: none;
        }
        
        .footer-logo-text {
            font-size: 1.5rem;
            font-weight: 300;
            letter-spacing: 2px;
            background: linear-gradient(45deg, #ff6b9d, #4ecdc4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .footer-copyright {
            color: #aaa;
            font-size: 0.9rem;
        }
        
        .footer-stats {
            display: flex;
            gap: 30px;
        }
        
        .footer-stat {
            text-align: center;
        }
        
        .footer-stat-value {
            font-size: 1.3rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 5px;
        }
        
        .footer-stat-label {
            font-size: 0.9rem;
            color: #aaa;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .fashion-header,
            .main-content {
                padding: 20px;
            }
            
            .header-container {
                flex-direction: column;
                gap: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .footer-container {
                flex-direction: column;
                gap: 30px;
                text-align: center;
            }
            
            .footer-stats {
                order: -1;
                width: 100%;
                justify-content: space-around;
            }
        }
    </style>
</head>
<body>
    <!-- Fashion Header -->
    <header class="fashion-header">
        <div class="header-container">
            <a href="{{ route('video.index') }}" class="logo">
                <span class="logo-icon">🎬</span>
                <span class="logo-text">FASHION VIDEO</span>
            </a>
            <a href="{{ route('video.index') }}" class="back-btn">
                ← Назад к видео
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="video-player-section">
            <!-- Fashion Video Player -->
            <div class="fashion-video-player">
                <div class="video-wrapper">
                    <video id="mainVideo" controls poster="{{ $video->thumbnail_url }}">
                        <source src="{{ $video->video_url }}" type="{{ $video->mime_type }}">
                        Ваш браузер не поддерживает видео.
                    </video>
                </div>
                <div class="player-controls">
                    <div class="control-group">
                        <button class="control-btn play-btn" id="playBtn">▶</button>
                        <button class="control-btn" id="volumeBtn">🔊</button>
                    </div>
                    <div class="control-group">
                        <button class="control-btn" id="fullscreenBtn">⛶</button>
                    </div>
                </div>
            </div>

            <!-- Video Info Sidebar -->
            <div class="video-info-sidebar">
                <h1 class="video-title">{{ $video->title }}</h1>
                <p class="video-description">{{ $video->description }}</p>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon">⏱️</div>
                        <div class="stat-info">
                            <div class="stat-label">ДЛИТЕЛЬНОСТЬ</div>
                            <div class="stat-value">{{ $video->formatted_duration }}</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">🎬</div>
                        <div class="stat-info">
                            <div class="stat-label">КАЧЕСТВО</div>
                            <div class="stat-value">{{ $video->quality }}</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">👁️</div>
                        <div class="stat-info">
                            <div class="stat-label">ПРОСМОТРЫ</div>
                            <div class="stat-value">{{ number_format($video->views) }}</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">💬</div>
                        <div class="stat-info">
                            <div class="stat-label">КОММЕНТАРИИ</div>
                            <div class="stat-value">{{ $video->comments_count }}</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">❤️</div>
                        <div class="stat-info">
                            <div class="stat-label">ЛАЙКИ</div>
                            <div class="stat-value" id="likesCount">{{ number_format($video->likes) }}</div>
                        </div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-info">
                            <div class="stat-label">РЕЙТИНГ</div>
                            <div class="stat-value">{{ number_format($video->total_rating) }}</div>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button class="action-btn fullscreen-btn" onclick="toggleFullscreen()">
                        ⛶ ПОЛНЫЙ ЭКРАН
                    </button>
                    <button class="action-btn like-btn" onclick="likeVideo('{{ $video->slug }}')">
                        ❤️ НРАВИТСЯ
                    </button>
                </div>
            </div>
        </div>

        <!-- Comments Section -->
        <section class="comments-section">
            <h2 class="section-title">
                <span>💬</span>
                КОММЕНТАРИИ 
                <span style="color: #aaa;">({{ $video->comments_count }})</span>
            </h2>
            
            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif
            
            @if($errors->any())
                <div class="alert alert-danger">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif
            
            <!-- Comment Form -->
            <form method="POST" action="{{ route('video.comment.store', $video->slug) }}" class="comment-form">
                @csrf
                
                <div class="form-group">
                    <label class="form-label">ВАШЕ ИМЯ (НЕОБЯЗАТЕЛЬНО)</label>
                    <input 
                        type="text" 
                        name="username" 
                        class="form-input" 
                        placeholder="Как вас зовут?" 
                        maxlength="50"
                        value="{{ old('username') }}"
                    >
                </div>
                
                <div class="form-group">
                    <label class="form-label">ВАШ КОММЕНТАРИЙ *</label>
                    <textarea 
                        name="content" 
                        class="form-input form-textarea" 
                        placeholder="Оставьте ваш комментарий..." 
                        required
                        minlength="3"
                        maxlength="1000"
                    >{{ old('content') }}</textarea>
                </div>
                
                <button type="submit" class="submit-btn">
                    📝 ОТПРАВИТЬ КОММЕНТАРИЙ
                </button>
                
                <div style="margin-top: 20px; color: #888; font-size: 0.9rem;">
                    💡 Все комментарии публикуются анонимно. Максимум 3 комментария в час с одного IP.
                </div>
            </form>
            
            <!-- Comments List -->
            <div class="comments-list">
                @forelse($comments as $comment)
                    <div class="comment-card">
                        <div class="comment-header">
                            <div class="comment-author">
                                <div class="author-avatar">
                                    {{-- mb_substr, а не substr: substr режет многобайтовую кириллицу пополам и даёт «�» --}}
                                    {{ mb_strtoupper(mb_substr((string) $comment->username, 0, 1)) }}
                                </div>
                                {{ $comment->username }}
                            </div>
                            <div class="comment-date">
                                {{ $comment->created_at->format('d.m.Y H:i') }}
                            </div>
                        </div>
                        <p class="comment-content">
                            {{ $comment->content }}
                        </p>
                    </div>
                @empty
                    <div class="no-comments">
                        <p>Пока нет комментариев. Будьте первым!</p>
                    </div>
                @endforelse
            </div>
        </section>
    </main>

    <!-- Fashion Footer -->
    <footer class="fashion-footer">
        <div class="footer-container">
            <div class="footer-stats">
                <div class="footer-stat">
                    <div class="footer-stat-value">{{ $stats['videos'] }}</div>
                    <div class="footer-stat-label">ВИДЕО</div>
                </div>
                <div class="footer-stat">
                    <div class="footer-stat-value">{{ number_format($stats['views']) }}</div>
                    <div class="footer-stat-label">ПРОСМОТРОВ</div>
                </div>
                <div class="footer-stat">
                    <div class="footer-stat-value">{{ $stats['comments'] }}</div>
                    <div class="footer-stat-label">КОММЕНТАРИЕВ</div>
                </div>
            </div>
            
            <a href="{{ route('video.index') }}" class="footer-logo">
                <span class="footer-logo-text">FASHION VIDEO</span>
            </a>
            
            <div class="footer-copyright">
                © {{ date('Y') }} Fashion Video. Все права защищены.
            </div>
        </div>
    </footer>

    <script>
        // Video Controls
        const video = document.getElementById('mainVideo');
        const playBtn = document.getElementById('playBtn');
        const volumeBtn = document.getElementById('volumeBtn');
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        
        // Play/Pause
        playBtn.addEventListener('click', () => {
            if (video.paused) {
                video.play();
                playBtn.textContent = '⏸';
            } else {
                video.pause();
                playBtn.textContent = '▶';
            }
        });
        
        video.addEventListener('play', () => playBtn.textContent = '⏸');
        video.addEventListener('pause', () => playBtn.textContent = '▶');
        
        // Volume
        volumeBtn.addEventListener('click', () => {
            video.muted = !video.muted;
            volumeBtn.textContent = video.muted ? '🔇' : '🔊';
        });
        
        // Fullscreen
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                if (video.requestFullscreen) {
                    video.requestFullscreen();
                } else if (video.webkitRequestFullscreen) {
                    video.webkitRequestFullscreen();
                }
                fullscreenBtn.textContent = '⛶';
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
                fullscreenBtn.textContent = '⛶';
            }
        }
        
        fullscreenBtn.addEventListener('click', toggleFullscreen);
        
        // Update fullscreen button on change
        document.addEventListener('fullscreenchange', updateFullscreenButton);
        document.addEventListener('webkitfullscreenchange', updateFullscreenButton);
        
        function updateFullscreenButton() {
            fullscreenBtn.textContent = document.fullscreenElement ? '⛶' : '⛶';
        }
        
        // Like Video
        async function likeVideo(slug) {
            const likeButton = document.querySelector('.like-btn');
            const likesCount = document.getElementById('likesCount');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            if (!csrfToken) {
                alert('CSRF токен не найден. Обновите страницу.');
                return;
            }

            try {
                likeButton.disabled = true;
                likeButton.textContent = '⏳ ОТПРАВКА...';

                const response = await fetch(`/video/${slug}/like`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (response.ok && data.success) {
                    if (likesCount && typeof data.likes !== 'undefined') {
                        likesCount.textContent = Number(data.likes).toLocaleString('ru-RU');
                    }
                    likeButton.textContent = '❤️ ЛАЙК ПОСТАВЛЕН';
                    alert('Спасибо за лайк!');
                    return;
                }

                likeButton.disabled = false;
                likeButton.textContent = '❤️ НРАВИТСЯ';
                alert(data.message || 'Вы уже лайкнули это видео');
            } catch (error) {
                console.error('Error:', error);
                likeButton.disabled = false;
                likeButton.textContent = '❤️ НРАВИТСЯ';
                alert('Ошибка при отправке лайка');
            }
        }
        
        // Ensure video always fits container
        function resizeVideo() {
            const wrapper = document.querySelector('.video-wrapper');
            const video = document.getElementById('mainVideo');
            
            // Reset styles
            video.style.width = '100%';
            video.style.height = '100%';
            video.style.objectFit = 'contain';
        }
        
        // Resize on load and resize
        window.addEventListener('load', resizeVideo);
        window.addEventListener('resize', resizeVideo);
        video.addEventListener('loadedmetadata', resizeVideo);
        
        // Initialize
        resizeVideo();
    </script>
</body>
</html>
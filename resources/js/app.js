import './bootstrap';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

let toastTimer;

function toast(message) {
    const node = document.querySelector('[data-toast]');

    if (!node) {
        return;
    }

    node.textContent = message;
    node.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => node.classList.remove('is-visible'), 2600);
}

function updateFavoriteCount(count) {
    document.querySelectorAll('[data-favorites-count]').forEach((node) => {
        node.textContent = String(count);
    });
}

async function postJson(url) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json',
        },
        credentials: 'same-origin',
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(payload.message || 'Не удалось выполнить действие');
    }

    return payload;
}

function initFavorites() {
    document.querySelectorAll('[data-favorite-url]').forEach((button) => {
        button.addEventListener('click', async (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (button.dataset.busy === '1') {
                return;
            }

            button.dataset.busy = '1';

            try {
                const payload = await postJson(button.dataset.favoriteUrl);
                const active = Boolean(payload.favorite);

                document.querySelectorAll(`[data-favorite-url="${button.dataset.favoriteUrl}"]`).forEach((node) => {
                    node.classList.toggle('is-active', active);
                    node.setAttribute('aria-pressed', active ? 'true' : 'false');
                    node.setAttribute('aria-label', active ? 'Убрать из избранного' : 'Добавить в избранное');

                    const label = node.querySelector('[data-favorite-label]');

                    if (label) {
                        label.textContent = active ? 'В избранном' : 'В избранное';
                    }
                });

                updateFavoriteCount(payload.count);
                toast(active ? 'Добавлено в избранное' : 'Удалено из избранного');
            } catch (error) {
                toast(error.message);
            } finally {
                button.dataset.busy = '0';
            }
        });
    });
}

function initLikes() {
    document.querySelectorAll('[data-like-button]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (button.dataset.busy === '1') {
                return;
            }

            button.dataset.busy = '1';

            try {
                const payload = await postJson(button.dataset.likeUrl);
                const count = button.querySelector('[data-like-count]');

                if (count) {
                    count.textContent = new Intl.NumberFormat('ru-RU').format(payload.likes);
                }

                button.classList.add('is-active');
                button.setAttribute('aria-pressed', 'true');
                toast('Спасибо! Лайк учтён.');
            } catch (error) {
                toast(error.message);
            } finally {
                button.dataset.busy = '0';
            }
        });
    });
}

function initPreviews() {
    if (window.matchMedia('(hover: none), (max-width: 767px)').matches) {
        return;
    }

    document.querySelectorAll('[data-video-card]').forEach((card) => {
        const media = card.querySelector('.video-card__media');
        const preview = card.querySelector('[data-preview-src]');

        if (!media || !preview) {
            return;
        }

        let timer;

        media.addEventListener('mouseenter', () => {
            timer = setTimeout(() => {
                if (!preview.src) {
                    preview.src = preview.dataset.previewSrc;
                }

                preview.play().catch(() => {});
            }, 300);
        });

        media.addEventListener('mouseleave', () => {
            clearTimeout(timer);
            preview.pause();
            preview.currentTime = 0;
        });
    });
}

function initMenu() {
    const toggle = document.querySelector('[data-menu-toggle]');
    const nav = document.querySelector('[data-mobile-nav]');

    if (!toggle || !nav) {
        return;
    }

    toggle.addEventListener('click', () => {
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        toggle.setAttribute('aria-label', open ? 'Открыть меню' : 'Закрыть меню');
        nav.hidden = open;
        document.body.classList.toggle('menu-open', !open);
    });
}

function initShare() {
    document.querySelectorAll('[data-share]').forEach((button) => {
        button.addEventListener('click', async () => {
            const data = {
                title: button.dataset.title || document.title,
                url: window.location.href,
            };

            try {
                if (navigator.share) {
                    await navigator.share(data);
                    return;
                }

                await navigator.clipboard.writeText(window.location.href);
                toast('Ссылка скопирована');
            } catch (error) {
                if (error.name !== 'AbortError') {
                    toast('Не удалось поделиться ссылкой');
                }
            }
        });
    });
}

function initCharCount() {
    const textarea = document.querySelector('.comment-form textarea');
    const counter = document.querySelector('[data-char-count]');

    if (!textarea || !counter) {
        return;
    }

    const update = () => {
        counter.textContent = `${textarea.value.length} / 1000`;
    };

    textarea.addEventListener('input', update);
    update();
}

function initVideoShortcuts() {
    const video = document.querySelector('#main-video');

    if (!video) {
        return;
    }

    document.addEventListener('keydown', (event) => {
        const target = event.target;

        if (target instanceof HTMLElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
            return;
        }

        if (event.code === 'Space') {
            event.preventDefault();
            video.paused ? video.play() : video.pause();
        }

        if (event.key.toLowerCase() === 'm') {
            video.muted = !video.muted;
            toast(video.muted ? 'Звук выключен' : 'Звук включён');
        }

        if (event.key.toLowerCase() === 'f') {
            if (document.fullscreenElement) {
                document.exitFullscreen?.();
            } else {
                video.requestFullscreen?.();
            }
        }

        if (event.key === 'ArrowRight') {
            video.currentTime = Math.min(video.duration || Infinity, video.currentTime + 5);
        }

        if (event.key === 'ArrowLeft') {
            video.currentTime = Math.max(0, video.currentTime - 5);
        }
    });
}

function initHeader() {
    const header = document.querySelector('[data-header]');

    if (!header) {
        return;
    }

    const update = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
    window.addEventListener('scroll', update, { passive: true });
    update();
}

document.addEventListener('DOMContentLoaded', () => {
    initMenu();
    initFavorites();
    initLikes();
    initPreviews();
    initShare();
    initCharCount();
    initVideoShortcuts();
    initHeader();
});

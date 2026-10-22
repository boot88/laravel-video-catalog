<?php

return [
    'name' => env('SITE_NAME', 'Fashion Video Hub'),
    'tagline' => 'Показы, кампании и портфолио моделей',
    'description' => 'Редакционная видеоплатформа о моде: показы, лукбуки, backstage, кампании и портфолио моделей.',
    'comments_require_moderation' => (bool) env('COMMENTS_REQUIRE_MODERATION', false),
    'contact_email' => env('SITE_CONTACT_EMAIL', 'hello@example.com'),
];

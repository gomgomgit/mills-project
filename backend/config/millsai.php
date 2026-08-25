<?php

/**
 * Mills AI chatbot widget — Aivena RAG backend project dedicated to Mills
 * Smart Log. Read by ChatbotWidget (resources/views/components/
 * chatbot-widget.blade.php) and rendered into the widget's inline
 * Alpine.js config via @js() — these values are exposed to the browser
 * by design (client-side widget, embed-level credential). See .env's own
 * comment on MILLS_AI_* for the full explanation.
 */
return [
    'base_url' => env('MILLS_AI_BASE_URL', 'https://demo-chatbot-api.prodemy.id/api/v1'),
    'project_id' => env('MILLS_AI_PROJECT_ID'),
    'auth_email' => env('MILLS_AI_AUTH_EMAIL'),
    'auth_password' => env('MILLS_AI_AUTH_PASSWORD'),
];

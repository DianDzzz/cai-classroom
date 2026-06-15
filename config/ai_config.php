<?php
// Ganti dengan API Key Gemini Anda dari https://aistudio.google.com/app/apikey
define('AI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');
define('AI_MODEL',   'gemini-1.5-flash');

function callGeminiAPI(string $system_prompt, string $user_message): string {
    if (!AI_API_KEY || AI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
        return 'API Key Gemini belum dikonfigurasi. Isi di config/ai_config.php.';
    }

    $url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . AI_MODEL
          . ':generateContent?key=' . AI_API_KEY;

    $body = json_encode([
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => $system_prompt . "\n\n" . $user_message]]],
        ],
        'generationConfig' => [
            'temperature'     => 0.7,
            'maxOutputTokens' => 1024,
        ],
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) return "Koneksi ke Gemini gagal: $err";

    $data = json_decode($res, true);

    if (isset($data['error'])) {
        return 'Gemini API error: ' . ($data['error']['message'] ?? 'Unknown error');
    }

    return $data['candidates'][0]['content']['parts'][0]['text']
        ?? 'Sevi AI tidak dapat memproses pertanyaan ini saat ini.';
}

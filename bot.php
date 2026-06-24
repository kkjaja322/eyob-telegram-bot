<?php

// ============================================================
//  CONFIGURATION — replace these two placeholders before use
// ============================================================
$botToken    = '8310843613:AAHeLa-5Kv59z1ikBIUR97w_k67C3_Ok1aA';
$geminiApiKey = 'AQ.Ab8RN6JOsVxbBNJ6qaKVcEQsUswggBISMG1OghKxmbLbAz7RMw';
// ============================================================

// ── Gemini endpoint ─────────────────────────────────────────
$geminiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $geminiApiKey;

// ── Telegram endpoint base ───────────────────────────────────
$telegramBase = 'https://api.telegram.org/bot' . $botToken;

// ── System instruction (Eyob's AI identity) ─────────────────
$systemInstruction = <<<'SYSPROMPT'
Identity & Context:
- You are not an AI assistant built by Google. You are the digital AI twin of Eyob Sisay, a talented full-stack web and mobile developer (expert in PHP, MySQL, Python, and Flutter), a cybersecurity enthusiast who studies ethical hacking, and a dedicated freshman Anesthesia student at Wollo University.
- You are responding to messages on Eyob's personal Telegram account via Telegram Business Chat Automation because Eyob is currently busy, attending classes, studying to maintain his high CGPA, or away from his phone.
- Your goal is to represent him seamlessly so that his friends, classmates, and contacts feel welcomed and supported.

Language & Tone:
- Speak strictly in modern, casual, conversational Amharic (የቤት ውስጥ እና የዩኒቨርሲቲ ተማሪዎች መደበኛ ያልሆነ ወሬ) as used by Ethiopian university students. Avoid sounding robotic, formal, or textbook-like.
- Be exceptionally friendly, warm, enthusiastic, empathetic, and witty.
- Keep your responses concise, engaging, and straight to the point. Long, text-heavy paragraphs should be avoided.
- Use friendly emojis and expressions naturally (e.g., "🤗", "😊", "የኔ ወንድም", "የኔ እህት", "አይዞህ/ሽ", "በጣም").

Knowledge Base Guidelines:
- If someone asks technical questions about coding, programming, system architecture, or ethical hacking/cybersecurity, respond enthusiastically and helpfully, reflecting Eyob's passion as a developer and tech content creator.
- If someone asks medical questions or mentions health/anesthesia, show solid knowledge in a friendly way, reflecting his field of study at Wollo University.
- If people ask personal questions like "Where are you?" or "What are you doing?", respond politely and playfully, stating: "አሁን እዮብ ትንሽ ቢዚ ስለሆነ/እያጠና ስለሆነ ነው፤ እኔ የራሱ AI ነኝ! መልዕክትህን/ሽን አስቀምጥልኝ፣ ልክ ሲመጣ ቀጥታ ያነበዋል 😊."

Strict Constraints & Rules:
- NEVER reveal that you are an AI model trained by Google, and NEVER mention the name "Gemini" or "OpenAI". If asked, you are strictly "የእዮብ AI" (Eyob's AI) built proprietary.
- Never use overly complex or formal language. Keep the vibe strictly peer-to-peer (like a supportive brother, friend, or classmate).
- Politely pivot away from or decline to engage in negative, political, or toxic topics while maintaining a positive and warm demeanor.
SYSPROMPT;

// ── Fallback message (shown if Gemini call fails) ────────────
$fallbackMessage = 'ይቅርታ፣ አሁን ትንሽ ችግር አለ 😅 እዮብ ሲመጣ ይነጋገርሃል/ሽ!';

// ============================================================
//  1. READ & DECODE THE INCOMING WEBHOOK PAYLOAD
// ============================================================
$rawInput = file_get_contents('php://input');

if (empty($rawInput)) {
    http_response_code(200);
    exit;
}

$update = json_decode($rawInput, true);

if (!$update || !isset($update['business_message'])) {
    // Not a business message — acknowledge silently and exit
    http_response_code(200);
    exit;
}

$businessMessage      = $update['business_message'];
$businessConnectionId = $businessMessage['business_connection_id'] ?? '';
$chatId               = $businessMessage['chat']['id']             ?? '';
$userText             = $businessMessage['text']                   ?? '';

// If there is no text (e.g. sticker, photo), skip
if (empty($userText) || empty($chatId) || empty($businessConnectionId)) {
    http_response_code(200);
    exit;
}

// ============================================================
//  2. CALL THE GEMINI API
// ============================================================
$geminiPayload = json_encode([
    'system_instruction' => [
        'parts' => [
            ['text' => $systemInstruction]
        ]
    ],
    'contents' => [
        [
            'role'  => 'user',
            'parts' => [
                ['text' => $userText]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature'     => 0.85,
        'maxOutputTokens' => 512,
    ]
], JSON_UNESCAPED_UNICODE);

$ch = curl_init($geminiEndpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_POSTFIELDS,     $geminiPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);   // Required on InfinityFree
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);   // Required on InfinityFree
curl_setopt($ch, CURLOPT_TIMEOUT,        15);

$geminiRaw  = curl_exec($ch);
$curlErrno  = curl_errno($ch);
curl_close($ch);

$replyText = $fallbackMessage; // default

if (!$curlErrno && !empty($geminiRaw)) {
    $geminiResponse = json_decode($geminiRaw, true);

    $replyText = $geminiResponse['candidates'][0]['content']['parts'][0]['text']
                 ?? $fallbackMessage;
}

// ============================================================
//  3. SEND THE REPLY VIA TELEGRAM sendMessage
// ============================================================
$telegramPayload = json_encode([
    'business_connection_id' => $businessConnectionId,
    'chat_id'                => $chatId,
    'text'                   => $replyText,
    'parse_mode'             => 'HTML',
], JSON_UNESCAPED_UNICODE);

$tg = curl_init($telegramBase . '/sendMessage');
curl_setopt($tg, CURLOPT_RETURNTRANSFER, true);
curl_setopt($tg, CURLOPT_POST,           true);
curl_setopt($tg, CURLOPT_POSTFIELDS,     $telegramPayload);
curl_setopt($tg, CURLOPT_HTTPHEADER,     ['Content-Type: application/json']);
curl_setopt($tg, CURLOPT_SSL_VERIFYPEER, false);   // Required on InfinityFree
curl_setopt($tg, CURLOPT_SSL_VERIFYHOST, false);   // Required on InfinityFree
curl_setopt($tg, CURLOPT_TIMEOUT,        10);

curl_exec($tg);
curl_close($tg);

// ── Always return 200 to Telegram so it doesn't retry ───────
http_response_code(200);
exit;

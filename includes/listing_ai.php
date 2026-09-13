<?php
require_once __DIR__ . '/settings.php';

/**
 * Calls the Anthropic Messages API to draft a listing title/description from whatever the seller
 * has typed so far (a few keywords, or nothing at all beyond category/condition). Returns null on
 * any failure (bad key, API error, unparseable response) — callers should treat that as "try
 * again," not fatal.
 */
function claude_generate_listing_draft(string $notes, string $category, string $condition, string $gradeLevel, string $model, string $apiKey): ?array
{
    $conditionLabels = [
        'new' => 'New', 'like_new' => 'Like New', 'good' => 'Good',
        'fair' => 'Fair', 'digital_download' => 'Digital Download',
    ];
    $conditionLabel = $conditionLabels[$condition] ?? $condition;

    $prompt = <<<PROMPT
You write concise, appealing marketplace listings for MarketplaceForTeachers.com, a site where K-12 teachers buy, sell, and donate classroom supplies to each other.

A seller is posting an item and gave you these details:
- Notes from the seller: {$notes}
- Category: {$category}
- Condition: {$conditionLabel}
- Grade level: {$gradeLevel}

Write a listing title and description based ONLY on the details above — do not invent specifics (brand names, exact quantities, dimensions) that weren't mentioned. If the seller's notes are sparse, write a shorter, more general description rather than padding it with invented details.

Respond with ONLY a JSON object, no other text, no markdown code fence, in exactly this shape:
{"title": "...", "description": "..."}

"title" is under 70 characters, no quotation marks, no emoji. "description" is 2-4 plain-text sentences (no markdown, no headings) that would help a fellow teacher decide to buy it — mention condition and grade fit naturally if relevant.
PROMPT;

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $model,
            'max_tokens' => 500,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]),
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $status >= 400) {
        return null;
    }
    $data = json_decode($response, true);
    $text = $data['content'][0]['text'] ?? null;
    if (!$text) {
        return null;
    }

    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false) {
        return null;
    }
    $parsed = json_decode(substr($text, $start, $end - $start + 1), true);
    if (!$parsed || empty($parsed['title']) || empty($parsed['description'])) {
        return null;
    }
    return [
        'title' => trim($parsed['title']),
        'description' => trim($parsed['description']),
    ];
}

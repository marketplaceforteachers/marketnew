<?php
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/helpers.php';

/**
 * Free, keyless "deep search" source: Google News RSS filtered to a topic query. Returns recent
 * headlines relevant to teaching/education — no API key, no subscription.
 */
function fetch_education_news(string $query, int $limit = 8): array
{
    $url = 'https://news.google.com/rss/search?q=' . urlencode($query) . '&hl=en-US&gl=US&ceid=US:en';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; MarketplaceForTeachersBot/1.0)',
    ]);
    $xml = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$xml || $status >= 400) {
        return [];
    }

    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);
    if (!$feed) {
        return [];
    }

    $items = [];
    foreach ($feed->channel->item as $item) {
        $items[] = [
            'title' => trim((string) $item->title),
            'link' => trim((string) $item->link),
            'pubDate' => trim((string) $item->pubDate),
            'summary' => trim(strip_tags((string) $item->description)),
        ];
        if (count($items) >= $limit) break;
    }
    return $items;
}

/**
 * Calls the Anthropic Messages API to draft a blog post from a single news headline. Deliberately
 * instructs the model to write commentary/analysis FOR TEACHERS inspired by the headline rather
 * than claiming to report the full story — we only ever hand it a headline + one-line RSS
 * summary, not the source article's full text, so asking it to "report" the story would invite
 * fabricated specifics. Returns null on any failure (bad key, API error, unparseable response) —
 * callers should treat that as "try again later," not fatal.
 */
function claude_generate_blog_draft(array $newsItem, string $topics, string $model, string $apiKey): ?array
{
    $prompt = <<<PROMPT
You write for the blog of MarketplaceForTeachers.com, a marketplace where K-12 teachers buy, sell, and donate classroom supplies. Your audience is working teachers.

A news headline crossed the wire:
Headline: {$newsItem['title']}
One-line summary (may be incomplete): {$newsItem['summary']}
Source: {$newsItem['link']}

Write a short, useful blog post FOR TEACHERS that responds to or is inspired by this headline — practical takeaways, classroom-budget angles, or what it means for their day-to-day. You were only given the headline and a one-line summary, not the full article, so do NOT invent specific facts, statistics, quotes, or details beyond what's given above — write commentary and general, defensible advice instead, and it's fine to say "according to this report" rather than restating specifics as your own claims.

The site's general topic focus is: {$topics}

Formatting rules — this is NOT rendered as raw HTML, it goes through a small safe converter that only understands:
- Blank lines between paragraphs
- "## Heading" and "### Subheading" on their own line
- "- item" for bullet lists
- **bold** and *italic*
- [link text](https://...) — https URLs only

Write 350-550 words. Do not use any other formatting. Do not include a title inside the content — that goes in a separate field.

Respond with ONLY a JSON object, no other text, no markdown code fence, in exactly this shape:
{"title": "...", "excerpt": "...", "content": "..."}

"title" is a compelling headline under 70 characters. "excerpt" is a 1-2 sentence teaser under 200 characters. "content" is the full post body using the formatting rules above.
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
            'max_tokens' => 1500,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]),
        CURLOPT_TIMEOUT => 60,
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

    // Models sometimes wrap JSON in a code fence despite instructions not to — strip it, then
    // take the substring between the first "{" and last "}" as a defensive fallback.
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $text);
    $start = strpos($text, '{');
    $end = strrpos($text, '}');
    if ($start === false || $end === false) {
        return null;
    }
    $parsed = json_decode(substr($text, $start, $end - $start + 1), true);
    if (!$parsed || empty($parsed['title']) || empty($parsed['content'])) {
        return null;
    }
    return [
        'title' => trim($parsed['title']),
        'excerpt' => trim($parsed['excerpt'] ?? ''),
        'content' => trim($parsed['content']),
    ];
}

/**
 * Full pipeline: pick a topic, search news, pick a headline not already covered, draft it with
 * Claude, save as a DRAFT (never published automatically — see admin/blog.php, which is where a
 * human reviews and publishes). Returns ['status' => 'created'|'skipped'|'error', 'message' => ...].
 */
function generate_blog_post_draft(): array
{
    $config = get_anthropic_config();
    if (empty($config['isEnabled']) || empty($config['apiKey'])) {
        return ['status' => 'error', 'message' => 'The AI auto-writer is not configured yet — add an Anthropic API key below and enable it.'];
    }

    $topicList = array_filter(array_map('trim', explode(',', $config['topics'])));
    if (!$topicList) {
        $topicList = ['K-12 classroom teaching'];
    }
    $topic = $topicList[array_rand($topicList)];

    $newsItems = fetch_education_news($topic, 8);
    if (!$newsItems) {
        return ['status' => 'error', 'message' => "Couldn't fetch news for topic \"$topic\" — the news source may be unreachable right now."];
    }

    // Skip anything we've already drafted a post from, so re-running doesn't produce duplicates.
    $stmt = db()->query('SELECT source_url FROM blog_posts WHERE source_url IS NOT NULL');
    $usedUrls = array_flip($stmt->fetchAll(PDO::FETCH_COLUMN));

    $candidate = null;
    foreach ($newsItems as $item) {
        if (!isset($usedUrls[$item['link']])) {
            $candidate = $item;
            break;
        }
    }
    if (!$candidate) {
        return ['status' => 'skipped', 'message' => "All recent headlines for \"$topic\" have already been covered — try again later or add another topic."];
    }

    $draft = claude_generate_blog_draft($candidate, $config['topics'], $config['model'], $config['apiKey']);
    if (!$draft) {
        return ['status' => 'error', 'message' => 'The AI didn\'t return a usable draft (check the API key, or try again — this can happen on an occasional bad response).'];
    }

    $slug = slugify($draft['title']);
    db()->prepare(
        'INSERT INTO blog_posts (title, slug, excerpt, content, author_name, status, source, source_url)
         VALUES (?, ?, ?, ?, ?, \'draft\', \'ai_generated\', ?)'
    )->execute([
        $draft['title'],
        $slug,
        truncate($draft['excerpt'] ?: blog_plain_excerpt($draft['content']), 300),
        $draft['content'],
        'MarketplaceForTeachers.com Team',
        $candidate['link'],
    ]);

    return ['status' => 'created', 'message' => "Draft created: \"{$draft['title']}\" — review it in the list below before publishing.", 'slug' => $slug];
}

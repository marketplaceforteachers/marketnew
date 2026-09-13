<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing_ai.php';
header('Content-Type: application/json');

$me = require_auth();

$config = get_anthropic_config();
if (empty($config['isEnabled']) || empty($config['apiKey'])) {
    json_response(['error' => 'The AI writing assistant is not set up yet — an admin needs to add an Anthropic API key in Admin → Blog.'], 503);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$notes = trim((string) ($input['notes'] ?? ''));
$category = trim((string) ($input['category'] ?? ''));
$condition = trim((string) ($input['condition'] ?? ''));
$gradeLevel = trim((string) ($input['gradeLevel'] ?? ''));

if ($notes === '' && $category === '') {
    json_response(['error' => 'Type a few keywords about the item first (or at least pick a category).'], 422);
}

$draft = claude_generate_listing_draft($notes, $category, $condition, $gradeLevel, $config['model'], $config['apiKey']);
if (!$draft) {
    json_response(['error' => "The AI didn't return a usable draft — try again, or check the API key in Admin → Blog."], 502);
}

json_response($draft);

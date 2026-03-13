<?php

// Get environment variables from .env file if it exists
if (file_exists('.env')) {
  $_ENV = parse_ini_file('.env');
}


// Prevent PHP warnings and deprecation notices
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);


// Set defaults
const UPVOTE_RSS_VERSION            = '1.8.1';
const UPVOTE_RSS_CACHE_ROOT         = __DIR__ . '/cache/';
const DEFAULT_PLATFORM              = 'lemmy';
const DEFAULT_GITHUB_INSTANCE       = 'github.com';
const DEFAULT_GITHUB_COMMUNITY      = 'all';
const DEFAULT_GITHUB_SCORE          = 10;
const DEFAULT_HACKER_NEWS_INSTANCE  = 'news.ycombinator.com';
const DEFAULT_HACKER_NEWS_COMMUNITY = 'beststories';
const DEFAULT_HACKER_NEWS_SCORE     = 100;
const DEFAULT_LEMMY_INSTANCE        = 'lemmy.world';
const DEFAULT_LEMMY_COMMUNITY       = 'Technology';
const DEFAULT_LEMMY_SCORE           = 100;
const DEFAULT_PIEFED_INSTANCE       = 'piefed.social';
const DEFAULT_PIEFED_COMMUNITY      = 'goodnewseveryone';
const DEFAULT_PIEFED_SCORE          = 50;
const DEFAULT_LOBSTERS_INSTANCE     = 'lobste.rs';
const DEFAULT_LOBSTERS_COMMUNITY    = 'all';
const DEFAULT_LOBSTERS_CATEGORY     = 'compsci';
const DEFAULT_LOBSTERS_TAG          = 'programming';
const DEFAULT_LOBSTERS_SCORE        = 50;
const DEFAULT_MBIN_INSTANCE         = 'kbin.earth';
const DEFAULT_MBIN_COMMUNITY        = 'technology@lemmy.world';
const DEFAULT_MBIN_SCORE            = 100;
const DEFAULT_REDDIT_INSTANCE       = 'reddit.com';
const DEFAULT_SUBREDDIT             = 'technology';
const DEFAULT_REDDIT_SCORE          = 100;


// Debug
$debug = $_SERVER["DEBUG"] ?? $_ENV["DEBUG"] ?? false;
if ($debug) {
  ini_set('display_errors', '0');
}
define('DEBUG', $debug);


// Maximum execution time
$max_execution_time = $_SERVER["MAX_EXECUTION_TIME"] ?? $_ENV["MAX_EXECUTION_TIME"] ?? 60;
define('MAX_EXECUTION_TIME', $max_execution_time);
if (
  !empty(MAX_EXECUTION_TIME)
  && is_numeric(MAX_EXECUTION_TIME)
  && MAX_EXECUTION_TIME > 0
) {
  ini_set('max_execution_time', MAX_EXECUTION_TIME);
}


// Timezone
$timezone = $_SERVER["TZ"] ?? $_ENV["TZ"] ?? 'Europe/London';
date_default_timezone_set($timezone);


// Upvote RSS URI (proxy aware)
$scheme = 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
  $scheme_parts = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO']);
  $scheme_candidate = strtolower(trim($scheme_parts[0]));
  if (in_array($scheme_candidate, ['http', 'https'])) $scheme = $scheme_candidate;
} elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
  $scheme_candidate = strtolower($_SERVER['REQUEST_SCHEME']);
  if (in_array($scheme_candidate, ['http', 'https'])) $scheme = $scheme_candidate;
} elseif (
  (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1))
  || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
) {
  $scheme = 'https';
}

$host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
  $host_parts = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
  $host = trim($host_parts[0]);
}

$script_dir = rtrim(dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');
$script_dir = $script_dir ? $script_dir . '/' : '/';

$upvote_rss_uri = $scheme . '://' . $host . $script_dir;
define('UPVOTE_RSS_URI', $upvote_rss_uri);


// Variables
$instance = null;
$community = !empty($_GET["community"]) ? strip_tags(trim($_GET["community"])) : null;
$community_type = !empty($_GET["type"]) ? strip_tags(trim($_GET["type"])) : null;
$filter_type = 'averagePostsPerDay';
$filter_value = null;
$score = 100;


// Demo mode
$demo_mode = false;
if (isset($_SERVER["DEMO_MODE"]) && $_SERVER["DEMO_MODE"] == true) {
  $demo_mode = true;
} elseif (isset($_ENV["DEMO_MODE"]) && $_ENV["DEMO_MODE"] == true) {
  $demo_mode = true;
}
define('DEMO_MODE', $demo_mode);


// Platform
$platform = !empty($_GET["platform"]) ? strip_tags(trim($_GET["platform"])) : DEFAULT_PLATFORM;
if (DEMO_MODE && $platform == 'reddit') $platform = DEFAULT_PLATFORM;
if (!empty($_GET["subreddit"])) $platform = 'reddit';
define('PLATFORM', $platform);


// Auth
$encryption_key = hash('sha256', date('Y-m-d', strtotime('-1 month')));
define('ENCRYPTION_KEY', $encryption_key);
define('CIPHERING', "AES-128-CTR");
define('IV_LENGTH', openssl_cipher_iv_length(CIPHERING));
$encryption_iv = substr(hash('sha256', date('Y-m-d', strtotime('+1 month'))), 0, 16);
define('ENCRYPTION_IV', $encryption_iv);
define('ENCRYPTION_OPTIONS', 0);


// Reddit
if (PLATFORM == 'reddit') {
  $score = DEFAULT_REDDIT_SCORE;
}
// Username
$reddit_user = $_SERVER["REDDIT_USER"] ?? $_ENV["REDDIT_USER"] ?? null;
$reddit_user = !empty($_GET["reddit_user"]) ? strip_tags(trim($_GET["reddit_user"])) : $reddit_user;
define('REDDIT_USER', $reddit_user);
// Client ID
$reddit_client_id = $_SERVER["REDDIT_CLIENT_ID"] ?? $_ENV["REDDIT_CLIENT_ID"] ?? null;
$reddit_client_id = !empty($_GET["reddit_client_id"]) ? strip_tags(trim($_GET["reddit_client_id"])) : $reddit_client_id;
define('REDDIT_CLIENT_ID', $reddit_client_id);
$client_id_encrypted = null;
if (REDDIT_CLIENT_ID !== null) {
  $client_id_encrypted = openssl_encrypt(REDDIT_CLIENT_ID, CIPHERING, ENCRYPTION_KEY, ENCRYPTION_OPTIONS, ENCRYPTION_IV);
  $client_id_encrypted = base64_encode($client_id_encrypted);
}
define('REDDIT_CLIENT_ID_ENCRYPTED', $client_id_encrypted);
// Client Secret
$reddit_client_secret = $_SERVER["REDDIT_CLIENT_SECRET"] ?? $_ENV["REDDIT_CLIENT_SECRET"] ?? null;
$reddit_client_secret = !empty($_GET["reddit_client_secret"]) ? strip_tags(trim($_GET["reddit_client_secret"])) : $reddit_client_secret;
define('REDDIT_CLIENT_SECRET', $reddit_client_secret);
// Reddit domain
define('REDDIT_DEFAULT_DOMAIN', 'www.reddit.com');
define('REDDIT_DEFAULT_DOMAIN_OVERRIDE', 'old.reddit.com');
$reddit_domain = $_GET["redditDomain"] ?? REDDIT_DEFAULT_DOMAIN;
define('REDDIT_DOMAIN', $reddit_domain);
// Subreddit
$subreddit = DEFAULT_SUBREDDIT;
if (!empty($_GET["subreddit"])) {
  $subreddit = strip_tags(trim($_GET["subreddit"]));
  $instance = 'reddit.com';
  $community = '';
}
define('SUBREDDIT', $subreddit);


// Hacker News
if (PLATFORM == 'hacker-news') {
  $instance = DEFAULT_HACKER_NEWS_INSTANCE;
  $score = DEFAULT_HACKER_NEWS_SCORE;
  if (empty($community)) {
    if (!empty($_GET["type"])) $community = strip_tags(trim($_GET["type"]));
    else $community = DEFAULT_HACKER_NEWS_COMMUNITY;
  }
}


// Lemmy
if (PLATFORM == 'lemmy') {
  $instance = !empty($_GET["instance"]) ? strip_tags(trim($_GET["instance"])) : DEFAULT_LEMMY_INSTANCE;
  $community = !empty($_GET["community"]) ? strip_tags(trim($_GET["community"])) : DEFAULT_LEMMY_COMMUNITY;
  $score = DEFAULT_LEMMY_SCORE;
}

// PieFed
if (PLATFORM == 'piefed') {
  $instance = !empty($_GET["instance"]) ? strip_tags(trim($_GET["instance"])) : DEFAULT_PIEFED_INSTANCE;
  $community = !empty($_GET["community"]) ? strip_tags(trim($_GET["community"])) : DEFAULT_PIEFED_COMMUNITY;
  $score = DEFAULT_PIEFED_SCORE;
}


// Lobsters
if (PLATFORM == 'lobsters') {
  $instance = !empty($_GET["instance"]) ? strip_tags(trim($_GET["instance"])) : DEFAULT_LOBSTERS_INSTANCE;
  $community_type = $community_type ?? DEFAULT_LOBSTERS_COMMUNITY;
  if (empty($community) || $community_type == DEFAULT_LOBSTERS_COMMUNITY) {
    $community = DEFAULT_LOBSTERS_COMMUNITY;
    $community_type = DEFAULT_LOBSTERS_COMMUNITY;
  }
  $score = DEFAULT_LOBSTERS_SCORE;
}


// Mbin
if (PLATFORM == 'mbin') {
  $instance = !empty($_GET["instance"]) ? strip_tags(trim($_GET["instance"])) : DEFAULT_MBIN_INSTANCE;
  $community = !empty($_GET["community"]) ? strip_tags(trim($_GET["community"])) : DEFAULT_MBIN_COMMUNITY;
  $score = DEFAULT_MBIN_SCORE;
}


// GitHub
if (PLATFORM == 'github') {
  $instance = DEFAULT_GITHUB_INSTANCE;
  $community = DEFAULT_GITHUB_COMMUNITY;
  $score = DEFAULT_GITHUB_SCORE;
}


// Instance
define('INSTANCE', $instance);


// Community
define('COMMUNITY', $community);


// Community type
define('COMMUNITY_TYPE', $community_type);


// Category
$category = null;
if (!empty($_GET["category"])) {
  $category = strip_tags(trim($_GET["category"]));
} elseif (PLATFORM == 'lobsters' && COMMUNITY_TYPE == 'category' && !empty($_GET["community"])) {
  $category = strip_tags(trim($_GET["community"]));
}
define('CATEGORY', $category);


// Tag
$tag = null;
if (!empty($_GET["tag"])) {
  $tag = strip_tags(trim($_GET["tag"]));
} elseif (PLATFORM == 'lobsters' && COMMUNITY_TYPE == 'tag' && !empty($_GET["community"])) {
  $tag = strip_tags(trim($_GET["community"]));
}
define('TAG', $tag);


// Language
$language = !empty($_GET["language"]) ? strip_tags(trim($_GET["language"])) : '';
define('LANGUAGE', $language);


// Topic
$topic = !empty($_GET["topic"]) ? strip_tags(trim($_GET["topic"])) : '';
define('TOPIC', $topic);


// Query
$query = null;
if (!empty($_GET["query"])) {
  $query = strip_tags(trim($_GET["query"]));
}
define('QUERY', $query);


// Filters Available
define('SCORE_FILTER_AVAILABLE_PLATFORMS', ['github', 'hacker-news', 'lemmy', 'lobsters', 'mbin', 'piefed', 'reddit']);
define('SCORE_FILTER_AVAILABLE', in_array(PLATFORM, SCORE_FILTER_AVAILABLE_PLATFORMS));
define('THRESHOLD_FILTER_AVAILABLE_PLATFORMS', ['github', 'hacker-news', 'lemmy', 'mbin', 'piefed', 'reddit']);
define('THRESHOLD_FILTER_AVAILABLE', in_array(PLATFORM, THRESHOLD_FILTER_AVAILABLE_PLATFORMS));
define('AVERAGE_POSTS_PER_DAY_FILTER_AVAILABLE_PLATFORMS', ['github', 'hacker-news', 'lemmy', 'mbin', 'piefed', 'reddit']);
define('AVERAGE_POSTS_PER_DAY_FILTER_AVAILABLE', in_array(PLATFORM, AVERAGE_POSTS_PER_DAY_FILTER_AVAILABLE_PLATFORMS));


// Score
if (isset($_GET["score"])) {
  $filter_type = 'score';
  $score = strip_tags(trim($_GET["score"]));
  $score = (int)$score;
  if (!is_numeric($score)) $score = 0;
  $filter_value = $score;
}
define('SCORE', $score);


// Treshold
$percentage = 100;
if (!empty($_GET["threshold"])) {
  $filter_type = 'threshold';
  $percentage = strip_tags(trim($_GET["threshold"]));
  $filter_value = $percentage;
}
define('PERCENTAGE', $percentage);


// Posts per day
$average_posts_per_day = 3;
if (isset($_GET["averagePostsPerDay"])) {
  $filter_type = 'averagePostsPerDay';
  $average_posts_per_day = strip_tags(trim($_GET["averagePostsPerDay"]));
  $filter_value = $average_posts_per_day;
}
define('POSTS_PER_DAY', $average_posts_per_day);


// Filter type
define('FILTER_TYPE', $filter_type);


// Filter value
define('FILTER_VALUE', $filter_value);


// Show score
$show_score = false;
if (isset($_GET["showScore"])) $show_score = true;
define('SHOW_SCORE', $show_score);


// Include content
$include_content = true;
if (isset($_GET["content"]) && $_GET["content"] == 0) {
  $include_content = false;
}
define('INCLUDE_CONTENT', $include_content);


// Ollama API
$ollama_url = $_SERVER["OLLAMA_URL"] ?? $_ENV["OLLAMA_URL"] ?? null;
define('OLLAMA_URL', $ollama_url);
$ollama_model = $_SERVER["OLLAMA_MODEL"] ?? $_ENV["OLLAMA_MODEL"] ?? null;
define('OLLAMA_MODEL', $ollama_model);


// Google Gemini API
$gemini_api_key = $_SERVER["GOOGLE_GEMINI_API_KEY"] ?? $_ENV["GOOGLE_GEMINI_API_KEY"] ?? null;
define('GOOGLE_GEMINI_API_KEY', $gemini_api_key);
$gemini_api_model = $_SERVER["GOOGLE_GEMINI_API_MODEL"] ?? $_ENV["GOOGLE_GEMINI_API_MODEL"] ?? 'gemini-2.5-flash';
define('GOOGLE_GEMINI_API_MODEL', $gemini_api_model);


// OpenAI API
$openai_api_key = $_SERVER["OPENAI_API_KEY"] ?? $_ENV["OPENAI_API_KEY"] ?? null;
define('OPENAI_API_KEY', $openai_api_key);
$openai_api_model = $_SERVER["OPENAI_API_MODEL"] ?? $_ENV["OPENAI_API_MODEL"] ?? 'gpt-4o-mini';
define('OPENAI_API_MODEL', $openai_api_model);

// Anthropic API
$anthropic_api_key = $_SERVER["ANTHROPIC_API_KEY"] ?? $_ENV["ANTHROPIC_API_KEY"] ?? null;
define('ANTHROPIC_API_KEY', $anthropic_api_key);
$anthropic_api_model = $_SERVER["ANTHROPIC_API_MODEL"] ?? $_ENV["ANTHROPIC_API_MODEL"] ?? 'claude-3-haiku-20240307';
define('ANTHROPIC_API_MODEL', $anthropic_api_model);

// Mistral API
$mistral_api_key = $_SERVER["MISTRAL_API_KEY"] ?? $_ENV["MISTRAL_API_KEY"] ?? null;
define('MISTRAL_API_KEY', $mistral_api_key);
$mistral_api_model = $_SERVER["MISTRAL_API_MODEL"] ?? $_ENV["MISTRAL_API_MODEL"] ?? 'mistral-small-latest';
define('MISTRAL_API_MODEL', $mistral_api_model);

// DeepSeek API
$deepseek_api_key = $_SERVER["DEEPSEEK_API_KEY"] ?? $_ENV["DEEPSEEK_API_KEY"] ?? null;
define('DEEPSEEK_API_KEY', $deepseek_api_key);
$deepseek_api_model = $_SERVER["DEEPSEEK_API_MODEL"] ?? $_ENV["DEEPSEEK_API_MODEL"] ?? 'deepseek-chat';
define('DEEPSEEK_API_MODEL', $deepseek_api_model);

// OpenAI Compatible API
$openai_compatible_url = $_SERVER["OPENAI_COMPATIBLE_URL"] ?? $_ENV["OPENAI_COMPATIBLE_URL"] ?? null;
define('OPENAI_COMPATIBLE_URL', $openai_compatible_url);
$openai_compatible_api_key = $_SERVER["OPENAI_COMPATIBLE_API_KEY"] ?? $_ENV["OPENAI_COMPATIBLE_API_KEY"] ?? null;
define('OPENAI_COMPATIBLE_API_KEY', $openai_compatible_api_key);
$openai_compatible_api_model = $_SERVER["OPENAI_COMPATIBLE_API_MODEL"] ?? $_ENV["OPENAI_COMPATIBLE_API_MODEL"] ?? null;
define('OPENAI_COMPATIBLE_API_MODEL', $openai_compatible_api_model);


// Summary enabled
$summary_enabled = match (true) {
  !empty(OLLAMA_URL) && !empty(OLLAMA_MODEL) => true,
  !empty(GOOGLE_GEMINI_API_KEY) => true,
  !empty(OPENAI_API_KEY) => true,
  !empty(ANTHROPIC_API_KEY) => true,
  !empty(MISTRAL_API_KEY) => true,
  !empty(DEEPSEEK_API_KEY) => true,
  !empty(OPENAI_COMPATIBLE_URL) && !empty(OPENAI_COMPATIBLE_API_MODEL) => true,
  default => false
};
define('SUMMARY_ENABLED', $summary_enabled);


// Summary
$include_summary = false;
if (isset($_GET["summary"]) && SUMMARY_ENABLED) $include_summary = true;
define('INCLUDE_SUMMARY', $include_summary);


// Summary prompt
$summary_system_prompt = "You are web article summarizer. Use the following pieces of retrieved context to answer the question. Do not answer from your own knowledge base. If the answer isn't present in the knowledge base, refrain from providing an answer based on your own knowledge. Instead, say nothing. Output should be limited to one paragraph with a maximum of three sentences, and keep the answer concise. Always complete the last sentence. Do not hallucinate or make up information. Do not include information about the article's publisher or author unless they have some bearing on the story.";
$summary_system_prompt = $_SERVER["SUMMARY_SYSTEM_PROMPT"] ?? $_ENV["SUMMARY_SYSTEM_PROMPT"] ?? $summary_system_prompt;
define('SUMMARY_SYSTEM_PROMPT', $summary_system_prompt);


// Summary temperature
$summary_temperature = $_SERVER["SUMMARY_TEMPERATURE"] ?? $_ENV["SUMMARY_TEMPERATURE"] ?? 0.4;
$summary_temperature = floatval($summary_temperature);
if (!($summary_temperature > 0 && $summary_temperature <= 1)) {
  $summary_temperature = 0.4;
}
define('SUMMARY_TEMPERATURE', $summary_temperature);


// Summary max tokens
$summary_max_tokens = $_SERVER["SUMMARY_MAX_TOKENS"] ?? $_ENV["SUMMARY_MAX_TOKENS"] ?? 1000;
$summary_max_tokens = intval($summary_max_tokens);
if ($summary_max_tokens < 0) {
  $summary_max_tokens = 1000;
}
define('SUMMARY_MAX_TOKENS', $summary_max_tokens);


// Comments
$include_comments = false;
$comments = 0;
if (!empty($_GET["comments"])) {
  $include_comments = true;
  $comments = strip_tags(trim($_GET["comments"]));
}
define('INCLUDE_COMMENTS', $include_comments);
define('COMMENTS', $comments);
define('COMMENTS_AVAILABLE_PLATFORMS', ['hacker-news', 'lemmy', 'lobsters', 'mbin', 'piefed', 'reddit']);
define('COMMENTS_AVAILABLE', in_array(PLATFORM, COMMENTS_AVAILABLE_PLATFORMS));
define('PINNED_COMMENTS_AVAILABLE_PLATFORMS', ['lemmy', 'piefed', 'reddit']);
define('PINNED_COMMENTS_FILTER_AVAILABLE', in_array(PLATFORM, PINNED_COMMENTS_AVAILABLE_PLATFORMS));
$filter_pinned_comments = false;
if (isset($_GET["filterPinnedComments"]) && INCLUDE_COMMENTS) $filter_pinned_comments = true;
define('FILTER_PINNED_COMMENTS', $filter_pinned_comments);


// NSFW
$filter_nsfw = false;
$blur_nsfw = false;
if (isset($_GET["filterNSFW"])) $filter_nsfw = true;
if (isset($_GET["blurNSFW"])) $blur_nsfw = true;
define('FILTER_NSFW', $filter_nsfw);
define('BLUR_NSFW', $blur_nsfw);


// Filter old posts
$filter_old_posts = false;
$post_cutoff_days = 0;
if (isset($_GET["filterOldPosts"]) && $_GET["filterOldPosts"]) {
  $filter_old_posts = true;
  $post_cutoff_days = strip_tags(trim($_GET["filterOldPosts"]));
  $post_cutoff_days = is_numeric($_GET["filterOldPosts"]) ? $_GET["filterOldPosts"] : 0;
}
define('FILTER_OLD_POSTS', $filter_old_posts);
define('POST_CUTOFF_DAYS', $post_cutoff_days);


// Browserless
$browserless_url = $_SERVER["BROWSERLESS_URL"] ?? $_ENV["BROWSERLESS_URL"] ?? '';
$browserless_url = rtrim($browserless_url, '/');
$browserless_token = $_SERVER["BROWSERLESS_TOKEN"] ?? $_ENV["BROWSERLESS_TOKEN"] ?? '';
define('BROWSERLESS_URL', $browserless_url);
define('BROWSERLESS_TOKEN', $browserless_token);


// Readability-JS
$readability_js_url = $_SERVER["READABILITY_JS_URL"] ?? $_ENV["READABILITY_JS_URL"] ?? '';
define('READABILITY_JS_URL', $readability_js_url);


// Mercury Parser URL, e.g. https://mercuryparser.example.com
$mercury_url = $_SERVER["MERCURY_URL"] ?? $_ENV["MERCURY_URL"] ?? null;
define('MERCURY_URL', $mercury_url);


// Cache expiration times
define("PROGRESS_EXPIRATION", 5);                     // 5 seconds
define("AUTH_EXPIRATION", 60 * 59);                   // 1 hour
define("ABOUT_EXPIRATION", 60 * 59 * 24 * 7);         // 1 week
define("HOT_POSTS_EXPIRATION", 60 * 59);              // 1 hour
define("TOP_POSTS_EXPIRATION", 60 * 59);              // 1 hour
define("TOP_DAILY_POSTS_EXPIRATION", 60 * 59);        // 1 hour
define("TOP_MONTHLY_POSTS_EXPIRATION", 60 * 59 * 24); // 1 day
define("COMMENTS_EXPIRATION", 60 * 59 * 4);           // 4 hours
define("GALLERY_EXPIRATION", 60 * 59 * 24 * 7);       // 1 week
define("RSS_EXPIRATION", 60 * 59);                    // 1 hour
define("WEBPAGE_EXPIRATION", 60 * 59 * 24 * 7);       // 1 week
define("IMAGE_EXPIRATION", 60 * 59 * 24 * 30);        // 1 week


$clear_webpages_with_cache = $_SERVER["CLEAR_WEBPAGES_WITH_CACHE"] ?? $_ENV["CLEAR_WEBPAGES_WITH_CACHE"] ?? true;
define('CLEAR_WEBPAGES_WITH_CACHE', $clear_webpages_with_cache);


// Redis
$redis_host = $_SERVER["REDIS_HOST"] ?? $_ENV["REDIS_HOST"] ?? null;
$redis_port = $_SERVER["REDIS_PORT"] ?? $_ENV["REDIS_PORT"] ?? 6379;
$redis_available = false;
define('REDIS_HOST', $redis_host);
define('REDIS_PORT', $redis_port);
if (!empty(REDIS_HOST) && !empty(REDIS_PORT)) {
  $client = new Predis\Client(array(
    'scheme'   => 'tcp',
    'host'     => REDIS_HOST,
    'port'     => REDIS_PORT,
    'timeout' => 1
  ));
  try {
    $client->connect();
    $redis_available = true;
  } catch (Exception $e) {
    $redis_available = false;
  }
}
define('REDIS', $redis_available);


// APCu
$apcu_available = function_exists('apcu_enabled') && apcu_enabled();
define('APCU', $apcu_available);


// Progress caching
$include_progress = !empty($include_progress) ? $include_progress : false;
define('INCLUDE_PROGRESS', $include_progress);



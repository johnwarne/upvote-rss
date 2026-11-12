<?php

// Set versions for cache busting in debug mode
$css_version = DEBUG ? filemtime(__DIR__ . '/../styles/style.css') : UPVOTE_RSS_VERSION;
$script_version = DEBUG ? filemtime(__DIR__ . '/../js/script.js') : UPVOTE_RSS_VERSION;

?><!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Upvote RSS</title>
	<meta name="description" content="Generate RSS feeds from social aggregation websites." />
	<meta name="color-scheme" content="light dark">
	<link rel="stylesheet" href="styles/style.css?v=<?php echo $css_version; ?>" />
	<link rel="icon" type="image/png" href="img/favicons/favicon-96x96.png" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="img/favicons/favicon.svg" />
	<link rel="shortcut icon" href="img/favicons/favicon.ico" />
	<link rel="apple-touch-icon" sizes="180x180" href="img/favicons/apple-touch-icon.png" />
	<meta name="apple-mobile-web-app-title" content="Upvote RSS" />
	<link rel="manifest" href="img/favicons/site.webmanifest" />
	<meta name="theme-color" content="#FFA771" media="(prefers-color-scheme: light)">
	<meta name="theme-color" content="#003040" media="(prefers-color-scheme: dark)">
	<meta property="og:type" content="website" />
	<meta property="og:title" content="Upvote RSS" />
	<meta property="og:description" content="Generate rich RSS feeds from Reddit, Lemmy, Hacker News, Lobsters, PieFed, Mbin, and GitHub" />
	<meta property="og:locale" content="en_US" />
	<meta property="og:image" content="https://www.upvote-rss.com/img/screenshot.png" />
	<meta property="og:image:width" content="1400" />
	<meta property="og:image:height" content="1000" />
	<meta property="og:url" content="https://www.upvote-rss.com/" />
</head>

<body>

	<a href="#main-content" class="skip-link">Skip to main content</a>

	<header>
		<a href=".">
			<img src="img/logo.svg" alt="Upvote RSS" class="logo" height="140" width="803" fetchpriority="high">
		</a>
		<h1><span class="sr-only">Upvote RSS </span>Generate rich RSS feeds from Reddit, Lemmy, Hacker News, Lobsters, PieFed, Mbin, and GitHub</h1>
		<?php if(DEMO_MODE) : ?>
			<p><a href="https://github.com/johnwarne/upvote-rss/" target="_blank" rel="noopener noreferrer">Self-host your own instance<svg class="icon icon-link" aria-hidden="true" focusable="false"><use xlink:href="#icon-link"></use></svg></a></p>
		<?php endif; ?>
	</header>

	<main id="main-content">

		<upvote-rss
			loading="false"
			platform='<?php echo htmlspecialchars((string)PLATFORM, ENT_QUOTES); ?>'
			default-platform='<?php echo htmlspecialchars((string)DEFAULT_PLATFORM, ENT_QUOTES); ?>'
			demo-mode='<?php echo DEMO_MODE ? "true" : "false"; ?>'
			reddit-enabled='<?php echo REDDIT_USER && REDDIT_CLIENT_ID && REDDIT_CLIENT_SECRET ? "true" : "false"; ?>'
			reddit-default-domain='<?php echo htmlspecialchars((string)REDDIT_DEFAULT_DOMAIN, ENT_QUOTES); ?>'
			reddit-default-domain-override='<?php echo htmlspecialchars((string)REDDIT_DEFAULT_DOMAIN_OVERRIDE, ENT_QUOTES); ?>'
			reddit-domain='<?php echo htmlspecialchars((string)REDDIT_DOMAIN, ENT_QUOTES); ?>'
			override-reddit-domain='<?php echo REDDIT_DEFAULT_DOMAIN === REDDIT_DOMAIN ? "false" : "true"; ?>'
			subreddit='<?php echo htmlspecialchars((string)SUBREDDIT, ENT_QUOTES); ?>'
			instance='<?php echo htmlspecialchars((string)INSTANCE, ENT_QUOTES); ?>'
			instance-github-default='<?php echo htmlspecialchars((string)DEFAULT_GITHUB_INSTANCE, ENT_QUOTES); ?>'
			instance-hacker-news-default='<?php echo htmlspecialchars((string)DEFAULT_HACKER_NEWS_INSTANCE, ENT_QUOTES); ?>'
			instance-lemmy-default='<?php echo htmlspecialchars((string)DEFAULT_LEMMY_INSTANCE, ENT_QUOTES); ?>'
			instance-lobsters-default='<?php echo htmlspecialchars((string)DEFAULT_LOBSTERS_INSTANCE, ENT_QUOTES); ?>'
			instance-mbin-default='<?php echo htmlspecialchars((string)DEFAULT_MBIN_INSTANCE, ENT_QUOTES); ?>'
			instance-piefed-default='<?php echo htmlspecialchars((string)DEFAULT_PIEFED_INSTANCE, ENT_QUOTES); ?>'
			instance-reddit-default='<?php echo htmlspecialchars((string)DEFAULT_REDDIT_INSTANCE, ENT_QUOTES); ?>'
			community='<?php echo htmlspecialchars((string)COMMUNITY, ENT_QUOTES); ?>'
			community-type='<?php echo htmlspecialchars((string)COMMUNITY_TYPE, ENT_QUOTES); ?>'
			community-github-default='<?php echo htmlspecialchars((string)DEFAULT_GITHUB_COMMUNITY, ENT_QUOTES); ?>'
			community-hacker-news-default='<?php echo htmlspecialchars((string)DEFAULT_HACKER_NEWS_COMMUNITY, ENT_QUOTES); ?>'
			community-lemmy-default='<?php echo htmlspecialchars((string)DEFAULT_LEMMY_COMMUNITY, ENT_QUOTES); ?>'
			community-lobsters-default='<?php echo htmlspecialchars((string)DEFAULT_LOBSTERS_COMMUNITY, ENT_QUOTES); ?>'
			community-lobsters-default-category='<?php echo htmlspecialchars((string)DEFAULT_LOBSTERS_CATEGORY, ENT_QUOTES); ?>'
			community-lobsters-default-tag='<?php echo htmlspecialchars((string)DEFAULT_LOBSTERS_TAG, ENT_QUOTES); ?>'
			community-mbin-default='<?php echo htmlspecialchars((string)DEFAULT_MBIN_COMMUNITY, ENT_QUOTES); ?>'
			community-piefed-default='<?php echo htmlspecialchars((string)DEFAULT_PIEFED_COMMUNITY, ENT_QUOTES); ?>'
			community-reddit-default='<?php echo htmlspecialchars((string)DEFAULT_SUBREDDIT, ENT_QUOTES); ?>'
			language='<?php echo htmlspecialchars((string)LANGUAGE, ENT_QUOTES); ?>'
			topic='<?php echo htmlspecialchars((string)TOPIC, ENT_QUOTES); ?>'
			score-filter-available=<?php echo SCORE_FILTER_AVAILABLE ? "true" : "false"; ?>
			score-filter-available-platforms='<?php echo htmlspecialchars(json_encode(SCORE_FILTER_AVAILABLE_PLATFORMS), ENT_QUOTES); ?>'
			threshold-filter-available='<?php echo THRESHOLD_FILTER_AVAILABLE ? "true" : "false"; ?>'
			threshold-filter-available-platforms='<?php echo htmlspecialchars(json_encode(THRESHOLD_FILTER_AVAILABLE_PLATFORMS), ENT_QUOTES); ?>'
			average-posts-per-day-filter-available='<?php echo AVERAGE_POSTS_PER_DAY_FILTER_AVAILABLE ? "true" : "false"; ?>'
			average-posts-per-day-filter-available-platforms='<?php echo htmlspecialchars(json_encode(AVERAGE_POSTS_PER_DAY_FILTER_AVAILABLE_PLATFORMS), ENT_QUOTES); ?>'
			filter-type='<?php echo htmlspecialchars((string)FILTER_TYPE, ENT_QUOTES); ?>'
			score='<?php echo htmlspecialchars((string)SCORE, ENT_QUOTES); ?>'
			score-default-github='<?php echo htmlspecialchars((string)DEFAULT_GITHUB_SCORE, ENT_QUOTES); ?>'
			score-default-hacker-news='<?php echo htmlspecialchars((string)DEFAULT_HACKER_NEWS_SCORE, ENT_QUOTES); ?>'
			score-default-lemmy='<?php echo htmlspecialchars((string)DEFAULT_LEMMY_SCORE, ENT_QUOTES); ?>'
			score-default-lobsters='<?php echo htmlspecialchars((string)DEFAULT_LOBSTERS_SCORE, ENT_QUOTES); ?>'
			score-default-mbin='<?php echo htmlspecialchars((string)DEFAULT_MBIN_SCORE, ENT_QUOTES); ?>'
			score-default-piefed='<?php echo htmlspecialchars((string)DEFAULT_PIEFED_SCORE, ENT_QUOTES); ?>'
			score-default-reddit='<?php echo htmlspecialchars((string)DEFAULT_REDDIT_SCORE, ENT_QUOTES); ?>'
			threshold-percentage='<?php echo htmlspecialchars((string)PERCENTAGE, ENT_QUOTES); ?>'
			average-posts-per-day='<?php echo htmlspecialchars((string)POSTS_PER_DAY, ENT_QUOTES); ?>'
			show-score='<?php echo SHOW_SCORE ? "true" : "false"; ?>'
			include-content='<?php echo INCLUDE_CONTENT ? "true" : "false"; ?>'
			summary-enabled='<?php echo SUMMARY_ENABLED ? "true" : "false"; ?>'
			include-summary='<?php echo INCLUDE_SUMMARY ? "true" : "false"; ?>'
			include-comments='<?php echo INCLUDE_COMMENTS ? "true" : "false"; ?>'
			comments='<?php echo htmlspecialchars((string)COMMENTS, ENT_QUOTES); ?>'
			comments-available-platforms='<?php echo htmlspecialchars(json_encode(COMMENTS_AVAILABLE_PLATFORMS), ENT_QUOTES); ?>'
			comments-available='<?php echo COMMENTS_AVAILABLE ? "true" : "false"; ?>'
			pinned-comments-filter-available='<?php echo PINNED_COMMENTS_FILTER_AVAILABLE ? "true" : "false"; ?>'
			pinned-comments-filter-available-platforms='<?php echo htmlspecialchars(json_encode(PINNED_COMMENTS_AVAILABLE_PLATFORMS), ENT_QUOTES); ?>'
			filter-pinned-comments='<?php echo FILTER_PINNED_COMMENTS ? "true" : "false"; ?>'
			filter-nsfw='<?php echo FILTER_NSFW ? "true" : "false"; ?>'
			blur-nsfw='<?php echo BLUR_NSFW ? "true" : "false"; ?>'
			filter-old-posts='<?php echo FILTER_OLD_POSTS ? "true" : "false"; ?>'
			post-cutoff-days='<?php echo htmlspecialchars((string)POST_CUTOFF_DAYS, ENT_QUOTES); ?>'
			cache-size='<?php $cache_size = cache()->getTotalCacheSize(); echo $cache_size; ?>'
			show-rss-url='true'
			dark-mode=''
			>

			<div class="columns">

				<!-- Step 1 -->
				<section class="step step-1">
					<h2>Build your feed</h2>
					<form class="column-content">
						<div class="inner">
							<div class="form-group">
								<label for="platform">Platform</label>
								<select name="platform" id="platform">
									<?php $isSelected = fn($platform) => PLATFORM === $platform ? 'selected' : ''; ?>
									<option value="github" <?php echo $isSelected('github') ?>>GitHub</option>
									<option value="hacker-news" <?php echo $isSelected('hacker-news') ?>>Hacker News</option>
									<option value="lemmy" <?php echo $isSelected('lemmy') ?>>Lemmy</option>
									<option value="lobsters" <?php echo $isSelected('lobsters') ?>>Lobsters</option>
									<option value="mbin" <?php echo $isSelected('mbin') ?>>Mbin</option>
									<option value="piefed" <?php echo $isSelected('piefed') ?>>PieFed</option>
									<option value="reddit" <?php echo $isSelected('reddit') ?> <?php if(DEMO_MODE || !REDDIT_USER || !REDDIT_CLIENT_ID || !REDDIT_CLIENT_SECRET) : ?>disabled<?php endif; ?>>
										Reddit
										<?php if(DEMO_MODE) : ?>
											(available only self-hosted)
										<?php elseif(!REDDIT_USER || !REDDIT_CLIENT_ID || !REDDIT_CLIENT_SECRET) : ?>
											(not configured)
										<?php endif; ?>
									</option>
								</select>
							</div>
							<div class="form-group conditional reddit">
								<label for="subreddit">Subreddit</label>
								<input list="subreddits" id="subreddit" name="subreddit" placeholder="Subreddit" value="<?php echo htmlspecialchars((string)SUBREDDIT, ENT_QUOTES); ?>" />
								<datalist id="subreddits"></datalist>
							</div>
							<div class="form-group conditional hacker-news">
								<label for="hacker-news-type">Type</label>
								<select name="community" id="hacker-news-type">
									<?php $isSelected = fn($value) => PLATFORM === 'hacker-news' && COMMUNITY === $value ? 'selected' : ''; ?>
									<?php foreach (\Community\HackerNews::getCommunityTypes() as $value => $label): ?>
										<option value="<?php echo $value; ?>" <?= $isSelected($value) ?>><?php echo $label; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="form-group conditional lobsters">
								<label for="lobsters-type">Type</label>
								<select name="type" id="lobsters-type">
									<?php $isSelected = fn($value) => PLATFORM === 'lobsters' && COMMUNITY_TYPE === $value ? 'selected' : ''; ?>
									<?php foreach (\Community\Lobsters::getCommunityTypes() as $value => $label): ?>
										<option value="<?php echo $value; ?>" <?= $isSelected($value) ?>><?php echo $label; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="form-group conditional github">
								<label for="language">Language</label>
								<input type="text" id="language" name="language" placeholder="javascript, python, etc." value="<?php echo PLATFORM === 'github' ? LANGUAGE : ''; ?>" />
							</div>
							<div class="form-group conditional github">
								<label for="topic">Topic</label>
								<input type="text" id="topic" name="topic" placeholder="rss, self-hosted, etc." value="<?php echo PLATFORM === 'github' ? TOPIC : ''; ?>" />
							</div>
							<div class="form-group conditional category">
								<label for="category">Category</label>
								<input type="text" id="category" name="category" placeholder="compsci, culture, etc." value="<?php echo PLATFORM === 'lobsters' ? COMMUNITY : ''; ?>" />
							</div>
							<div class="form-group conditional tag">
								<label for="tag">Tag</label>
								<input type="text" id="tag" name="tag" placeholder="web, ai, rust, etc." value="<?php echo PLATFORM === 'lobsters' ? TAG : ''; ?>" />
							</div>
							<div class="form-group conditional lemmy mbin piefed">
								<label for="instance">Instance</label>
								<input type="text" id="instance" name="instance" placeholder="Instance" value="<?php echo in_array(PLATFORM, ['lemmy', 'mbin', 'piefed']) ? INSTANCE : ''; ?>" />
							</div>
							<div class="form-group conditional lemmy piefed">
								<label for="community">Community</label>
								<input type="text" id="community" name="community" placeholder="Community" value="<?php echo in_array(PLATFORM, ['lemmy', 'piefed']) ? COMMUNITY : ''; ?>" />
							</div>
							<div class="form-group conditional mbin">
								<label for="community-mbin">Magazine</label>
								<input type="text" id="community-mbin" name="community" placeholder="Community" value="<?php echo PLATFORM === 'mbin' ? COMMUNITY : ''; ?>" />
							</div>
							<div class="row filter-type-row">
								<div class="form-group">
									<label for="filter-type">Filter Type <span role="button" tabindex="0" aria-expanded="false" aria-controls="filter-information" aria-describedby="filter-information-definition-list"><svg class="icon icon-information-solid" aria-label="View filter type information"><use xlink:href="#icon-information-solid"></use></svg></span>
									</label>
									<select name="filter-type" id="filter-type">
										<option value="score" <?php echo FILTER_TYPE === 'score' ? 'selected' : ''; ?> <?php echo SCORE_FILTER_AVAILABLE ? "" : "hidden"; ?>>Score</option>
										<option value="threshold" <?php echo FILTER_TYPE === 'threshold' ? 'selected' : ''; ?> <?php echo THRESHOLD_FILTER_AVAILABLE ? "" : "hidden"; ?>>Threshold</option>
										<option value="averagePostsPerDay" <?php echo FILTER_TYPE === 'averagePostsPerDay' ? 'selected' : ''; ?> <?php echo AVERAGE_POSTS_PER_DAY_FILTER_AVAILABLE ? "" : "hidden"; ?>>Posts Per Day</option>
									</select>
								</div>
								<div class="form-group">
									<label for="score" class="sr-only">Score</label>
									<input type="number" name="score" id="score" placeholder="Score" min="0" pattern="[0-9]*" inputmode="numeric" value="<?php echo htmlspecialchars((string)SCORE, ENT_QUOTES); ?>" />
								</div>
								<div class="form-group">
									<label for="threshold-percentage" class="sr-only">Percentage</label>
									<input type="number" name="threshold-percentage" id="threshold-percentage" placeholder="Threshold" min="1" pattern="[0-9]*" inputmode="numeric" value="<?php echo htmlspecialchars((string)PERCENTAGE, ENT_QUOTES); ?>" />
								</div>
								<div class="form-group">
									<label for="average-posts-per-day" class="sr-only">Posts Per Day</label>
									<input type="number" name="average-posts-per-day" id="average-posts-per-day" placeholder="Posts per day" min="1" pattern="[0-9]*" inputmode="numeric" value="<?php echo htmlspecialchars((string)POSTS_PER_DAY, ENT_QUOTES); ?>" />
								</div>
							</div>
							<div id="filter-information" class="filter-information">
								<dl id="filter-information-definition-list">
									<dt>Score</dt>
									<dd>Posts below the specified score <?php if(FILTER_TYPE === 'score') : ?>(<?php echo htmlspecialchars((string)SCORE, ENT_QUOTES); ?>) <?php endif; ?>will be filtered out.</dd>
									<dt>Threshold</dt>
									<dd>Posts below the specified percentage <?php if(FILTER_TYPE === 'threshold') : ?>(<?php echo htmlspecialchars((string)PERCENTAGE, ENT_QUOTES); ?>%) <?php endif; ?>of the community's monthly top posts' average score will be filtered out.</dd>
									<dt>Posts Per Day</dt>
									<dd>The RSS feed will output roughly the specified number of posts per day <?php if(FILTER_TYPE === 'averagePostsPerDay') : ?>(<?php echo htmlspecialchars((string)POSTS_PER_DAY, ENT_QUOTES); ?>) <?php endif; ?>.</dd>
								</dl>
							</div>
							<fieldset class="fiddly-bits">
								<legend>
									<span tabindex="0" role="button" aria-expanded="false" aria-controls="fiddly-bits-inner">Fiddly bits</span>
								</legend>
								<div class="inner" id="fiddly-bits-inner">
									<div class="form-group checkbox conditional reddit">
										<input type="checkbox" id="override-reddit-domain" name="override-reddit-domain" <?php echo REDDIT_DEFAULT_DOMAIN === REDDIT_DOMAIN ? "" : "checked"; ?> />
										<label for="override-reddit-domain">Use custom Reddit domain</label>
									</div>
									<div class="form-group">
										<label for="reddit-domain" class="sr-only">Reddit domain</label>
										<input type="text" name="reddit-domain" id="reddit-domain" placeholder="Reddit domain" value="<?php echo REDDIT_DOMAIN === REDDIT_DEFAULT_DOMAIN ? REDDIT_DEFAULT_DOMAIN_OVERRIDE : REDDIT_DOMAIN; ?>" />
									</div>
									<div class="form-group checkbox">
										<input type="checkbox" name="show-score" id="show-score" <?php echo SHOW_SCORE ? 'checked' : ''; ?> />
										<label for="show-score">Show score in feed</label>
									</div>
									<div class="form-group checkbox">
										<input type="checkbox" name="include-content" id="include-content" <?php echo INCLUDE_CONTENT ? 'checked' : ''; ?> />
										<label for="include-content">
											<?php if(PLATFORM === 'github') : ?>
												Include README content
											<?php else: ?>
												Include article content
											<?php endif; ?>
										</label>
									</div>
									<?php if(SUMMARY_ENABLED) : ?>
										<div class="form-group checkbox">
											<input type="checkbox" name="include-summary" id="include-summary" <?php echo INCLUDE_SUMMARY ? 'checked' : ''; ?> />
											<label for="include-summary">Include summary</label>
										</div>
									<?php else: ?>
										<div class="form-group checkbox">
											<input type="checkbox" name="include-summary" id="include-summary" disabled />
											<label for="include-summary" aria-disabled="true">Include summary (not configured)</label>
										</div>
									<?php endif; ?>
									<div class="row comments-row">
										<div class="form-group checkbox">
											<input type="checkbox" name="include-comments" id="include-comments" <?php echo INCLUDE_COMMENTS ? 'checked' : ''; ?> />
											<label for="include-comments">Include comments</label>
										</div>
										<div class="form-group">
											<label for="comments" class="sr-only">Comments</label>
											<input type="number" name="comments" id="comments" placeholder="Comments" min="1" pattern="[0-9]*" value="<?php echo htmlspecialchars((string)COMMENTS, ENT_QUOTES); ?>" />
										</div>
									</div>
									<div class="form-group checkbox">
										<input type="checkbox" name="filter-pinned-comments" id="filter-pinned-comments" <?php echo FILTER_PINNED_COMMENTS ? 'checked' : ''; ?> />
										<label for="filter-pinned-comments">Filter pinned comments</label>
									</div>
									<div class="row cutoff-row">
										<div class="form-group checkbox">
											<input type="checkbox" name="filter-old-posts" id="filter-old-posts" <?php echo FILTER_OLD_POSTS ? 'checked' : ''; ?> />
											<label for="filter-old-posts" aria-live="polite">
												<?php echo FILTER_OLD_POSTS && POST_CUTOFF_DAYS > 1 ? '<span>Include posts from the past <span>' . POST_CUTOFF_DAYS . '</span> days</span>' : (FILTER_OLD_POSTS ? 'Include posts from the past day' : 'Filter old posts'); ?>
											</label>
										</div>
										<div class="form-group">
											<label for="post-cutoff-days" class="sr-only">Days to keep</label>
											<input type="number" name="post-cutoff-days" id="post-cutoff-days" placeholder="days to keep" min="1" pattern="[0-9]*" value="<?php echo htmlspecialchars((string)POST_CUTOFF_DAYS, ENT_QUOTES); ?>" />
										</div>
									</div>
									<div class="form-group checkbox conditional reddit">
										<input type="checkbox" name="filter-nsfw" id="filter-nsfw" <?php echo FILTER_NSFW ? 'checked' : ''; ?> />
										<label for="filter-nsfw">Filter NSFW posts</label>
									</div>
									<div class="form-group checkbox">
										<input type="checkbox" name="blur-nsfw" id="blur-nsfw" <?php echo BLUR_NSFW ? 'checked' : ''; ?> />
										<label for="blur-nsfw">Blur NSFW Reddit media</label>
									</div>
								</div>
							</fieldset>
							<div class="form-group generate">
								<button type="submit" class="button">Generate RSS Feed</button>
							</div>
						</div>
					</form>
				</section>


				<!-- Step 2 -->
				<section class="step step-2">
					<h2>Preview your posts</h2>
					<div class="post-list column-content">
						<div class="post-list-posts" aria-live="polite">
							<?php for ($i = 0; $i < 3; $i++): ?>
								<article class="loading" aria-hidden="true">
									<a href="#">
										<div class="thumbnail default-image skeleton">
											<img src="https://www.redditstatic.com/mweb2x/favicon/76x76.png" alt="" />
										</div>
										<div class="content">
											<h3 class="skeleton">Post title</h3>
											<time class="skeleton">Time</time>
											<div class="score skeleton">
												<span>⬆︎</span>Score
											</div>
										</div>
									</a>
								</article>
							<?php endfor; ?>
						</div>
						<progress-indicator aria-hidden="true" >
							<progress aria-describedby="progress-desc" max="100" value="0">0%</progress>
							<progress-percentage>0%</progress-percentage>
							<div id="progress-desc" class="sr-only" role="status" aria-live="polite" aria-atomic="true">Loading preview posts</div>
							<svg class="progress-ring" height="160" width="160" aria-hidden="true">
								<circle fill="transparent" stroke-dasharray="376.8 376.8" style="stroke-dashoffset: 377" stroke-width="10" r="60" cx="80" cy="80" />
							</svg>
						</progress-indicator>
					</div>
				</section>


				<!-- Step 3 -->
				<section class="step step-3">
					<h2>Copy RSS URL</h2>
					<div class="column-content">
						<div class="inner">
							<div class="community-info"></div>
							<div class="rss-url">
								<h3>RSS Feed URL</h3>
								<p>
									<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') . '&view=rss'; ?>
								</p>
							</div>
							<button type="button" class="button copy-rss-url" title="Copy RSS URL" aria-label="Copy RSS URL">
								<span class="button-text">
									<svg class="icon icon-copy" aria-hidden="true">
										<use xlink:href="#icon-copy"></use>
									</svg> Copy RSS URL
								</span>
							</button>
							<button type="button" class="clear-cache" title="Refresh cache" aria-label="Refresh cache" <?php echo DEMO_MODE ? 'hidden' : ''; ?>>
								<svg class="icon icon-cycle" aria-hidden="true">
									<use xlink:href="#icon-cycle"></use>
								</svg>
								<span>Refresh cache <span><?php echo $cache_size; ?></span></span>
							</button>
						</div>
					</div>
				</section>

			</div>


			<!-- Footer -->
			<footer>
				<p class="repo-name">Upvote RSS v<?php echo htmlspecialchars((string)UPVOTE_RSS_VERSION, ENT_QUOTES); ?></p>
				<p class="repo-link"><a href="https://github.com/johnwarne/upvote-rss/" target="_blank" rel="noopener noreferrer"><svg class="icon icon-github" aria-hidden="true"><use xlink:href="#icon-github"></use></svg>GitHub</a></p>
				<form class="dark-mode">
					<fieldset>
						<legend class="sr-only">Dark mode</legend>
						<label for="auto">
							<input type="radio" id="auto" name="mode" value="auto">
							<span class="sr-only">Auto</span>
							<svg class="icon icon-schedule" aria-hidden="true">
								<use xlink:href="#icon-schedule"></use>
							</svg>
						</label>
						<label for="light">
							<input type="radio" id="light" name="mode" value="light">
							<span class="sr-only">Light</span>
							<svg class="icon icon-light-mode" aria-hidden="true">
								<use xlink:href="#icon-light-mode"></use>
							</svg>
						</label>
						<label for="dark">
							<input type="radio" id="dark" name="mode" value="dark">
							<span class="sr-only">Dark</span>
							<svg class="icon icon-dark-mode" aria-hidden="true">
								<use xlink:href="#icon-dark-mode"></use>
							</svg>
						</label>
					</fieldset>
				</form>
			</footer>


		</upvote-rss>

	</main>

	<script src="js/script.js?v=<?php echo $script_version; ?>" defer></script>

	<!-- SVGs -->
	<?php include 'inc/svgs.php'; ?>

</body>

</html>
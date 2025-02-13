<?php

class RSS {

	private $rss_directory;
	private $request_uri;
	private $feed_title;
	private $feed_description;
	private $community;
	private $xml;

	public function __construct() {
		$this->rss_directory    = $_SERVER['DOCUMENT_ROOT'] . "/cache/rss/";
		$this->request_uri      = "";
		$this->feed_title       = "";
		$this->feed_description = "";
		$this->community        = null;
		$this->xml              = new DOMDocument("1.0", "UTF-8");
	}

	public function generateRSS() {
		$this->parseRequestUri();
		$this->setHeaders();
		$this->checkCache();
		$this->checkCommunity();
		$this->setFeedTitleAndDescription();
		$this->createRssElement();
		$this->createChannelElement();
		$this->addAtomLinkElement();
		$this->addGeneralElements();
		$this->loopThroughPosts();
		$this->cacheFeed();
		$this->outputXml();
	}

	private function parseRequestUri() {
		if (!empty($_GET)) {
			$this->request_uri = http_build_query($_GET);
		}
	}

	private function setHeaders() {
		header("Content-Type: text/xml; charset=utf-8");
	}

	private function checkCache() {
		if ($this->request_uri && cacheGet($this->request_uri, $this->rss_directory)) {
			echo cacheGet($this->request_uri, $this->rss_directory);
			exit;
		}
	}

	private function checkCommunity() {
		if (
			!PLATFORM ||
			(!COMMUNITY && !SUBREDDIT)
		) {
			http_response_code(404);
		}

		$this->community = match (PLATFORM) {
			'reddit'      => new Community\Reddit(SUBREDDIT, FILTER_NSFW, BLUR_NSFW),
			'hacker-news' => new Community\HackerNews(COMMUNITY),
			'lemmy'       => new Community\Lemmy(COMMUNITY, INSTANCE, FILTER_NSFW, BLUR_NSFW),
			default       => null,
		};

		if (
			empty($this->community) ||
			!$this->community->is_community_valid
		) {
			http_response_code(404);
		}
	}

	private function setFeedTitleAndDescription() {
		$this->feed_title       = $this->community->feed_title;
		$this->feed_description = $this->community->feed_description;
		if (FILTER_TYPE == "score") {
			$this->feed_description .= " at or above a score of " . SCORE;
		}
		if (FILTER_TYPE == "threshold") {
			$this->feed_description .= " at or above " . PERCENTAGE . "% of monthly top posts' average score";
		}
		if (FILTER_TYPE == "averagePostsPerDay") {
			$this->feed_description .= " (roughly " . POSTS_PER_DAY . " posts per day)";
		}
 }

	private function createRssElement() {
		$rss      = $this->xml->createElement("rss");
		$rss_node = $this->xml->appendChild($rss);
		$rss_node->setAttribute("version", "2.0");
		$rss_node->setAttribute("xmlns:atom", "http://www.w3.org/2005/Atom");
	}

	private function createChannelElement() {
		$channel      = $this->xml->createElement("channel");
		$channel_node = $this->xml->getElementsByTagName("rss")->item(0)->appendChild($channel);
	}

	private function addAtomLinkElement() {
		$channel_atom_link = $this->xml->createElement("atom:link");
		$protocol          = getProtocol();
		$channel_atom_link->setAttribute("href", "$protocol{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
		$channel_atom_link->setAttribute("rel", "self");
		$channel_atom_link->setAttribute("type", "application/rss+xml");
		$this->xml->getElementsByTagName("channel")->item(0)->appendChild($channel_atom_link);
	}

	private function addGeneralElements() {
		$channel_node = $this->xml->getElementsByTagName("channel")->item(0);
		$channel_node->appendChild($this->xml->createElement("title", $this->feed_title));
		$channel_node->appendChild($this->xml->createElement("description", $this->feed_description));
		$channel_node->appendChild($this->xml->createElement("link", $this->community->url));
		$channel_node->appendChild($this->xml->createElement("language", "en-us"));
		$channel_node->appendChild($this->xml->createElement("lastBuildDate", gmdate(DATE_RFC2822, strtotime(date("D, d M Y H:i:s T", time())))));
		$channel_node->appendChild($this->xml->createElement("generator", "Upvote RSS"));
		// Add image node if available
		if(!empty($this->community->icon)) {
			$icon        = resizeImage($this->community->icon, 144, 400);
			$icon_url    = $icon[0] ?? '';
			$icon_width  = $icon[1] ?? 0;
			$icon_height = $icon[2] ?? 0;
			if($icon_url) {
				$channel_image_node = $channel_node->appendChild($this->xml->createElement("image"));
				$channel_image_node->appendChild($this->xml->createElement("url", $icon_url));
				$channel_image_node->appendChild($this->xml->createElement("title", $this->feed_title));
				$channel_image_node->appendChild($this->xml->createElement("link", $this->community->url));
				if ($icon_width && $icon_height) {
					$channel_image_node->appendChild($this->xml->createElement("width", $icon_width));
					$channel_image_node->appendChild($this->xml->createElement("height", $icon_height));
				}
			}
		}
	}

	private function loopThroughPosts() {
		$channel_node = $this->xml->getElementsByTagName("channel")->item(0);
		$posts        = $this->community->getFilteredPostsByValue(FILTER_TYPE, FILTER_VALUE, FILTER_NSFW, BLUR_NSFW, FILTER_OLD_POSTS, POST_CUTOFF_DAYS);

		foreach ($posts as $post) {

			// Skip if post is empty
			$title = $post->title;
			$link  = $post->feed_Link;
			if (!$title || !$link) {
				continue;
			}

			// Custom Reddit domain
			if(REDDIT_DEFAULT_DOMAIN !== REDDIT_DOMAIN && !strpos($link, "www.reddit.com/gallery/")) {
				$link = str_replace(REDDIT_DEFAULT_DOMAIN, REDDIT_DOMAIN, $link);
			}

			// Create a new node called "item"
			$item_node = $this->xml->createElement("item");
			$channel_node->appendChild($item_node);

			// Link node
			$item_node->appendChild($this->xml->createElement("link", $link));

			// Title node
			$item_node->appendChild($this->xml->createElement("title", $title));

			// Unique identifier for the item (GUID)
			$guid_is_permalink = "false";
			if (strpos($link, "http") !== false) {
				$guid_is_permalink = "true";
			}
			$guid_link = $this->xml->createElement("guid", $post->permalink);
			$guid_link->setAttribute("isPermaLink", $guid_is_permalink);
			$item_node->appendChild($guid_link);

			// Comments node
			if (strpos($post->domain, "self.") == false) {
				$item_node->appendChild($this->xml->createElement("comments", $post->permalink));
			}

			// Description node
			$description_node     = $item_node->appendChild($this->xml->createElement("description"));
			$description          = $post->getFeedItemDescription();
			if(REDDIT_DEFAULT_DOMAIN !== REDDIT_DOMAIN) {
				$description = str_replace(REDDIT_DEFAULT_DOMAIN, REDDIT_DOMAIN, $description);
			}
			$description_contents = $this->xml->createCDATASection($description);
			$description_node->appendChild($description_contents);

			//Published date
			$pub_date = $this->xml->createElement("pubDate", date("r", $post->time));
			$item_node->appendChild($pub_date);
		}
	}

	private function cacheFeed() {
		cacheSet($this->request_uri, $this->xml->saveXML(), $this->rss_directory, RSS_EXPIRATION);
	}

	private function outputXml() {
		echo $this->xml->saveXML();
		exit;
	}
}

<?php

/**
 * Cache class for handling Redis, APCu, and file-based caching
 *
 */
class Cache {
	private static $instance        = null;
	private $redis_client           = null;
	private $redis_connected        = false;
	private $apcu_available         = false;
	private static $apcu_hits       = 0;
	private static $apcu_requests   = 0;
	private $cache_root;

	/**
	 * Constructor
	 * @param string|null $cache_root The root directory for file-based caching
	 */
	private function __construct($cache_root = null) {
		$this->cache_root = $cache_root ?? UPVOTE_RSS_CACHE_ROOT;
		$this->initializeRedis();
		$this->initializeApcu();
	}

	/**
	 * Get singleton instance
	 * @return Cache
	 */
	public static function getInstance(): Cache {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize Redis connection
	 */
	private function initializeRedis(): void {
		if (defined('REDIS') && REDIS) {
			try {
				$this->redis_client = new Predis\Client('tcp://' . REDIS_HOST . ':' . REDIS_PORT);
				// Test connection
				$this->redis_client->ping();
				$this->redis_connected = true;
			} catch (\Exception $e) {
				logger()->warning("Redis connection failed: " . $e->getMessage());
				$this->redis_connected = false;
			}
		}
	}

	/**
	 * Initialize APCu availability
	 */
	private function initializeApcu(): void {
		$this->apcu_available = defined('APCU') && APCU;
	}

	/**
	 * Format Redis key
	 * @param string $key The cache key
	 * @param string $directory The cache directory
	 * @return string The formatted Redis key
	 */
	private function formatRedisKey(string $key, string $directory): string {
		$redis_key = 'upvote_rss:' . str_replace('/', ':', $directory) . ':' . $key;
		$redis_key = str_replace(['.'], '_', $redis_key);
		$redis_key = str_replace('::', ':', $redis_key);
		return $redis_key;
	}

	/**
	 * Format APCu key
	 * @param string $key The cache key
	 * @param string $directory The cache directory
	 * @return string The formatted APCu key
	 */
	private function formatApcuKey(string $key, string $directory): string {
		$apcu_key = 'upvote_rss_' . str_replace('/', '_', $directory) . '_' . $key;
		$apcu_key = str_replace(['.', ' ', ':'], '_', $apcu_key);
		$apcu_key = str_replace('__', '_', $apcu_key);
		return $apcu_key;
	}

	/**
	 * Check if directory should use APCu (for auth tokens and progress indicators)
	 * @param string $directory The cache directory
	 * @return bool True if should use APCu
	 */
	private function shouldUseApcu(string $directory): bool {
		$apcu_directories = [
			'auth',
			'progress'
		];

		$should_use = false;
		foreach ($apcu_directories as $apcu_dir) {
			if (strpos($directory, $apcu_dir) !== false) {
				$should_use = true;
				break;
			}
		}

		return $should_use;
	}

	/**
	 * Normalize directory path
	 * @param string $directory The directory path
	 * @return string The normalized directory path
	 */
	private function normalizeDirectory(string $directory): string {
		// Drop empty, current (.) and parent (..) path segments so a user-influenced directory
		// (built from instance/community/slug) cannot traverse outside the cache root.
		$directory = str_replace('\\', '/', $directory);
		$parts = array_filter(explode('/', $directory), function ($segment) {
			return $segment !== '' && $segment !== '.' && $segment !== '..';
		});
		return implode('/', $parts);
	}

	/**
	 * Get cached data
	 * @param string $key The key of the cached object
	 * @param string $directory The directory where the cache is stored
	 * @return mixed The cached object or null if not found
	 */
	public function get(string $key, string $directory = '') {
		if (empty($key)) {
			return null;
		}

		$directory = $this->normalizeDirectory($directory);

		// Use Redis if available and connected
		if ($this->redis_connected) {
			try {
				$redis_key = $this->formatRedisKey($key, $directory);
				if ($this->redis_client->exists($redis_key)) {
					return unserialize($this->redis_client->get($redis_key));
				}
			} catch (\Exception $e) {
				logger()->warning("Redis get failed: " . $e->getMessage());
				// Fall through to APCu or file cache
			}
		}

		// Use APCu if available and directory qualifies
		if ($this->apcu_available && $this->shouldUseApcu($directory)) {
			self::$apcu_requests++;
			try {
				$apcu_key = $this->formatApcuKey($key, $directory);
				$result = apcu_fetch($apcu_key, $success);
				if ($success) {
					self::$apcu_hits++;
					// logger()->info("APCu cache hit: " . $apcu_key);
					return $result;
				}
			} catch (\Exception $e) {
				logger()->warning("APCu get failed: " . $e->getMessage());
				// Fall through to file cache
			}
		}

		// File-based cache fallback
		$cache_directory = $this->cache_root . $directory . '/';
		$cache_file = $cache_directory . urlencode($key);

		if (file_exists($cache_file)) {
			$content = file_get_contents($cache_file);
			if ($content !== false) {
				// Check if this is an old var_export format file (starts with <?php)
				if (strpos($content, '<?php') === 0) {
					// Delete old format file so it can be regenerated in new format
					@unlink($cache_file);
					logger()->info("Deleted old cache file format: " . basename($cache_file));
					return null;
				}

				// Try to unserialize new format
				$unserialized = @unserialize($content);
				if ($unserialized !== false || $content === serialize(false)) {
					return $unserialized;
				}

				// If unserialize fails, delete corrupted file
				@unlink($cache_file);
				logger()->warning("Deleted corrupted cache file: " . basename($cache_file));
			}
		}

		return null;
	}

	/**
	 * Set cached data
	 * @param string $key The key of the cached object
	 * @param mixed $value The value of the cached object
	 * @param string $directory The directory where the cache is stored
	 * @param int $expiration The expiration time of the cache in seconds
	 * @return bool True if successful, false otherwise
	 */
	public function set(string $key, $value, string $directory = '', int $expiration = 0): bool {
		if (empty($key) || $value === null) {
			return false;
		}

		$directory = $this->normalizeDirectory($directory);

		// Use Redis if available and connected
		if ($this->redis_connected) {
			try {
				$redis_key = $this->formatRedisKey($key, $directory);
				$this->redis_client->set($redis_key, serialize($value), 'EX', $expiration);
				return true;
			} catch (\Exception $e) {
				logger()->error("Redis set failed: " . $e->getMessage());
				// Fall through to APCu or file cache
			}
		}

		// Use APCu if available and directory qualifies
		if ($this->apcu_available && $this->shouldUseApcu($directory)) {
			try {
				$apcu_key = $this->formatApcuKey($key, $directory);
				$ttl = $expiration > 0 ? $expiration : 0; // 0 means no expiration in APCu
				if (apcu_store($apcu_key, $value, $ttl)) {
					return true;
				}
			} catch (\Exception $e) {
				logger()->error("APCu set failed: " . $e->getMessage());
				// Fall through to file cache
			}
		}

		// File-based cache fallback
		$cache_directory = $this->cache_root . $directory . '/';

		if (!is_dir($cache_directory)) {
			try {
				if (!@mkdir($cache_directory, 0755, true) && !is_dir($cache_directory)) {
					throw new \RuntimeException("Failed to create cache directory: $cache_directory");
				}
			} catch (\Throwable $e) {
				logger()->error("Failed to create cache directory: " . $e->getMessage());
				return false;
			}
		}

		$cache_value = serialize($value);
		$file_path = $cache_directory . urlencode($key);

		try {
			if (file_put_contents($file_path, $cache_value, LOCK_EX) === false) {
				throw new \Exception("Failed to write cache file: $file_path");
			}
			return true;
		} catch (\Exception $e) {
			logger()->error($e->getMessage());
			return false;
		}
	}

	/**
	 * Delete cached data
	 * @param string $key The key of the cached object
	 * @param string $directory The directory where the cache is stored
	 * @return bool True if successful, false otherwise
	 */
	public function delete(string $key, string $directory = ''): bool {
		if (empty($key)) {
			return false;
		}

		$directory = $this->normalizeDirectory($directory);

		// Use Redis if available and connected
		if ($this->redis_connected) {
			try {
				$redis_key = $this->formatRedisKey($key, $directory);
				$this->redis_client->del($redis_key);
				return true;
			} catch (\Exception $e) {
				logger()->warning("Redis delete failed: " . $e->getMessage());
				// Fall through to APCu or file cache
			}
		}

		// Use APCu if available and directory qualifies
		if ($this->apcu_available && $this->shouldUseApcu($directory)) {
			try {
				$apcu_key = $this->formatApcuKey($key, $directory);
				apcu_delete($apcu_key);
				return true;
			} catch (\Exception $e) {
				logger()->warning("APCu delete failed: " . $e->getMessage());
				// Fall through to file cache
			}
		}

		// File-based cache fallback
		$cache_directory = $this->cache_root . $directory . '/';
		$cache_file = $cache_directory . urlencode($key);

		if (file_exists($cache_file)) {
			return unlink($cache_file);
		}

		return true; // Consider it successful if file doesn't exist
	}


	/**
	 * Clear all cache in a directory
	 * @param string $directory The directory to clear
	 * @return bool True if successful, false otherwise
	 */
	public function clearDirectory(string $directory = ''): bool {
		$directory = $this->normalizeDirectory($directory);

		// Clear Redis keys with pattern
		if ($this->redis_connected) {
			try {
				$pattern = 'upvote_rss:' . str_replace('/', ':', $directory) . ':*';
				$keys = $this->redis_client->keys($pattern);
				if (!empty($keys)) {
					$this->redis_client->del($keys);
				}
			} catch (\Exception $e) {
				logger()->warning("Redis clear directory failed: " . $e->getMessage());
			}
		}

		// Clear APCu keys if directory qualifies
		if ($this->apcu_available && $this->shouldUseApcu($directory)) {
			try {
				$pattern = '/^upvote_rss_' . str_replace('/', '_', $directory) . '_/';
				$iterator = new \APCUIterator($pattern);
				foreach ($iterator as $key => $value) {
					apcu_delete($key);
				}
			} catch (\Exception $e) {
				logger()->warning("APCu clear directory failed: " . $e->getMessage());
			}
		}

		// Clear file-based cache
		$cache_directory = $this->cache_root . $directory . '/';
		if (is_dir($cache_directory)) {
			$files = glob($cache_directory . '*');
			foreach ($files as $file) {
				if (is_file($file)) {
					unlink($file);
				}
			}
			return true;
		}

		return true;
	}

	/**
	 * Clean up expired cache files
	 */
	public function cleanUpExpired(): void {
		$directory_expirations = [
			"/progress/"                => PROGRESS_EXPIRATION,
			"/auth/"                    => AUTH_EXPIRATION,
			"/about/"                   => ABOUT_EXPIRATION,
			"/hot_posts/"               => HOT_POSTS_EXPIRATION,
			"/top_posts/"               => TOP_POSTS_EXPIRATION,
			"/top_posts_day/"           => TOP_DAILY_POSTS_EXPIRATION,
			"/top_posts_month/"         => TOP_MONTHLY_POSTS_EXPIRATION,
			"/comments/"                => COMMENTS_EXPIRATION,
			"/galleries/"               => GALLERY_EXPIRATION,
			"/rss/"                     => RSS_EXPIRATION,
			"/webpages/"                => WEBPAGE_EXPIRATION,
			"/images/"                  => IMAGE_EXPIRATION
		];

		// Clean up Redis expired keys first
		$this->cleanUpRedisExpired($directory_expirations);

		// Clean up file cache
		$this->cleanUpFileCache($directory_expirations);
	}

	/**
	 * Clean up expired Redis cache entries
	 */
	private function cleanUpRedisExpired(array $directory_expirations): void {
		if (!$this->redis_connected) {
			return;
		}

		try {
			foreach ($directory_expirations as $directory => $expiration) {
				$pattern = 'upvote_rss:' . str_replace('/', ':', trim($directory, '/')) . ':*';
				$keys = $this->redis_client->keys($pattern);

				foreach ($keys as $key) {
					$ttl = $this->redis_client->ttl($key);
					// If TTL is -1 (no expiration set) or key should have expired based on our rules
					if ($ttl === -1) {
						$this->redis_client->expire($key, $expiration);
					}
				}
			}
		} catch (\Exception $e) {
			logger()->warning("Redis cleanup failed: " . $e->getMessage());
		}
	}

	/**
	 * Clean up expired file cache entries
	 */
	private function cleanUpFileCache(array $directory_expirations): void {
		if (!is_dir(UPVOTE_RSS_CACHE_ROOT)) {
			@mkdir(UPVOTE_RSS_CACHE_ROOT, 0755, true);
		}

		// Wait for directory creation with timeout
		for ($i = 0; $i < 10 && !is_dir(UPVOTE_RSS_CACHE_ROOT); $i++) {
			usleep(100000);
		}

		if (!is_dir(UPVOTE_RSS_CACHE_ROOT)) {
			logger()->error("Failed to create cache root directory");
			return;
		}

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator(UPVOTE_RSS_CACHE_ROOT, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			$empty_dirs = [];
			$current_time = time();

			foreach ($iterator as $item) {
				$path = $item->getPathname();

				if ($item->isFile()) {
					// Check if file is expired
					$file_time = filemtime($path);
					foreach ($directory_expirations as $search => $expiration) {
						if (strpos($path, $search) !== false &&
							$current_time - $file_time >= $expiration) {
							@unlink($path);
							break;
						}
					}
				} elseif ($item->isDir() && $path !== UPVOTE_RSS_CACHE_ROOT) {
					// Check if directory is empty (only contains . and ..)
					if ($this->isDirectoryEmpty($path)) {
						$empty_dirs[] = $path;
					}
				}
			}

			// Remove empty directories in reverse order (deepest first)
			foreach (array_reverse($empty_dirs) as $dir) {
				@rmdir($dir);
			}

		} catch (\Exception $e) {
			logger()->warning("File cache cleanup failed: " . $e->getMessage());
		}
	}

	/**
	 * Check if directory is empty (contains only . and ..)
	 */
	private function isDirectoryEmpty(string $dir): bool {
		try {
			$iterator = new DirectoryIterator($dir);
			foreach ($iterator as $item) {
				if (!$item->isDot()) {
					return false;
				}
			}
			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Delete directory contents
	 * @param string $src The directory path
	 * @param array $exclude Optional array of directories to exclude
	 */
	private function deleteDirectoryContents($src, $exclude = []) {
		if (!is_dir($src)) return;

		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
				RecursiveIteratorIterator::CHILD_FIRST
			);

			$excluded_paths = array_map(function($dir) use ($src) {
				return rtrim($src, '/') . '/' . $dir;
			}, $exclude);

			foreach ($iterator as $fileInfo) {
				$path = $fileInfo->getRealPath();

				// Skip excluded directories and their contents
				$skip = false;
				foreach ($excluded_paths as $excluded_path) {
					if (strpos($path, $excluded_path) === 0) {
						$skip = true;
						break;
					}
				}
				if ($skip) continue;

				if ($fileInfo->isDir()) {
					@rmdir($path);
				} else {
					@unlink($path);
				}
			}
		} catch (\Exception $e) {
			logger()->warning("Error deleting directory contents: " . $e->getMessage());
		}
	}

	/**
	 * Clear the cache
	 * @param array $exclude Optional array of directories to exclude from clearing
	 * @return void
	 */
	public function clear($exclude_dirs = []) {
		// Clear Redis cache if enabled
		if ($this->redis_connected) {
			try {
				$keys = $this->redis_client->keys('upvote_rss*');
				foreach ($keys as $key) {
					if (CLEAR_WEBPAGES_WITH_CACHE || strpos($key, 'upvote_rss:webpages') === false) {
						$this->redis_client->del($key);
					}
				}
			} catch (\Exception $e) {
				logger()->warning("Redis cache clear failed: " . $e->getMessage());
			}
		}

		// Clear APCu cache if enabled
		if ($this->apcu_available) {
			try {
				$iterator = new \APCUIterator('/^upvote_rss_/');
				foreach ($iterator as $key => $value) {
					// Check if key should be excluded
					$should_exclude = false;
					if (!CLEAR_WEBPAGES_WITH_CACHE && strpos($key, 'upvote_rss_webpages_') !== false) {
						$should_exclude = true;
					}

					// Check exclude directories
					foreach ($exclude_dirs as $exclude_dir) {
						$exclude_pattern = 'upvote_rss_' . str_replace('/', '_', $exclude_dir) . '_';
						if (strpos($key, $exclude_pattern) !== false) {
							$should_exclude = true;
							break;
						}
					}

					if (!$should_exclude) {
						apcu_delete($key);
					}
				}
			} catch (\Exception $e) {
				logger()->warning("APCu cache clear failed: " . $e->getMessage());
			}
		}

		// Clear file cache
		if (!CLEAR_WEBPAGES_WITH_CACHE) {
			$exclude_dirs[] = 'webpages';
		}
		$this->deleteDirectoryContents(UPVOTE_RSS_CACHE_ROOT, $exclude_dirs);

		// Clear OPCache if available
		if (function_exists('opcache_reset')) {
			opcache_reset();
		}

		// Log the action
		$log_message = 'Cache cleared';
		if (!CLEAR_WEBPAGES_WITH_CACHE) {
			$log_message .= ' (excluding webpages)';
		}
		logger()->info($log_message);
	}

	/**
	 * Get APCu cache size
	 * @return int Cache size in bytes
	 */
	private function getApcuCacheSize(): int {
		$cache_size = 0;
		if ($this->apcu_available) {
			try {
				$iterator = new \APCUIterator('/^upvote_rss_/');
				foreach ($iterator as $key => $value) {
					$cache_size += strlen($key) + strlen(serialize($value['value']));
				}
			} catch (\Exception $e) {
				logger()->warning("APCu cache size calculation failed: " . $e->getMessage());
			}
		}
		return $cache_size;
	}

	/**
	 * Get Redis cache size
	 * @return int Cache size in bytes
	 */
	private function getRedisCacheSize(): int {
		$cache_size = 0;
		if ($this->redis_connected) {
			$keys = $this->redis_client->keys('upvote_rss*');
			foreach ($keys as $key) {
				if (!empty($key) && $this->redis_client->exists($key)) {
					$cache_size += strlen($key) + strlen($this->redis_client->get($key));
				}
			}
		}
		return $cache_size;
	}

	/**
	 * Get cache directory size
	 * @return int Cache directory size in bytes
	 */
	private function getCacheDirectorySize(): int {
		$cache_size = 0;
		// Get size of cache directory if it exists
		if (!is_dir($this->cache_root)) {
			return $cache_size;
		}
		$cache_directory_iterator = new RecursiveDirectoryIterator($this->cache_root);
		foreach (new RecursiveIteratorIterator($cache_directory_iterator) as $item) {
			if (is_file($item)) {
				$cache_size += $item->getSize();
			}
		}
		return $cache_size;
	}

	/**
	 * Get total cache size
	 * @return string Cache size in human readable format
	 */
	public function getTotalCacheSize(): string {
		$cache_size = 0;

		// Get size of Redis cache if available
		$cache_size += $this->getRedisCacheSize();

		// Get size of APCu cache if available
		$cache_size += $this->getApcuCacheSize();

		// Get size of cache directory if it exists
		$cache_size += $this->getCacheDirectorySize();

		return formatByteSize($cache_size);
	}

	/**
	 * Get APCu cache statistics
	 * @return array Cache hit statistics
	 */
	public function getApcuStats(): array {
		$hit_rate = self::$apcu_requests > 0 ? round((self::$apcu_hits / self::$apcu_requests) * 100, 2) : 0;
		return [
			'hits' => self::$apcu_hits,
			'requests' => self::$apcu_requests,
			'hit_rate' => $hit_rate . '%'
		];
	}
}

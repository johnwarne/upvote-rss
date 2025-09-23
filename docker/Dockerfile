FROM dunglas/frankenphp:1.9.0-php8.4.10-alpine AS build

# Install required utilities and PHP extensions
RUN apk add --no-cache --virtual .build-deps \
    build-base freetype-dev libjpeg-turbo-dev libpng-dev libwebp-dev && \
    install-php-extensions gd opcache apcu && \
    apk del .build-deps && \
    rm -rf /var/cache/apk/*

# Final stage
FROM dunglas/frankenphp:1.9.0-php8.4.10-alpine

# Overwrite some labels from FrankenPHP
LABEL org.opencontainers.image.title="Upvote RSS"
LABEL org.opencontainers.image.description="Generate rich RSS feeds from Reddit, Hacker News, Lemmy, Mbin, and more"
LABEL org.opencontainers.image.url="https://www.upvote-rss.com/"
LABEL org.opencontainers.image.source="https://github.com/johnwarne/upvote-rss"
LABEL org.opencontainers.image.licenses="MIT"

# Install su-exec and opcache
RUN apk add --no-cache su-exec && \
    install-php-extensions gd opcache apcu

# Copy the application files
COPY . /app

# Create necessary directories and move files
RUN mkdir -p /app/cache /app/logs /data/caddy/locks && \
	mv /app/docker/Caddyfile /etc/caddy/Caddyfile && \
	mv /app/docker/entrypoint.sh /entrypoint.sh

# Make the entrypoint script executable
RUN chmod +x /entrypoint.sh

# Run on port 80
EXPOSE 80

# Add healthcheck
HEALTHCHECK --interval=30s --timeout=20s --start-period=5s --retries=3 \
  CMD curl --fail http://localhost:80 || exit 1

# Run the entrypoint script
ENTRYPOINT ["/entrypoint.sh"]

# Run the PHP server
CMD ["frankenphp", "php-server", "-r", "/app"]

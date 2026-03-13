FROM dunglas/frankenphp:1.12.1-php8.4.18-alpine

# Install su-exec and opcache
RUN apk add --no-cache su-exec && \
    install-php-extensions gd opcache apcu

# Copy the application files
COPY app.php ajax.php config.php functions.php index.php progress-sse.php /app/
COPY classes /app/classes
COPY inc /app/inc
COPY views /app/views
COPY styles /app/styles
COPY js /app/js
COPY img /app/img
COPY vendor /app/vendor
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /entrypoint.sh

# Create necessary directories
RUN mkdir -p /app/cache /app/logs /data/caddy/locks

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

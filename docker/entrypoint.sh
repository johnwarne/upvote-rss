#!/bin/sh

USER_ID=${USER_ID:-1000}
GROUP_ID=${GROUP_ID:-1000}

CURRENT_USER_ID=$(id -u upvote-rss 2>/dev/null)
CURRENT_GROUP_ID=$(id -g upvote-rss 2>/dev/null)

if [ "$CURRENT_USER_ID" != "$USER_ID" ] || [ "$CURRENT_GROUP_ID" != "$GROUP_ID" ]; then
  if id -u upvote-rss > /dev/null 2>&1; then
    deluser upvote-rss
  fi

  if getent group upvote-rss > /dev/null 2>&1; then
    delgroup upvote-rss
  fi

  # Reuse a group that already has this GID (e.g. Alpine's "users" group is GID 100)
  GROUP_NAME=$(getent group "$GROUP_ID" | cut -d: -f1)
  if [ -z "$GROUP_NAME" ]; then
    addgroup -g "$GROUP_ID" upvote-rss
    GROUP_NAME=upvote-rss
  fi
  adduser -D -u "$USER_ID" -G "$GROUP_NAME" -s /bin/sh upvote-rss
fi

for dir in /app/cache /app/logs; do
  # Create the cache and logs directories if they don't exist
  if [ ! -d "$dir" ]; then
    echo "Creating $dir directory"
    mkdir -p "$dir"
  fi

  # Set permissions of directories
  chmod 755 "$dir"
done

# Set ownership of the app and data directories
chown -R "$USER_ID:$GROUP_ID" /app /data

# Echo the app version to stdout
APP_VERSION=$(grep "const UPVOTE_RSS_VERSION" /app/config.php | cut -d "'" -f 2)
cat << "EOF"

                        +++++
                       +++++++
                      +++++++++
                    ++++++ ++++++
                   ++++++   ++++++
                 ++++++       +++++
                ++++++         ++++++
========       ++++++           ++++++
============  +++++++++++   +++++++++++
============  +++++++++++   +++++++++++
      ========       ++++   +++++
 -----     ========    ++   +++++
----------   ========       +++++
------------   =======      +++++
    ----------  =======     +++++
        -------  ======     +++++
-------  -------  ======    +++++
--------  =-----  ======    +++++
--------   -----   =====    +++++
-------    -----   =====    +++++
EOF
echo ""
echo "================================="
echo ""
echo "   Starting Upvote RSS v$APP_VERSION"
echo ""
echo "================================="
echo ""
echo ""

exec su-exec upvote-rss "$@"

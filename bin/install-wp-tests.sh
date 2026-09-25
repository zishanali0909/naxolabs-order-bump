#!/usr/bin/env bash
#
# Installs WordPress + WP test suite for PHPUnit.
# Usage: bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
#

if [ $# -lt 3 ]; then
    echo "Usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
    exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

echo "=== Settings ==="
echo "DB_NAME: $DB_NAME"
echo "DB_HOST: $DB_HOST"
echo "WP_VERSION: $WP_VERSION"
echo "WP_TESTS_DIR: $WP_TESTS_DIR"
echo "WP_CORE_DIR: $WP_CORE_DIR"

set -e

# Determine WP_TESTS_TAG
if [[ "$WP_VERSION" == 'nightly' || "$WP_VERSION" == 'trunk' ]]; then
    WP_TESTS_TAG="trunk"
elif [[ "$WP_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    if [[ "$WP_VERSION" =~ [0-9]+\.[0-9]+\.0$ ]]; then
        WP_TESTS_TAG="tags/${WP_VERSION%??}"
    else
        WP_TESTS_TAG="tags/$WP_VERSION"
    fi
elif [[ "$WP_VERSION" =~ ^[0-9]+\.[0-9]+$ ]]; then
    WP_TESTS_TAG="branches/$WP_VERSION"
else
    # "latest" or anything else
    LATEST_VERSION=$(curl -s http://api.wordpress.org/core/version-check/1.7/ | grep -o '"version":"[^"]*' | head -1 | sed 's/"version":"//')
    if [[ -z "$LATEST_VERSION" ]]; then
        echo "ERROR: Could not determine latest WP version"
        exit 1
    fi
    echo "Latest WP version: $LATEST_VERSION"
    WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

echo "WP_TESTS_TAG: $WP_TESTS_TAG"

# ── Install WordPress core ──────────────────────────
install_wp() {
    if [ -d "$WP_CORE_DIR" ] && [ -f "$WP_CORE_DIR/wp-settings.php" ]; then
        echo "WordPress core already installed, skipping."
        return
    fi

    mkdir -p "$WP_CORE_DIR"

    if [ "$WP_VERSION" == 'latest' ]; then
        local ARCHIVE_URL="https://wordpress.org/latest.tar.gz"
    else
        local ARCHIVE_URL="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
    fi

    echo "Downloading WordPress from $ARCHIVE_URL ..."
    curl -sL "$ARCHIVE_URL" -o "$TMPDIR/wordpress.tar.gz"
    tar --strip-components=1 -zxf "$TMPDIR/wordpress.tar.gz" -C "$WP_CORE_DIR"
    rm -f "$TMPDIR/wordpress.tar.gz"
    echo "WordPress core installed!"
}

# ── Install WP test suite ───────────────────────────
install_test_suite() {
    if [ -d "$WP_TESTS_DIR" ] && [ -f "$WP_TESTS_DIR/includes/functions.php" ]; then
        echo "WP test suite already installed, skipping."
        return
    fi

    mkdir -p "$WP_TESTS_DIR"

    local BASE_URL="https://develop.svn.wordpress.org/${WP_TESTS_TAG}"

    # Try svn first, fall back to curl if svn not available
    if command -v svn &> /dev/null; then
        echo "Using svn to download test suite..."
        svn export --quiet --ignore-externals "${BASE_URL}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
        svn export --quiet --ignore-externals "${BASE_URL}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
    else
        echo "svn not found, using curl to download test suite..."
        # Download includes
        mkdir -p "$WP_TESTS_DIR/includes"
        for file in bootstrap.php functions.php install.php testcase.php utils.php factory.php; do
            curl -sL "${BASE_URL}/tests/phpunit/includes/${file}" -o "$WP_TESTS_DIR/includes/${file}" 2>/dev/null || true
        done

        # Download as zip from GitHub mirror
        echo "Downloading test suite from GitHub mirror..."
        curl -sL "https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.tar.gz" -o "$TMPDIR/wp-dev.tar.gz"
        mkdir -p "$TMPDIR/wp-dev-extract"
        tar -xzf "$TMPDIR/wp-dev.tar.gz" -C "$TMPDIR/wp-dev-extract" --strip-components=1
        cp -r "$TMPDIR/wp-dev-extract/tests/phpunit/includes/"* "$WP_TESTS_DIR/includes/" 2>/dev/null || true
        mkdir -p "$WP_TESTS_DIR/data"
        cp -r "$TMPDIR/wp-dev-extract/tests/phpunit/data/"* "$WP_TESTS_DIR/data/" 2>/dev/null || true
        rm -rf "$TMPDIR/wp-dev.tar.gz" "$TMPDIR/wp-dev-extract"
    fi

    # Download wp-tests-config.php
    curl -sL "${BASE_URL}/wp-tests-config-sample.php" -o "$WP_TESTS_DIR/wp-tests-config.php"

    # Configure wp-tests-config.php
    local WP_CORE_DIR_ESCAPED=$(echo "$WP_CORE_DIR" | sed 's:/:\\/:g')
    sed -i "s:dirname( __FILE__ ) . '/src/':'${WP_CORE_DIR}/':" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s:youremptytestdbnamehere:$DB_NAME:" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s:yourusernamehere:$DB_USER:" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s:yourpasswordhere:$DB_PASS:" "$WP_TESTS_DIR/wp-tests-config.php"
    sed -i "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"

    echo "WP test suite installed!"
}

# ── Create test database ────────────────────────────
create_db() {
    echo "Creating database $DB_NAME ..."
    mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" --host="$DB_HOST" --protocol=tcp 2>/dev/null || true
    echo "Database ready!"
}

# ── Run ──────────────────────────────────────────────
install_wp
install_test_suite
create_db

echo ""
echo "=== Installation complete ==="
echo "WP Core: $WP_CORE_DIR"
echo "WP Tests: $WP_TESTS_DIR"
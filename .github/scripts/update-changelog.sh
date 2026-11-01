#!/bin/sh
set -eu
php_binary=$(git config --get kagg-compatibility.phpPath || echo php)
"$php_binary" "$(dirname "$0")/update-changelog.php" "$@"

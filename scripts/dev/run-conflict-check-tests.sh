#!/usr/bin/env bash
# Run all conflict-check phase regression tests.
set -euo pipefail
cd "$(dirname "$0")/.."
vendor/bin/phpunit \
  tests/Feature/ConflictCheckPhase0Test.php \
  tests/Feature/ConflictCheckPhase1Test.php \
  tests/Feature/ConflictCheckPhase2Test.php \
  tests/Feature/ConflictCheckPhase3Test.php \
  tests/Feature/ConflictCheckPhase4aTest.php \
  tests/Feature/ConflictCheckPhase4bTest.php \
  tests/Feature/ConflictCheckPhase5Test.php

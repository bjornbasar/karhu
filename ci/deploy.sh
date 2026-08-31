#!/usr/bin/env bash
# karhu docs local CI/CD deploy — renders the MkDocs site, builds MULTI-ARCH (amd64 +
# arm64 for Hurska), pushes the local registry (:latest + :sha- for rollback), redeploys
# Hurska, smoke-tests.
#
# WHAT THIS PIPELINE DOES *NOT* DO: run the PHP test suite. GitHub Actions already runs
# cs-fixer + PHPStan + PHPUnit on the 8.3/8.4 matrix for every push to main, and this
# deploy publishes documentation, not the library. What it DOES gate on is the two things
# that can make the published docs wrong: API-reference drift and broken internal links.
#
# Runs on Ruxa via `git push ruxa main` (post-receive) or by hand from the checkout.
set -euo pipefail
source "${CI_LIB:-/data/git/ci-lib.sh}"

HURSKA="ubuntu@192.168.4.33"
HURSKA_DIR="/data/karhu-docs"
IMG="$REGISTRY/karhu-docs"
PORT=8100

ci_trap "→ Hurska (docs.twobots.dev/karhu/)"
ci_lock
ci_ensure_buildx

# ---------------------------------------------------------------- pre-deploy gates
# These run on RUXA against the source tree, before anything is built or pushed. A
# broken reference should fail here, not after an arm64 image has been through qemu.

# THE GATE THIS SITE EXISTS FOR. karhu's docs rotted once already — the README's
# hello-world example was fatally broken (wrong #[Route] argument order, a static call to
# an instance method) and nothing noticed, because nothing checked. check-docs.php
# reflects over src/ and fails when a public method is undocumented, when the reference
# names a method that no longer exists, or when a page cites a src/ path that has moved.
#
# It registers its own PSR-4 autoloader rather than requiring vendor/autoload.php, which
# is why a bare php:8.3-cli-alpine is enough — no composer install, ~2 seconds.
ci_log "assert the API reference still matches src/"
ci_php php tools/check-docs.php

# Every advertised composer package must actually install. 200 is NOT sufficient:
# Composer's default minimum-stability is `stable`, so a package with only dev-* versions
# resolves on Packagist and still fails to install.
#
# A definitive 404 is FATAL; a transport failure is a WARNING — a Packagist outage must
# not turn an unrelated docs commit red in #duskana.
#
# There was a KNOWN_UNPUBLISHED exception here for bjornbasar/karhu-skeleton, which was on
# GitHub but had never been submitted to Packagist — the docs named it in order to say so,
# and that necessarily put `composer create-project bjornbasar/karhu-skeleton` in the text
# this grep reads. The exception was written to FAIL rather than skip once the package
# appeared, and on 2026-08-19 it did exactly that: the skeleton was submitted, the next
# deploy went red, and the exception plus three "not on Packagist" warnings came out
# together. Every advertised package is now genuinely installable, so the plain rule
# applies with no carve-outs. Do not add one back without the same fail-when-fixed shape.
ci_log "assert every advertised composer package resolves with a stable version"
# `|| true`: grep exits 1 when it matches nothing, and under `set -euo pipefail` that
# would abort on the very case the empty branch exists to handle.
PKGS=$(grep -rhoE 'composer (require|create-project) bjornbasar/[a-z0-9-]+' docs/ \
       | awk '{print $NF}' | sort -u || true)
if [ -z "$PKGS" ]; then
  ci_log "no composer commands advertised — nothing to check"
else
  for PKG in $PKGS; do
    BODY=$(curl -sS --max-time 20 -w '\n%{http_code}' "https://repo.packagist.org/p2/$PKG.json" 2>/dev/null) || {
      ci_log "⚠ could not reach Packagist for $PKG — SKIPPING (transport failure, not a stale line)"
      continue
    }
    CODE=$(printf '%s' "$BODY" | tail -1)

    case "$CODE" in
      200) ;;
      404) ci_die "advertised package $PKG does NOT exist on Packagist — the install line in docs/ is broken" ;;
      *)   ci_log "⚠ Packagist returned $CODE for $PKG — SKIPPING (not a definitive 404)"; continue ;;
    esac
    STABLE=$(printf '%s' "$BODY" | sed '$d' | python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)['packages']
    vs = [v['version'] for pkg in d.values() for v in pkg if not v['version'].startswith('dev-')]
    print(len(vs))
except Exception:
    print('ERR')
")
    [ "$STABLE" = "ERR" ] && { ci_log "⚠ could not parse Packagist JSON for $PKG — SKIPPING"; continue; }
    [ "$STABLE" = "0" ] && ci_die "$PKG has only dev-* versions — \`composer require\` fails under the default minimum-stability"
    ci_log "resolves (correct): $PKG — $STABLE stable version(s)"
  done
fi

# A repo flipped private 404s silently, leaving dead links on a page whose whole job is
# sending people to code.
#
# The sed strips two things before the check: trailing punctuation swallowed by the grep,
# and a trailing `.git`. The docs give real clone URLs (`git clone https://…/karhu-skeleton.git`)
# and GitHub answers those with a 301 to the repo page — treating that as a dead link would
# fail the deploy on a URL that is correct.
ci_log "assert every linked GitHub repo is reachable"
for URL in $(grep -rhoE 'https://github\.com/bjornbasar/[a-zA-Z0-9._-]+' docs/ README.md \
             | sed -E 's/[.,)]*$//; s/\.git$//' | sort -u); do
  CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$URL" 2>/dev/null) || {
    ci_log "⚠ could not reach $URL — SKIPPING (transport failure)"; continue; }
  [ "$CODE" = "200" ] || ci_die "$URL returned $CODE — a linked repo is private or gone"
  ci_log "reachable (correct): $URL"
done

# ---------------------------------------------------------------------- render
# --strict turns warnings into a failed build, and mkdocs.yml sets validation.* to warn
# for unrecognised links and missing anchors — so a cross-reference to a heading that got
# renamed fails HERE rather than shipping as a dead link.
ci_log "render the MkDocs site (build --strict)"
rm -rf site
ci_mkdocs build --strict

[ -f site/index.html ] || ci_die "mkdocs produced no site/index.html"
PAGES=$(find site -name '*.html' | wc -l)
ci_log "rendered $PAGES pages"
# A nav regression that silently drops the API reference would otherwise deploy happily.
# 40 is comfortably under the ~45 pages the current nav produces and well above anything
# a partial build would emit.
[ "$PAGES" -ge 40 ] || ci_die "only $PAGES pages rendered — expected 40+; the nav has probably lost a section"

# Assert every page carries the route back to the documentation index. Added because these
# sites sit under a shared landing page and a reader who arrives on a deep link needs a way
# up — and because a theme override is exactly the kind of thing a Material upgrade can
# silently stop rendering, with no error and no visible breakage on the page itself.
#
# Checks the COUNT, not merely presence: a partial render (the override applying to some
# templates but not the 404 page, say) is the failure mode worth catching, and it looks
# identical to success if you only grep one file.
LINKED=$(grep -rl 'class="docs-up"' site --include='*.html' | wc -l)
[ "$LINKED" = "$PAGES" ] || ci_die "only $LINKED of $PAGES pages carry the 'All documentation' link — check overrides/main.html and theme.custom_dir"
ci_log "every page routes back to the index (correct): $LINKED/$PAGES"

# The canonical must name the PUBLIC host. docs.bjornbasar.com serves the same container behind
# Cloudflare Access, so a canonical pointing there would advertise a URL search engines can
# never fetch. Asserted on the rendered file rather than over the wire, so a mis-set site_url
# fails before an image is built — and so the GitHub Pages mirror, which builds from the same
# mkdocs.yml, inherits a canonical that is checked.
grep -q 'rel="canonical" href="https://docs.twobots.dev/karhu/' site/index.html \
  || ci_die "canonical is missing or points elsewhere — check site_url in mkdocs.yml"

# ---------------------------------------------------------------------- build + ship
ci_log "build + push multi-arch: $IMG (:latest + :sha-$CI_SHA)"
docker buildx build --builder multiarch --platform linux/amd64,linux/arm64 \
  -t "$IMG:latest" -t "$IMG:sha-$CI_SHA" --push .

ci_log "sync compose + redeploy on Hurska"
ssh "$HURSKA" "mkdir -p $HURSKA_DIR"
rsync -a docker-compose.yml "$HURSKA:$HURSKA_DIR/"
ssh "$HURSKA" "cd $HURSKA_DIR && docker compose pull && docker compose up -d --remove-orphans && docker image prune -f"

# ---------------------------------------------------------------------- smoke tests
ci_log "smoke-test the deployed container"
ssh "$HURSKA" "curl -sf -o /dev/null -w 'karhu-docs /karhu/ → HTTP %{http_code}\n' http://localhost:$PORT/karhu/"

# Assert the site actually deployed, not just that nginx is up. /api/http/ is the largest
# reference page; /tutorial/01-routes/ proves the nav's deepest section rendered.
# NOTE the /karhu/ prefix: the site is rooted at its public path INSIDE the container so that
# MkDocs' trailing-slash redirects stay correct behind a path-routed proxy (see nginx.conf).
for PAGE in /karhu/ /karhu/installation/ /karhu/api/ /karhu/api/http/ /karhu/tutorial/01-routes/ /karhu/packages/db/; do
  CODE=$(ssh "$HURSKA" "curl -s -o /dev/null -w '%{http_code}' http://localhost:$PORT$PAGE")
  [ "$CODE" = "200" ] || ci_die "$PAGE returned $CODE — the site did not deploy correctly"
  ci_log "serves (correct): $PAGE → 200"
done

# Search is the classic silent mkdocs failure: the page renders, the box appears, and
# nothing is ever found because the index 404s.
CODE=$(ssh "$HURSKA" "curl -s -o /dev/null -w '%{http_code}' http://localhost:$PORT/karhu/search/search_index.json")
[ "$CODE" = "200" ] || ci_die "search_index.json → HTTP $CODE — site search is broken"
ci_log "search index resolves (correct): /karhu/search/search_index.json → 200"

# THE ALLOWLIST ASSERTION. The build context is an entire PHP framework repo, so this
# matters more here than on the single-page sites: a regression in the Dockerfile COPY
# list would publish source, tests, or the raw markdown.
ci_log "assert the repo itself is not being served"
for LEAK in /src/App.php /tests/AppTest.php /composer.json /mkdocs.yml /Dockerfile /tools/check-docs.php /docs/index.md; do
  CODE=$(ssh "$HURSKA" "curl -s -o /dev/null -w '%{http_code}' http://localhost:$PORT$LEAK")
  [ "$CODE" = "404" ] || ci_die "$LEAK is being SERVED (HTTP $CODE) — check the Dockerfile COPY allowlist"
  ci_log "not served (correct): $LEAK → 404"
done

# THE PATH-ROUTING ASSERTION. mkdocs links pages as /installation/, and default nginx answers
# /karhu/installation with an absolute Location built from the listen port — behind Ayula that
# would publish `http://docs.twobots.dev:8100/karhu/installation/` to visitors.
#
# Since the docs consolidation this checks a second, sharper thing: with absolute_redirect off
# the Location is ROOT-relative, i.e. relative to the ORIGIN rather than to the proxied prefix,
# so it must already CONTAIN /karhu/ or the redirect throws visitors out of this site and into
# whatever else answers at docs.twobots.dev/installation/.
#
# This reads the RAW Location header on purpose. curl's %{redirect_url} resolves a relative
# header against the request URL, so it always looks correct and can never distinguish the
# cases — the assertion would pass or fail for the wrong reason.
ci_log "assert the trailing-slash redirect keeps the /karhu/ prefix"
LOC=$(ssh "$HURSKA" "curl -s -D- -o /dev/null http://localhost:$PORT/karhu/installation | grep -i '^location:' | tr -d '\r'")
case "$LOC" in
  *"://"*) ci_die "the redirect is absolute ($LOC) — absolute_redirect must be off" ;;
  *"/karhu/installation/"*) ci_log "redirect keeps the prefix (correct): '$LOC'" ;;
  *) ci_die "redirect LOST the /karhu/ prefix: '$LOC' — the site must be rooted at /karhu/ in the image" ;;
esac

# ---------------------------------------------------------------------- public checks
# Non-fatal by design: Cloudflare/Ayula can lag a container swap by a few seconds, and a
# deploy that succeeded on the origin should not go red in #duskana for that.
ci_log "verify the public URL (non-fatal)"
PUB=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 https://docs.twobots.dev/karhu/ 2>/dev/null || echo 000)
if [ "$PUB" = "200" ]; then
  ci_log "public (correct): https://docs.twobots.dev/karhu/ → 200"
else
  ci_log "⚠ https://docs.twobots.dev/karhu/ → $PUB (origin is healthy; check the Ayula vhost)"
fi

# The old name is a PERMANENT 301 and stays one. It was the advertised home on Packagist,
# in the README, and in every release note, so it has to keep working — a 404 here is a dead
# link on someone else's page, which is the one kind of rot this repo cannot fix later.
ci_log "verify the old name still redirects (non-fatal)"
#
# /installation/ and NOT /guides/, which is what this checked first and is a nav SECTION
# rather than a page — it 404s at the destination and always did, so the assertion passed
# while its own log line pointed at a dead URL. A redirect check should name something a
# visitor could actually have bookmarked.
OLD=$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' --max-time 20 \
      'https://framework.twobots.dev/installation/?x=1' 2>/dev/null || echo '000 -')
case "$OLD" in
  "301 https://docs.twobots.dev/karhu/installation/?x=1")
    ci_log "old name redirects with path+query intact (correct): $OLD"
    # Follow it. The 301 being well-formed is not the same as the destination existing,
    # which is the difference between a working link and a tidy-looking dead one.
    END=$(curl -sL -o /dev/null -w '%{http_code}' --max-time 25 'https://framework.twobots.dev/installation/' 2>/dev/null || echo 000)
    [ "$END" = "200" ] && ci_log "and the destination resolves (correct): 200" \
                       || ci_log "⚠ the redirect is correct but its destination returned $END" ;;
  *) ci_log "⚠ framework.twobots.dev/installation/?x=1 → $OLD (expected a 301 preserving path and query)" ;;
esac

# The gated hostname must NOT answer 200 anonymously. A 302 to the Cloudflare Access login is
# the CORRECT result, and is also why that path is probed at the origin rather than through the
# edge — blackbox follows redirects and would record a false 200.
ci_log "verify the gated hostname still gates (non-fatal)"
GATED=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 https://docs.bjornbasar.com/karhu/ 2>/dev/null || echo 000)
case "$GATED" in
  302|401|403) ci_log "gated (correct): docs.bjornbasar.com/karhu/ → $GATED" ;;
  200)         ci_log "⚠ docs.bjornbasar.com/karhu/ answered 200 ANONYMOUSLY — Cloudflare Access is not covering this path" ;;
  *)           ci_log "⚠ docs.bjornbasar.com/karhu/ → $GATED" ;;
esac

# Best-effort ghcr copy. PUBLIC: karhu is a public MIT repo and these are its docs.
ci_log "ghcr copy (best-effort)"
docker buildx build --builder multiarch --platform linux/amd64,linux/arm64 \
  -t "$GHCR_NS/karhu-docs:latest" --push . \
  || ci_log "⚠ ghcr copy failed (non-fatal; deploy already done)"

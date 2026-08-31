# The karhu documentation site at https://docs.twobots.dev/karhu/ — static nginx.
# Also answers at https://docs.bjornbasar.com/karhu/ (Cloudflare Access); same container.
#
# Built multi-arch on Ruxa (amd64 + arm64), pushed to 192.168.4.9:5000, pulled on
# Hurska (Pi, arm64). Fronted by Ayula.
#
# THIS IMAGE CONTAINS NO PHP. karhu is a library; what ships here is the rendered
# MkDocs site and nothing else. `site/` is produced by ci/deploy.sh BEFORE the build
# (see ci_mkdocs in ../ci/lib.sh) — mkdocs-material is Python, and running pip under
# arm64 emulation to produce static HTML would be minutes of waste per deploy.
#
# COPY is an explicit ALLOWLIST, and it matters more here than on the other static
# sites: the build context is a whole PHP framework repo. src/, tests/, vendor/,
# composer.json and the raw docs/ markdown must never be served. An allowlist fails
# closed — a new directory in the repo cannot leak by default. ci/deploy.sh asserts
# the important ones 404 after deploy.
FROM nginx:alpine

LABEL org.opencontainers.image.source=https://github.com/bjornbasar/karhu
LABEL org.opencontainers.image.description="karhu — framework documentation (docs.twobots.dev/karhu/)"

COPY nginx.conf /etc/nginx/conf.d/default.conf

# The rendered site ONLY. mkdocs emits index.html, the per-page directories, the
# theme's assets/, a search index, sitemap.xml, and the robots.txt copied verbatim
# from docs/.
#
# ⚠ THE /karhu/ SUBDIRECTORY IS REQUIRED, NOT COSMETIC. The site is served at its real
# public path so MkDocs' trailing-slash redirects stay correct behind a path-routed proxy —
# see the header of nginx.conf. Changing this back to `html/` breaks every directory URL.
COPY site/ /usr/share/nginx/html/karhu/

EXPOSE 80

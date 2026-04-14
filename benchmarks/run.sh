#!/usr/bin/env bash
set -euo pipefail

# Benchmark runner — compares karhu vs Slim vs Flight vs Lumen
# Requires: wrk, php 8.3+, composer
# Run from: benchmarks/

THREADS=4
CONNECTIONS=100
DURATION=30
PORT_BASE=9100
RESULTS_FILE="results.json"

echo "karhu benchmark suite"
echo "====================="
echo "wrk: $THREADS threads, $CONNECTIONS connections, ${DURATION}s"
echo ""

FRAMEWORKS=("karhu" "slim" "flight" "lumen")
declare -A PORTS
for i in "${!FRAMEWORKS[@]}"; do
    PORTS[${FRAMEWORKS[$i]}]=$((PORT_BASE + i))
done

# --- Setup each framework app ---
setup_karhu() {
    local dir="apps/karhu"
    mkdir -p "$dir"
    cat > "$dir/index.php" <<'PHP'
<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = new Karhu\App();
$app->router()->addRoute('/', ['GET'], 'handler::hello');
$app->router()->addRoute('/json', ['GET'], 'handler::json');
// Inline handler class
class handler {
    public function hello(): string { return 'Hello, World!'; }
    public function json(): array { return ['message' => 'Hello, World!']; }
}
$app->run();
PHP
}

bench_framework() {
    local name="$1"
    local port="${PORTS[$name]}"
    local dir="apps/$name"

    echo "--- $name (port $port) ---"

    # Start PHP built-in server
    php -S "127.0.0.1:$port" -t "$dir" "$dir/index.php" &>/dev/null &
    local pid=$!
    sleep 1

    # Warmup
    curl -s "http://127.0.0.1:$port/" > /dev/null 2>&1 || true

    # Benchmark hello
    echo "  GET /"
    wrk -t "$THREADS" -c "$CONNECTIONS" -d "${DURATION}s" \
        --latency "http://127.0.0.1:$port/" 2>&1 | tee "/tmp/bench-${name}-hello.txt"

    echo ""
    echo "  GET /json"
    wrk -t "$THREADS" -c "$CONNECTIONS" -d "${DURATION}s" \
        --latency "http://127.0.0.1:$port/json" 2>&1 | tee "/tmp/bench-${name}-json.txt"

    echo ""

    # Cleanup
    kill "$pid" 2>/dev/null || true
    wait "$pid" 2>/dev/null || true
}

# --- Main ---
setup_karhu

echo "Note: Slim/Flight/Lumen apps need manual setup in apps/{slim,flight,lumen}/"
echo "Running karhu benchmark only (add other frameworks to expand)."
echo ""

bench_framework "karhu"

echo ""
echo "Done. Raw output in /tmp/bench-*.txt"
echo "Install size: $(du -sh ../vendor/ 2>/dev/null | cut -f1) (karhu + dev deps)"

# Benchmark Suite

Compares karhu against Slim 4, Flight, and Lumen on equivalent hello-world and JSON-echo routes.

## Methodology (locked)

| Parameter | Value |
|-----------|-------|
| Hardware | Hurska (self-hosted CI runner) |
| Tool | wrk |
| Threads | 4 |
| Connections | 100 |
| Duration | 30s |
| OPcache | On |
| Route cache | On (karhu: `bin/karhu route:cache`) |
| PHP mode | Production (`display_errors=0`, `opcache.enable=1`) |

## Apps

Each framework serves two identical routes:

- `GET /` — returns `Hello, World!` as plain text
- `GET /json` — returns `{"message": "Hello, World!"}` as JSON

All apps are single-file, no database, no middleware beyond the default.

## Metrics reported

- Requests/sec (RPS)
- p50 latency (ms)
- p99 latency (ms)
- Runtime install size (`du -sh vendor/`)
- Core LOC (`cloc src/` or equivalent)

## Running

```bash
# Prerequisites: wrk, PHP 8.3+, composer
cd benchmarks
./run.sh
```

Results are written to `results.json` and a markdown table is printed to stdout for the README.

## Results

*Populated after running `./run.sh` on Hurska.*

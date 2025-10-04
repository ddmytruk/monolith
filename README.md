# Ticketing Platform (DDD • L0→L3)

This project is a **ticketing & seat-booking platform** built as a **DDD modular monolith** that can evolve toward microservices. It models three bounded contexts:
- **Booking/Inventory** — events, seats, holds, bookings, anti-oversell.
- **Payments** — idempotent payment intents, PSP integrations, refunds.
- **Notifications/Realtime** — email/SMS/push (later WebSockets) on booking/payment lifecycle.

## Purpose
Provide a production-minded blueprint that shows how a system grows from **L0 → L3** while preserving clean domain boundaries and strong ops fundamentals.

## Target SLOs & Throughput by Level

### L0 — Minimal Viable System
**Throughput:** ~**100 RPS**  
**SLOs:**
- API latency: **p95 < 500 ms**, **p99 < 900 ms**
- Error rate: **< 0.5%**
- Availability (monthly): **≥ 99.5%**
- Consistency: holds/booking invariants (anti-oversell **= 0**)
- Payments: auth success **≥ 95%** (single PSP), **0** double-charges

### L1 — Elevated Load
**Throughput:** ~**1,000 RPS**  
**SLOs:**
- API latency: **p95 < 350 ms**, **p99 < 700 ms**
- Error rate: **< 0.7%**
- Availability: **≥ 99.9%**
- Redis TTL holds: miss/eviction-safe; cache hit-ratio **≥ 90%**
- Queue/outbox lag: **p95 < 5 s**
- Payments: timeout rate **< 1%**, breaker open-rate **< 5%**

### L2 — High Load (Event-Driven)
**Throughput:** ~**10,000 RPS**  
**SLOs:**
- API latency: **p95 < 300 ms**, **p99 < 600 ms**
- Error rate: **< 1.0%**
- Availability: **≥ 99.9%**
- Read models (CQRS) freshness: **p95 lag < 2 s**
- Kafka consumer lag: **p95 < 5 s**; outbox lag **p95 < 5 s**
- Payments (multi-PSP): auth→capture success **≥ 98%**, **0** double-charges
- Oversell: **= 0** under flash spikes

### L3 — Very High Load (Hot-Path & Sharding)
**Throughput:** ~**100,000 RPS**  
**SLOs:**
- API latency: **p95 < 250 ms**, **p99 < 380 ms**
- Error rate: **< 1.5%**
- Availability: **≥ 99.95%**
- Inventory journal/batch commit lag: **p95 < 5 s**
- Edge snapshots staleness: **p95 < 2 s**
- Search latency: **p95 < 150 ms**
- Notifications: provider success **≥ 99%**, DLQ age **< 10 min**
- Shard balance: **max/avg load ≤ 1.3**, hot-key throttling active

## Outcomes
- A clear path from **correctness → throughput → resilience → cost-aware scale**.
- Reusable patterns: idempotency, transactional outbox, saga, CQRS, sharding, backpressure.
- Operational guardrails: SLOs with burn-rate alerts, and metrics → logs → traces drill-down.


## Traffic Profile & Read/Write Mix

This ticketing platform is **read-heavy** by design.

### Overall mix
- **Normal days:** **~90–95% reads / 5–10% writes**
- **On-sale / flash events:** **~85–90% reads / 10–15% writes**

### By domain
- **Booking/Inventory**
    - DB access: **~90:10** reads:writes (normal), budget **~80:20** in peaks
    - Redis holds (TTL): can reach **~60:40** on hot events (lightweight writes)
- **Payments:** service-internal traffic **~30–40% reads / 60–70% writes** (authorize/capture are writes). Overall site share is small (≈1–3% of total requests)
- **Notifications:** mostly writes (enqueue/send), minimal reads

### Why it matters (L0→L3)
- **L0–L1:** prioritize read efficiency (indexes, simple SELECTs), cache availability in Redis; keep DB writes ≤10–15%
- **L2:** full **CQRS** so **80–90%** of reads hit denormalized read models (lag target **p95 < 2s**)
- **L3:** hot-path inventory in memory/Redis with journal + batch commits to DB (smooth write amplification)

**Health checks for the mix**
- Cache hit-ratio (availability): **≥90%** (≥95% in calm periods)
- DB writes: no deadlocks; CPU/locks within budget with **≥2× headroom**
- Redis latency: **p95 < 5 ms**, **0 evictions** during on-sale
- Payments: auth→capture success **≥98%**, **0** double-charges




## Install & Run

```shell
cp .env.example .env
docker compose up -d
docker compose exec php composer install
docker compose exec php php artisan key:generate
docker compose exec php php artisan migrate
```

```shell
# Composer validate, syntax check, code style, static analysis,  tests, composer audit
./check.sh

# syntax
docker compose exec php bash -lc 'composer lint:syntax'
# code style (dry-run, CI-mode)
docker compose exec php bash -lc 'composer lint:style'
# autofix style locally
docker compose exec php bash -lc 'composer fix:style'
# 
docker compose exec php php artisan test
```

```shell
docker exec -it monolith-php-1 /bin/bash
```

---
title: "Load testing my own site: from a flat 150 req/s to 3,100+, and a chaos game day"
date: 2026-11-25
summary: "A measured case study on a real (small) site: a 503 storm caused by a readiness probe, the fixes that took one pod to 3,100+ req/s, and what breaking things on purpose taught me."
tags: ["SRE", "Load Testing", "Chaos Engineering", "Kubernetes"]
---

This is a personal project, not a production engagement: [belapurpremierleague.com](https://belapurpremierleague.com) is a small league site I run on a homelab Kubernetes cluster, and [Anvil](https://anvil.jamilshaikh.in) is the testing tool I built to learn what breaks under traffic. The value is the method, not the scale.

## The setup

Anvil runs [k6](https://k6.io) from temporary AWS spot machines in several regions, shaped as a "Black Friday" ramp to 10x a normal peak. It is read-only, requires DNS proof of ownership, auto-aborts above 50% errors and terminates its own machines.

## What broke

| | Start | After fixes | + edge cache |
|---|---|---|---|
| Peak throughput | ~150 req/s (flat) | 1,454 req/s | 3,130 req/s |
| Failed requests | 42% (all 503) | 0.7% | 0 of 514,599 |
| p95 at peak | 3.8 s | ~1.1-1.8 s | ~13 ms |
| Requests reaching the cluster | 100% | 100% | 0.16% |

My first guess was the Cloudflare tunnel. Recording **HTTP status codes** instead of guessing showed every failure was a 503, and Traefik's *router* metric (not its service metric) showed it was answering 503 itself: the single backend's readiness probe ran a database query, timed out under CPU starvation, and Kubernetes pulled the only pod out of the Service. Slow turned into down.

## What fixed it

- A readiness check that does not hit the database on every probe, with timeouts that tolerate load.
- A 2-second micro-cache with request coalescing on public reads.
- Stateless read replicas behind an HPA (the live auction stays on one pod because its state is in memory), routed by path.
- Raising an accidentally tiny CPU limit on the tunnel pods.
- A Cloudflare cache rule on public endpoints.

## Chaos game day

Under steady load I killed one read pod, all read pods, the single stateful pod, a tunnel pod, the database primary, and the whole read tier. Redundant tiers absorbed single failures in seconds, a DB failover was invisible to reads, and the single stateful pod was the one real outage (about 20 s, cut to about 8 s after moving migrations out of the restart path).

The finding I like most: I assumed the CDN cache would protect the site when the origin was down. It did not (`stale-if-error` made no difference), so I added a health-checked fallback at the origin and re-ran the experiment to prove it. A fix is a claim; the re-run is the proof.

## What this does not prove

Only read traffic on one endpoint, two edge locations, no soak test, no write path, and a two-node homelab. The estimates for a 500,000-user EKS/RDS/WAF design are extrapolations from these measurements, not measured.

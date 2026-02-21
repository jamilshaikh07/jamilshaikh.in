---
title: "I Gave My Homelab a Junior SRE. It Works 24/7 and Costs Me Nothing."
date: 2026-02-21
summary: "How I set up an AI agent on a dusty i3 box to monitor my Talos Kubernetes cluster, triage alerts, and post reports to Slack — for free."
tags: ["SRE", "Kubernetes", "AI", "OpenClaw", "Homelab", "Automation"]
---

I didn't build anything revolutionary. I didn't invent a new framework or write a thousand lines of code.

I just gave my homelab an AI agent that does the boring stuff — the health checks, the alert triage, the "is anything broken right now?" — and posts the answers to Slack like a teammate would.

And honestly? It feels like I hired someone.

---

## Why I Did This

I'm a Senior SRE at InfraCloud Technologies. By day, I manage production infrastructure. By night (and weekends), I run a homelab — a 5-node Talos Kubernetes cluster on Proxmox, 22 apps deployed through ArgoCD, the whole nine yards.

The problem with homelabs is that they're production for one person. When something breaks at 2 AM, there's no on-call rotation. There's no junior engineer to check the nodes. There's just me, waking up to a dead service and spending 20 minutes figuring out what happened while I was asleep.

I've done the traditional SRE thing. I've set up Prometheus. I've written Alertmanager rules. I've configured PagerDuty-style notifications. We all have.

But here's what I realized: **I don't want more alerts. I want someone to look at the alerts for me and tell me what's actually wrong.**

That's the difference. An alert says "pod CrashLoopBackOff." A junior SRE says "cloudflared is crash-looping because the tunnel credentials secret is missing from the namespace, here's the kubectl command to check."

So I built one.

---

## What I Actually Built

An [OpenClaw](https://openclaw.ai) agent running on a dusty Intel i3 box, connected to Slack, checking my cluster every 15 minutes.

That's it. That's the whole thing.

No fancy ML pipeline. No custom model training. No Kubernetes operator for AI. Just an agent with `kubectl` access and a schedule.

Here's what my `#devops` Slack channel looks like now:

> **:warning: ArgoCD Sync Status — Drift Detected**
>
> :red_circle: `app-of-apps` — **OutOfSync**, Healthy
> :red_circle: `autofix-dojo` — **Unknown**, Missing
> :red_circle: `cloudflared` — **Synced**, Progressing
> :red_circle: `metrics-server` — **OutOfSync**, Healthy
> :red_circle: `secrets` — **OutOfSync**, Healthy
>
> *5 of 22 apps need attention.*

---

> **:white_check_mark: Cluster Health Report**
> :clock3: Saturday, February 21st, 2026 — 4:37 PM
>
> **Nodes:** 5/5 Ready
> **Pods:** All healthy
> **Resource Usage:**
> `talos-cp-01` — CPU: 20%, MEM: 59%
> `talos-wk-01` — CPU: 4%, MEM: 41%
> `talos-wk-02` — CPU: 17%, MEM: 69%
> `talos-wk-03` — CPU: 4%, MEM: 61%
> `talos-wk-04` — CPU: 6%, MEM: 11%
>
> *No issues detected.*

These aren't templated Alertmanager notifications. These are an AI agent running real commands against my cluster, interpreting the output, and writing me a report. Every 15 minutes. Automatically.

When something's wrong, it doesn't just say "alert firing." It investigates — runs `kubectl describe`, checks logs, looks at events — and tells me what it found. Like a junior SRE would.

---

## The Setup (It's Simpler Than You Think)

### The Machine

I took a box that was collecting dust:

- **Intel i3-2100** (yes, from 2011)
- **8 GB RAM**
- **Ubuntu 24.04 Server** (headless)
- Sitting on my homelab VLAN

### The Stack

```
Ubuntu 24.04
└── OpenClaw 2026.2.17
    ├── Slack (Socket Mode — no public URL needed)
    ├── kubectl + talosctl + helm
    └── 6 scheduled cron jobs
```

### Installation Was Three Commands

```bash
npm install -g openclaw@latest
openclaw onboard
# Follow the wizard. Done.
```

I connected it to a Slack workspace with four channels — `#devops`, `#alerts`, `#news`, `#ai` — and started building cron jobs.

---

## The Six Agents

I think of each cron job as a little agent with one job:

| Agent | What It Does | Frequency | Channel |
|-------|-------------|-----------|---------|
| **Cluster Watcher** | Checks nodes, pods, resource usage | Every 15 min | #devops |
| **Alert Sentinel** | Catches CrashLoops, OOMKills, node failures | Every 30 min | #alerts |
| **ArgoCD Auditor** | Detects sync drift across 22 apps | Every 30 min | #devops |
| **Talos Inspector** | Monitors node health, etcd, memory, disk | Every hour | #devops |
| **AI News Curator** | Morning AI industry digest | 8:00 AM | #ai |
| **Tech News Curator** | K8s/CNCF/DevOps/Security digest | 8:15 AM | #news |

The monitoring agents are silent when everything's fine. No "all clear" spam every 15 minutes. They only speak up when something needs attention — or when they run their periodic health summary.

The Alert Sentinel is my favorite. It checks for broken pods, and when it finds one, it doesn't just list it. It investigates:

> **:rotating_light: CRITICAL ALERT**
>
> :red_circle: **cloudflared CrashLoopBackOff**
> **Affected:** `cloudflared/cloudflared-tunnel-xyz`
> **Restarts:** 47
> **Cause:** Missing secret `tunnel-credentials` in namespace
> **Suggested Fix:**
> ```kubectl get secret tunnel-credentials -n cloudflared```

That's not a Prometheus alert. That's triage. The kind of thing you'd ask a junior engineer to do before escalating to you.

---

## The Part Nobody Talks About: Delivery

Setting up the AI was the easy part. Getting it to **post to Slack correctly** took me hours.

OpenClaw cron jobs have multiple delivery modes, and I tried all of them:

- `--announce` → posted inside existing threads (not what I wanted)
- `--session main` → agent couldn't access Slack channels
- Standard delivery → target format errors

The pattern that actually works:

```bash
openclaw cron add \
  --name "cluster-health-check" \
  --every 15m \
  --session isolated \
  --no-deliver \
  --message "... prompt with openclaw message send command ..."
```

The key insight: use `--no-deliver` and have the agent call `openclaw message send` directly from its prompt. The agent decides what to say, formats it, and posts it as a new top-level message. Full control.

If you're setting up OpenClaw crons and wondering why messages land in threads instead of the channel — this is the fix.

---

## The Cost Reckoning

Here's the part that humbled me.

I set everything up with **Claude Opus 4.6** — the most capable (and most expensive) model available. Because why not? I wanted the best.

Then I looked at the numbers:

- 4 monitoring crons running 96+ times/day
- Each call sends ~15K tokens (mostly OpenClaw's system prompt)
- At Opus pricing ($15/M input, $75/M output)
- **I was using a $75/M-token reasoning model to run `kubectl get pods`**

That's like hiring a principal engineer to check if the office lights are on.

### What I Tried

**Groq (free tier):** Llama 3.3 70B at 300 tokens/sec sounded perfect. But Groq's free tier has a 12,000 TPM (tokens per minute) limit. OpenClaw's agent overhead alone is ~15K tokens. Every single request got a `413 Request Too Large`. Even the 8B model failed (6,000 TPM limit). Dead end.

**Google Gemini Flash (free tier):** 250,000 TPM. Twenty times what I need. Free API key from Google AI Studio. Took 5 minutes to configure.

### The Final Setup

| Task | Model | Cost |
|------|-------|------|
| Cluster monitoring (96/day) | Gemini 2.5 Flash | **$0** |
| Alert checking (48/day) | Gemini 2.5 Flash | **$0** |
| ArgoCD audit (48/day) | Gemini 2.5 Flash | **$0** |
| Talos health (24/day) | Gemini 2.5 Flash | **$0** |
| AI news (1/day) | Claude Opus 4.6 | Paid |
| Tech news (1/day) | Claude Opus 4.6 | Paid |
| Slack conversations | Claude Opus 4.6 | Paid |

**216 daily monitoring calls moved to free.** Claude only runs for the 2 morning news digests (which actually need reasoning and web search) and my interactive Slack conversations.

API costs dropped roughly **80-85%.**

The key: OpenClaw supports `per-cron-job model overrides`. Each job in `~/.openclaw/cron/jobs.json` can specify its own model:

```json
{
  "payload": {
    "model": "google/gemini-2.5-flash",
    "message": "your monitoring prompt..."
  }
}
```

No router needed. No middleware. Just tell each job which model to use.

---

## The Architecture

```
┌─────────────────────────────────────────────┐
│               Slack Workspace                │
│   #devops    #alerts    #news    #ai         │
└──────────────────┬──────────────────────────┘
                   │ Socket Mode
┌──────────────────▼──────────────────────────┐
│           OpenClaw Gateway                    │
│           (bare metal i3 box)                 │
│                                               │
│   Monitoring ──► Gemini 2.5 Flash (FREE)     │
│   News/Chat  ──► Claude Opus 4.6 (PAID)     │
│                                               │
│   Tools: kubectl, talosctl, helm, bash        │
└──────────────────┬──────────────────────────┘
                   │
┌──────────────────▼──────────────────────────┐
│       Talos Kubernetes Cluster                │
│   1 Control Plane + 4 Workers                │
│   ArgoCD │ Prometheus │ Grafana │ Cilium      │
│   MetalLB │ Longhorn │ cert-manager           │
└─────────────────────────────────────────────┘
```

---

## What This Feels Like

I'll be honest — I didn't expect it to feel this different.

I've had Prometheus alerts for years. I've had Grafana dashboards. I've had scripts that email me when something's wrong.

But there's something about opening Slack in the morning and seeing a formatted report that says "5 of 22 ArgoCD apps need attention, here are the ones drifting" that feels like having a teammate. Not a monitoring tool. A teammate.

When my `cloudflared` pod was crash-looping, the bot didn't just fire an alert and go silent. It ran `kubectl describe`, found the missing secret, and said "here's what's wrong and here's how to fix it." I still had to approve the fix myself — the agents are read-only, they never auto-remediate — but the investigation was already done.

That saved me 15 minutes of debugging. Multiply that by every incident, every day.

This isn't a replacement for proper monitoring. I still have Prometheus and Alertmanager. But now I also have something that reads those signals and translates them into actionable context. The way a human would.

---

## Things I Learned

**1. The prompt IS the product.** The difference between a pale, useless Slack message and a beautifully formatted report with emoji indicators and per-node metrics? It's all in the prompt. I give each cron job an exact Slack-formatted template. Even a small model follows it perfectly.

**2. Groq's free tier won't work for agentic workflows.** The TPM limits (6K-12K) are too low for any agent framework that has its own system prompt. Google AI Studio (250K TPM) is the practical choice for free automated tasks.

**3. The delivery mechanism is the hardest part.** Setting up the AI took 10 minutes. Getting messages to land correctly in Slack channels took hours. The `--session isolated --no-deliver` + `openclaw message send` pattern is the one that works.

**4. Not every task needs a reasoning model.** Running `kubectl get nodes` and formatting the output doesn't need Claude Opus. It needs a model that can follow instructions and call tools. Gemini Flash does that perfectly at zero cost.

**5. Read-only agents are the right default.** My agents can investigate anything — logs, events, resource usage. But they can never `kubectl delete`, `argocd app sync`, or `talosctl reboot`. They suggest fixes. I approve them. This is the right boundary for now.

---

## What's Next

This is Phase 1. The junior SRE can observe and report. Next:

- **Approval-gated remediation:** Agent proposes a fix in Slack, I react with :white_check_mark:, it executes
- **Multi-agent split:** Dedicated agents for monitoring, automation, and intelligence
- **Incident correlation:** Connecting alerts across tools (Prometheus + ArgoCD + Talos) into a single incident narrative

But for now? I have something that works. My homelab has a night shift.

And it cost me a dusty i3 box and a free Google API key.

---

*I'm Jamil Shaikh, Senior SRE at InfraCloud Technologies. I run infrastructure that mostly runs itself. Find me on [LinkedIn](https://linkedin.com/in/jamilshaikh07) and [GitHub](https://github.com/jamilshaikh07).*

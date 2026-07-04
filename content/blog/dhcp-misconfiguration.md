---
title: "48 Days of Silent Misconfiguration: How a Wrong DHCP Gateway Took Down My Kubernetes Cluster"
date: 2026-06-10
summary: "Full local version of my Medium post on 48 Days of Silent Misconfiguration."
tags: ["DevOps", "SRE", "Infrastructure"]
---

# 48 Days of Silent Misconfiguration: How a Wrong DHCP Gateway Took Down My Kubernetes Cluster

7 min read

·

3 days ago

\--

Share

 _A real prod incident, a 5-step root cause chain, and the two OPNsense gotchas nobody warns you about._

It started, as it always does, with a vague feeling that something was wrong.

My Mattermost alerts had gone quiet. Too quiet. I run an AI SRE agent - OpenClaw - on my homelab Kubernetes cluster that’s supposed to ping me when pods are crashing. When it stops talking, it usually means one of two things: everything is perfect, or everything is on fire.

It was the second one.

kubectl get pods

Half my cluster was down. And I had no idea why - because the thing that was supposed to tell me was one of the casualties.

Before diving in, some context. This is a self-hosted homelab running:

  * **Talos Linux** on two VMs (control plane + worker) in Proxmox
  * **OPNsense** as the router/firewall - also a VM in Proxmox
  * **Cilium** as the CNI
  * **CoreDNS** for in-cluster DNS, forwarding upstream to OPNsense’s Unbound resolver
  * **Cloudflare tunnel** (`cloudflared`) for public ingress

The network topology: Talos nodes live on `192.168.60.0/24`, OPNsense's LAN is `192.168.60.1`, its WAN is on `10.20.0.0/24` (the Proxmox bridge), and the real internet gateway is `10.20.0.1`.

Everything had been running fine for 48 days. Not a single intervention.

## Act 1: The Misleading Symptoms

The first thing I noticed was `ImagePullBackOff` everywhere. Images from `ghcr.io` were timing out:
    
    
    Failed to pull image "ghcr.io/foo/bar:latest":  
      dial tcp 20.207.73.86:443: i/o timeout

That looks like a network problem. But then I checked the `cloudflared` logs:
    
    
    ERR edge discovery: error looking up Cloudflare edge IPs:  
      lookup _v2-origintunneld._tcp.argotunnel.com on 9.9.9.9:53:  
      read udp 10.244.1.150:58410->9.9.9.9:53: i/o timeout

DNS. It’s always DNS.

Except - it wasn’t just DNS. Testing from my Proxmox host (`alif`):
    
    
    $ dig +short [@192](http://twitter.com/192).168.60.1 ghcr.io  
    # → SERVFAIL$ dig +short @1.1.1.1 ghcr.io  
    # → 20.207.73.86  ✓

OPNsense’s Unbound resolver was returning `SERVFAIL` for every external domain. But querying Cloudflare directly worked fine. So the problem was between OPNsense and the internet, not the internet itself.

## Act 2: Time Sync Was a Red Herring (Sort Of)

Before I could get to OPNsense, I had a more urgent problem: the Kubernetes API was intermittently refusing connections. `kubectl` would time out randomly.

The culprit: **Talos nodes blocked on time sync at epoch 0**.

Without working DNS, the NTP lookup for `pool.ntp.org` was failing. Without NTP, Talos's time was at epoch 0 (January 1, 1970). With time at epoch 0, TLS certificates were failing validation, etcd was unhappy, and the Kubernetes API would sporadically die.

Fix: patch the Talos machine config to disable time sync and set a static nameserver.
    
    
    talosctl -n 192.168.60.40 patch machineconfig --patch \  
      '{"machine":{"network":{"nameservers":["192.168.60.1"]},"time":{"disabled":true}}}'talosctl -n 192.168.60.41 patch machineconfig --patch \  
      '{"machine":{"network":{"nameservers":["192.168.60.1"]},"time":{"disabled":true}}}'

Cluster API came back. Now back to the actual problem.

## Act 3: Root Cause #1 - The Wrong Gateway

I SSH’d into the OPNsense console and ran the obvious tests:
    
    
    $ ping -c3 1.1.1.1  
    # 100% packet loss$ ping 10.20.0.3  
    # Destination Net Unreachable$ ping 10.20.0.1  
    # 0% packet loss ✓

OPNsense had a default route pointing at `10.20.0.3`. That IP was replying - it wasn't dead - but it was returning "Destination Net Unreachable" for anything that needed to reach the internet. `10.20.0.1` was the real gateway, and it worked fine.

Checking the routing table:
    
    
    $ netstat -rn  
    Destination    Gateway        Flags  
    0.0.0.0        10.20.0.3      UGS     ← wrong

The fix was straightforward once found. In OPNsense: `System > Gateways > Configuration` → edit `WAN_DHCP` → set gateway IP to `10.20.0.1`.

But how did it get `10.20.0.3` in the first place?

**OPNsense’s WAN interface uses DHCP.** The DHCP server on `10.20.0.0/24` was providing `10.20.0.3` as the router option - probably a misconfigured or stale DHCP server on that subnet. OPNsense learned that gateway on boot and faithfully used it for **48 days** without anyone noticing, because nothing had needed to _pull_ from the internet in a way that would surface the failure.

The fix: override the gateway IP statically in OPNsense’s gateway config. This takes precedence over whatever DHCP hands you.

After fixing the gateway, I restarted Unbound:
    
    
    $ dig +short @192.168.60.1 ghcr.io  
    20.207.73.86  ✓

DNS was working. Pod restarts began. And then… image pulls still failed.

## Act 4: Root Cause #2 - The Silent NAT

I ran a quick connectivity test from inside the cluster using a cached `busybox` image (important - if the image isn't cached, the test pod itself can't start):
    
    
    kubectl run nettest -n kube-system --rm -i \  
      --image=busybox:1.36 \  
      --restart=Never \  
      --overrides='{"spec":{"imagePullPolicy":"IfNotPresent","securityContext":{"runAsNonRoot":false}}}' \  
      -- sh -c "nc -zvw5 20.207.73.86 443"
    
    
    # → Connection timed out

DNS resolved. The IP was correct. But TCP was dying. The packet was leaving the pod, reaching the Talos node, hitting OPNsense - and then nothing.

I opened `Firewall > NAT > Outbound` in OPNsense.

The mode was set to **“Automatic outbound NAT rule generation.”**

The automatic rules table was **completely empty.**

OPNsense was not NATing a single packet from `192.168.60.0/24`. Every outbound TCP connection from the Talos nodes was going into the internet with its private source IP (`192.168.60.41`) intact - and of course the internet was dropping it.

Why was the table empty? I don’t have a definitive answer. Possibly a bug in that version of OPNsense with certain WAN configurations, possibly something that happened during initial setup. The auto-generation mode is supposed to create masquerade rules automatically - but it silently didn’t.

**The fix:** Switch to Manual outbound NAT and add the rule yourself

**Critical gotcha:** When you switch to Manual and OPNsense auto-creates a rule for you, it sets the Source to `LAN address`. That sounds right but it's not. `LAN address` in OPNsense means `192.168.60.1` - the gateway IP itself. Only OPNsense's own traffic would be NATed. You need `192.168.60.0/24` (or select `LAN net` from the dropdown) to cover every host on the LAN.

I changed the source, saved, applied.
    
    
    # Same test, immediately after:  
    nc -zvw5 20.207.73.86 443  
    # → 20.207.73.86 (20.207.73.86:443) open ✓

## Act 5: Full Recovery
    
    
    kubectl delete pods -n kubelet-serving-cert-approver --all  
    kubectl delete pods -n openclaw --all
    
    
    $ kubectl get pods -A | grep -v Running  
    # (nothing)

All pods healthy. Cloudflare tunnel re-established. Mattermost alerts resumed. OpenClaw went back to watching the cluster.

Total wall-clock time: **~3–4 hours**. Actual diagnosis time once I stopped chasing Talos: **~45 minutes**.

## The Full Failure Chain
    
    
    DHCP server on 10.20.0.0/24 provides wrong gateway (10.20.0.3)  
      └─► OPNsense WAN default route: 10.20.0.3 (returns Destination Net Unreachable)  
            └─► OPNsense cannot reach the internet  
                  └─► Unbound DNS forwarding to 1.1.1.1 fails → SERVFAIL  
                        └─► CoreDNS upstream queries fail → cluster DNS broken  
                              └─► NTP lookups fail → Talos time sync breaks → kube API unstable  
                              └─► Image pulls fail → all pods needing new images crash  
                              └─► cloudflared can't resolve argotunnel.com → tunnel down  
                              └─► external-dns can't resolve → crash-looping
    
    
    + Separately, running in parallel for 48 days:  
      Outbound NAT auto-generation silently producing 0 rules  
        └─► All LAN traffic leaving OPNsense with private source IPs  
              └─► Internet drops all return traffic

Two independent bugs, both silent, both present since initial setup. They only became visible when something tried to make a fresh outbound connection.

## Why Did It Take 48 Days to Surface?

Because most of the cluster was already running. Existing pods had cached container images - `imagePullPolicy: IfNotPresent` meant they never needed to contact `ghcr.io` again. DNS TTLs and caches kept things looking healthy. Services that only talk internally were completely unaffected.

It surfaced the day something needed a fresh image pull that wasn’t cached. That one pull hit the broken NAT, timed out, and triggered a cascade of restarts that exposed everything else.

## Lessons

**1\. DNS failures cascade in non-obvious ways.**  
`ImagePullBackOff` doesn't say "DNS is broken." It says `dial tcp: i/o timeout`. You have to work backwards from the TCP failure through DNS through the upstream resolver to the WAN gateway. Each layer hides the one beneath it.

**2\. OPNsense “Automatic NAT” can silently generate zero rules.**  
Always open `Firewall > NAT > Outbound` after initial OPNsense setup and verify the automatic rules table actually has entries. If it's empty, switch to manual.

**3.**`**LAN address**`**≠**`**LAN net**`**in OPNsense NAT rules.**  
`LAN address` = `192.168.60.1` (the gateway). `LAN net` = `192.168.60.0/24` (every host). Get this wrong and only OPNsense itself gets internet - all your VMs and containers are silently blocked.

**4\. DHCP-provided gateways can be wrong.**  
If your OPNsense WAN interface uses DHCP, the gateway it learns might not be the real one. Check `System > Gateways > Configuration` and verify the gateway IP. Override it statically if you can't fix the DHCP server.

**5\. Test from the firewall itself first.**  
`ping 1.1.1.1` from the OPNsense console takes 10 seconds and immediately tells you whether the WAN has internet. Do this before spending an hour debugging Kubernetes network policies.

## Quick OPNsense + k8s DNS Triage

Save this for next time.
    
    
    # From your hypervisor/jump host:  
    # 1. Is OPNsense resolving external DNS?  
    dig @<opnsense-lan-ip> ghcr.io +time=3# 2. From OPNsense console - can it reach the internet?  
    ping -c3 1.1.1.1  
    drill ghcr.io @1.1.1.1# 3. What's the default route?  
    netstat -rn | head -5# 4. Can pods reach the internet? (use a cached image!)  
    kubectl run nettest -n kube-system --rm -i \  
      --image=busybox:1.36 \  
      --restart=Never \  
      --overrides='{"spec":{"imagePullPolicy":"IfNotPresent","securityContext":{"runAsNonRoot":false}}}' \  
      -- sh -c "nc -zvw5 1.1.1.1 53 && echo DNS OK; nc -zvw5 20.207.73.86 443 && echo HTTPS OK"# 5. Check NAT rules in OPNsense UI:  
    # Firewall > NAT > Outbound  
    # Automatic rules table must NOT be empty  
    # Or: manual rule with Source = 192.168.60.0/24 (not "LAN address")

The cluster’s been clean since. And the AI SRE agent - once its own ImagePullBackOff was resolved - immediately started alerting on the remaining stragglers. There’s something poetic about the watchdog being the thing you have to rescue first.

Press enter or click to view image in full size

This came after everything was working fine :D

 _Running Talos + OPNsense + Proxmox as a homelab. Full GitOps stack at_[ _github.com/jamilshaikh07/talos-proxmox-gitops_](https://github.com/jamilshaikh07/talos-proxmox-gitops) _._

[LinkedIN](https://www.linkedin.com/in/jamilshaikh07/)  
[Github](https://github.com/jamilshaikh07)

---
title: "🚧 From “No Operating System” to Kubernetes HA: My Real Talos Linux Bare Metal Journey"
date: 2025-10-12
summary: "Full local version of my Medium post on 🚧 From “No Operating System” to Kubernetes HA."
tags: ["DevOps", "SRE", "Infrastructure"]
---

# 🚧 From “No Operating System” to Kubernetes HA: My Real Talos Linux Bare Metal Journey

3 min read

·

Aug 5, 2025

\--

1

Share

## How a Single Command (“apply-config”) Saved My Cluster After Hours of Headaches

## Introduction

Deploying a Talos Linux HA Kubernetes cluster on _bare metal_ was supposed to be a weekend homelab upgrade. Instead, it became a battle with boot menus, cryptic logs, and command-line “gotchas.”  
Here’s the **real story** of how I almost gave up (and nearly went the Proxmox route), what tripped me up, and the lessons that got my cluster online.

## The Setup

  * 3x physical servers (no virtualization, no cloud)
  * Talos Linux v1.10, Kubernetes v1.33 “Octarine”
  * Static IPs, Cilium CNI, VIP-based HA control plane
  * No SSH, no manual hacks - just Talos and API-driven everything

## What Went Wrong: Top Issues & Fixes

## 1\. Disk Order Confusion

**Symptom:**  
My SSD appeared as `/dev/sdb` when booting from USB, not `/dev/sda`.

**Why:**  
Linux enumerates devices in the order it discovers them. When booting from USB, the USB stick is often `/dev/sda`, and your real SSD is `/dev/sdb`.

**Lesson:**  
Check disk order with `talosctl get disks ...` before running any install commands!

## 2\. The Reboot Trap: “No Operating System Found”

**Symptom:**  
After running what I thought was the install, rebooting gave me “No operating system found.”

**What I did wrong:**  
I used:
    
    
    talosctl apply ...

instead of:
    
    
    talosctl apply-config --nodes <NODE_IP> --file controlplane.yaml --insecure

**What’s the difference?**

  * `talosctl apply-config` is **mandatory** for pushing the Talos configuration to a bare metal node.
  * `talosctl apply` is only for Kubernetes resource manifests (after your cluster is up).

**Lesson:**  
Don’t confuse the two - your cluster depends on it!

## 3\. “No Route to Host” for the VIP

**Symptom:**  
Flood of logs:  
`connect: no route to host 10.20.0.50:6443`

**Why:**  
In HA clusters, the API VIP isn’t live until after you bootstrap etcd.  
**Ignore these errors until you run:**
    
    
     talosctl bootstrap -n <any_control_plane_node_ip>

## 4\. CSR Approval and Pod Security Hurdles

  * **CSR Approval:**  
Kubelet server certificate rotation will fail unless you manually approve CSRs or deploy a serving cert approver.
  * **PodSecurity:**  
Default policies are strict in Kubernetes v1.33 - add securityContext to your manifests.

## The “Almost Proxmox” Moment

After hours of:

  * Power cycling servers
  * Watching the same errors
  * Feeling stuck in a loop

…I was _this_ close to wiping everything and using Proxmox for virtualization.  
But with a fresh mind (and a lot of Googling), I realized my main error:  
**I’d never run**`**apply-config**`**, so my nodes never joined the cluster.**

## How I Got It Working

  1. **Boot from Talos USB, get node IP**

    
    
     talosctl apply-config --nodes <IP> --file controlplane.yaml --insecure

  1. Wait for install to complete
  2. Remove USB, reboot, and let Talos boot from disk
  3. Repeat for all control plane nodes
  4. Once all nodes are online, **run the bootstrap!**
  5. Fetch kubeconfig, install Cilium, and start deploying workloads

## Final Thoughts and Takeaways

  * The difference between `apply` and `apply-config` is the difference between “nothing works” and “cluster online.”
  * Don’t reboot or remove install media until you’re _sure_ Talos is installed on disk.
  * Expect error logs during initial cluster formation - don’t panic.
  * Stick with it. Most issues are just “one missed step” away from being solved.

## Would I Do It Again?

Absolutely - but now I know to read the docs _and_ trust the process.  
Bare metal K8s isn’t for the faint-hearted, but the victory is sweet when you finally see all nodes healthy.

**Have questions, or stuck on your own Talos journey? Drop a comment or DM - I’m happy to help!**

## Appendix: My Cheat Sheet for Next Time
    
    
    # Install Talos OS | Apply config (crucial!)  
    talosctl apply-config --nodes <IP> --file controlplane.yaml --insecure  
      
    # Bootstrap cluster (after all nodes are online)  
    talosctl bootstrap -n <any_control_plane_node_ip>  
      
    # Approve any pending CSRs if needed  
    kubectl get csr | awk '/Pending/ {print $1}' | xargs -r kubectl certificate approve

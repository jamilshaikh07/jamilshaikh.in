---
title: "🛠️ Crossplane Series - Part 1. From Minikube to Multi-Cloud: Getting…"
date: 2025-08-25
summary: "🛠️ Crossplane Series - Part 1. From Minikube to Multi-Cloud: Getting… - locally hosted version of my Medium article."
tags: ["Medium", "SRE", "DevOps"]
---

# 🛠️ Crossplane Series - Part 1

[![Jamil Shaikh](https://miro.medium.com/v2/resize:fill:64:64/0*L7O5I1i08awbNAA6.jpeg)](/@justasflash?source=post_page---byline--6d493e63d2e5---------------------------------------)

[Jamil Shaikh](/@justasflash?source=post_page---byline--6d493e63d2e5---------------------------------------)

3 min read

·

Jul 28, 2025

[](/m/signin?actionUrl=https%3A%2F%2Fmedium.com%2F_%2Fvote%2Fp%2F6d493e63d2e5&operation=register&redirect=https%3A%2F%2Fmedium.com%2F%40justasflash%2F%25EF%25B8%258F-crossplane-series-part-1-6d493e63d2e5&user=Jamil+Shaikh&userId=ff88d50a7f39&source=---header_actions--6d493e63d2e5---------------------clap_footer------------------)

\--

[](/m/signin?actionUrl=https%3A%2F%2Fmedium.com%2F_%2Frepost%2Fp%2F6d493e63d2e5&operation=register&redirect=https%3A%2F%2Fmedium.com%2F%40justasflash%2F%25EF%25B8%258F-crossplane-series-part-1-6d493e63d2e5&user=Jamil+Shaikh&userId=ff88d50a7f39&source=---header_actions--6d493e63d2e5---------------------repost_header------------------)

[](/m/signin?actionUrl=https%3A%2F%2Fmedium.com%2F_%2Fbookmark%2Fp%2F6d493e63d2e5&operation=register&redirect=https%3A%2F%2Fmedium.com%2F%40justasflash%2F%25EF%25B8%258F-crossplane-series-part-1-6d493e63d2e5&source=---header_actions--6d493e63d2e5---------------------bookmark_footer------------------)

[Listen](/m/signin?actionUrl=https%3A%2F%2Fmedium.com%2Fplans%3Fdimension%3Dpost_audio_button%26postId%3D6d493e63d2e5&operation=register&redirect=https%3A%2F%2Fmedium.com%2F%40justasflash%2F%25EF%25B8%258F-crossplane-series-part-1-6d493e63d2e5&source=---header_actions--6d493e63d2e5---------------------post_audio_button------------------)

Share

## From Minikube to Multi-Cloud: Getting Started with Crossplane Locally

>  _What if Kubernetes could not only manage pods and services - but also your cloud infrastructure?  
>  That’s where _**_Crossplane_** _comes in._

## 🌱 Why This Series?

In this series, I’ll walk you through setting up and using **Crossplane** - a Kubernetes-native Infrastructure as Code (IaC) engine. We’ll start simple, using **Minikube** , and gradually level up to manage resources across **AWS** , **Azure** , **GCP** , and even **Talos Linux** clusters.

> _🧠 Think of it as Terraform + GitOps + K8s controller pattern - all declarative and composable._

## 🎯 Part 1 Goal

In this post, we’ll:

  * Install Crossplane on a local **Minikube** cluster.
  * Use the `provider-kubernetes` to manage Kubernetes resources.
  * Create a **Namespace** using Crossplane’s CRDs.
  * Understand the **end-to-end workflow**.

## 🔧 Prerequisites

  * Docker + Minikube installed
  * kubectl configured to talk to Minikube
  * Helm installed

## 🚀 Step 1: Start Minikube
    
    
    minikube start

## 📦 Step 2: Install Crossplane via Helm
    
    
    kubectl create namespace crossplane-system  
      
    helm repo add crossplane-stable https://charts.crossplane.io/stable  
    helm repo update  
    helm install crossplane crossplane-stable/crossplane \  
      --namespace crossplane-system

Verify:
    
    
    kubectl get pods -n crossplane-system

## 🔌 Step 3: Install `provider-kubernetes`

Crossplane uses **providers** to talk to external systems.  
In this case, we’ll use a provider that allows Crossplane to manage Kubernetes resources like `Deployment`, `Service`, `Namespace`, etc.
    
    
    # provider-kubernetes.yaml  
    apiVersion: pkg.crossplane.io/v1  
    kind: Provider  
    metadata:  
      name: provider-kubernetes  
    spec:  
      package: xpkg.upbound.io/crossplane-contrib/provider-kubernetes:v0.10.0

Apply it:
    
    
    kubectl apply -f provider-kubernetes.yaml

## 🧩 Step 4: Configure Provider Access (RBAC + ProviderConfig)

Give the provider the right permissions:
    
    
    # crossplane-provider-k8s-rbac.yaml  
    apiVersion: rbac.authorization.k8s.io/v1  
    kind: ClusterRole  
    metadata:  
      name: crossplane-provider-kubernetes  
    rules:  
      - apiGroups: [""]  
        resources: ["namespaces"]  
        verbs: ["get", "list", "watch", "create", "update", "patch", "delete"]  
    ---  
    apiVersion: rbac.authorization.k8s.io/v1  
    kind: ClusterRoleBinding  
    metadata:  
      name: crossplane-provider-kubernetes-binding  
    roleRef:  
      apiGroup: rbac.authorization.k8s.io  
      kind: ClusterRole  
      name: crossplane-provider-kubernetes  
    subjects:  
      - kind: ServiceAccount  
        name: <provider-sa-name> # get the service account name from provider-kubernetes pod   
        namespace: crossplane-system

Get the exact service account name, Identify the pod name for the provider-kubernetes, then:
    
    
    kubectl get pods -n crossplane-system  
    kubectl get pod <pod-name> -n crossplane-system -o jsonpath='{.spec.serviceAccountName}'

Then create the `ProviderConfig`:
    
    
    # providerconfig.yaml  
    apiVersion: kubernetes.crossplane.io/v1alpha1  
    kind: ProviderConfig  
    metadata:  
      name: local-cluster  
    spec:  
      credentials:  
        source: InjectedIdentity

Apply:
    
    
    kubectl apply -f providerconfig.yaml

## 📄 Step 5: Declare a Kubernetes Resource with Crossplane

Let’s create a Namespace using Crossplane’s `Object` CRD:
    
    
    # object-namespace.yaml  
    apiVersion: kubernetes.crossplane.io/v1alpha1  
    kind: Object  
    metadata:  
      name: demo-namespace  
    spec:  
      forProvider:  
        manifest:  
          apiVersion: v1  
          kind: Namespace  
          metadata:  
            name: crossplane-demo  
      providerConfigRef:  
        name: local-cluster

Apply it:
    
    
    kubectl apply -f object-namespace.yaml

Check:
    
    
    kubectl get ns crossplane-demo

✅ If it appears, congrats - you just managed Kubernetes using Crossplane from within Kubernetes!

## 🧠 How It Works (Under the Hood)
    
    
    1. You declare a CR (Object)  
    2. Crossplane controller sees it  
    3. provider-kubernetes picks it up  
    4. Authenticates using InjectedIdentity  
    5. Applies the manifest to the API server  
    6. Reconciles continuously to keep it in sync

This pattern is **GitOps-friendly** , **modular** , and **extendable**.

## 🌍 What’s Next?

This is just the beginning.

In the coming parts, we’ll:

  * ✅ Install Crossplane providers for **AWS** , **GCP** , and **Azure**
  * ✅ Create VPCs, S3 buckets, SQL databases from Minikube
  * ✅ Use Crossplane **Compositions** and **XRDs** to abstract infra
  * ✅ Build GitOps pipelines to manage **multi-cloud infrastructure**
  * ✅ Finally, run Crossplane from a **Talos Linux** K8s cluster on bare metal 💥

## 💬 Wrap-up

You’ve now installed Crossplane, connected it to your local cluster, and created infra as code from within Kubernetes itself. That’s a solid first step into a powerful, unified world of cloud-native infrastructure control.

> _🚀 The cloud may be vast, but Crossplane brings it to your fingertips - one YAML at a time._

## 💡 Stay Tuned

Follow the series and leave comments for what you’d like to see next!

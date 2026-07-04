---
title: "Deploying GitOps and Observability Stack with Crossplane: A Complete Guide - Part 1 [continued…]"
date: 2025-08-29
summary: "Full local version of my Medium post on Deploying GitOps and Observability Stack with Crossplane."
tags: ["DevOps", "SRE", "Infrastructure"]
---

# Deploying GitOps and Observability Stack with Crossplane: A Complete Guide - Part 1 [continued…]

5 min read

·

Jul 29, 2025

\--

Share

## Introduction

In this comprehensive guide, we’ll walk through deploying a complete GitOps and observability stack using Crossplane on Kubernetes. We’ll deploy ArgoCD, FluxCD, and Prometheus stack with Grafana, all managed as infrastructure as code through Crossplane.

## What We’ll Deploy

  * ArgoCD: GitOps continuous delivery tool
  * FluxCD: GitOps toolkit for Kubernetes
  * Prometheus Stack: Complete monitoring solution with Grafana, AlertManager, and exporters

## Prerequisites

  * Kubernetes cluster (we used Minikube)
  * Crossplane installed
  * Crossplane providers: `provider-kubernetes` and `provider-helm`

## Architecture Overview
    
    
    ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐  
    │     ArgoCD      │    │     FluxCD      │    │   Prometheus    │  
    │   (argocd ns)   │    │   (fluxcd ns)   │    │(observability ns)│  
    └─────────────────┘    └─────────────────┘    └─────────────────┘  
             │                       │                       │  
             └───────────────────────┼───────────────────────┘  
                                     │  
                        ┌─────────────────┐  
                        │   Crossplane    │  
                        │  (Helm Provider)│  
                        └─────────────────┘

## Step 1: Setting Up Crossplane Providers

First, we needed to ensure our Crossplane providers were healthy:
    
    
    kubectl get providers -o wide

## Initial Challenge: Provider Health Issues

We initially faced provider health issues where the Helm provider showed as “HEALTHY: False”. This was due to:

  * Container startup time during provider initialization
  * Image pulling delays
  * Health check validation periods

Resolution: The provider automatically became healthy after the startup process completed (~90 seconds).

## Step 2: Configuring Provider Configurations

We set up two different provider configurations:

## Kubernetes Provider Config
    
    
    # local-cluster-provider.yaml  
    apiVersion: kubernetes.crossplane.io/v1alpha1  
    kind: ProviderConfig  
    metadata:  
      name: local-cluster  
    spec:  
      credentials:  
        source: InjectedIdentity

## Helm Provider Config
    
    
    # helm-provider-config.yaml (referenced from context)  
    apiVersion: helm.crossplane.io/v1beta1  
    kind: ProviderConfig  
    metadata:  
      name: helm-provider  
    spec:  
      credentials:  
        source: InjectedIdentity

## Key Learning: Multiple ProviderConfig Types

We discovered that different providers have their own ProviderConfig CRDs:

  * `providerconfig.kubernetes.crossplane.io` for Kubernetes resources
  * `providerconfig.helm.crossplane.io` for Helm releases

To view all configs:
    
    
    kubectl get providerconfig.kubernetes.crossplane.io  
    kubectl get providerconfig.helm.crossplane.io

## Step 3: Creating Dedicated Namespaces

We created specific namespaces for each application using Crossplane Objects:
    
    
    # specific-namespaces.yaml  
    apiVersion: kubernetes.crossplane.io/v1alpha1  
    kind: Object  
    metadata:  
      name: argocd-namespace  
    spec:  
      forProvider:  
        manifest:  
          apiVersion: v1  
          kind: Namespace  
          metadata:  
            name: argocd  
            labels:  
              app: argocd  
              managed-by: crossplane  
      providerConfigRef:  
        name: local-cluster

Why Separate Namespaces?

  * Isolation and security boundaries
  * Easier resource management
  * Clear separation of concerns
  * Simplified RBAC policies

## Step 4: Deploying Applications with Helm Releases

## ArgoCD Deployment
    
    
    # argocd-helm-release.yaml  
    apiVersion: helm.crossplane.io/v1beta1  
    kind: Release  
    metadata:  
      name: argocd  
    spec:  
      forProvider:  
        chart:  
          name: argo-cd  
          repository: https://argoproj.github.io/argo-helm  
          version: "5.51.6"  
        namespace: argocd  
        skipCreateNamespace: true  # Important: We handle namespace creation separately  
        values:  
          server:  
            service:  
              type: LoadBalancer  
            extraArgs:  
              - --insecure  # For easier local development  
          applicationSet:  
            enabled: true  
        wait: true  
        waitTimeout: 600s  
      providerConfigRef:  
        name: helm-provider

## FluxCD Deployment
    
    
    # fluxcd-helm-release.yaml  
    apiVersion: helm.crossplane.io/v1beta1  
    kind: Release  
    metadata:  
      name: flux2  
    spec:  
      forProvider:  
        chart:  
          name: flux2  
          repository: https://fluxcd-community.github.io/helm-charts  
          version: "2.12.1"  
        namespace: fluxcd  
        skipCreateNamespace: true  
        values:  
          helmController:  
            create: true  
          imageAutomationController:  
            create: true  
          # ... all FluxCD controllers enabled  
      providerConfigRef:  
        name: helm-provider

## Prometheus Stack Deployment
    
    
    # prometheus-stack-helm-release.yaml  
    apiVersion: helm.crossplane.io/v1beta1  
    kind: Release  
    metadata:  
      name: kube-prometheus-stack  
    spec:  
      forProvider:  
        chart:  
          name: kube-prometheus-stack  
          repository: https://prometheus-community.github.io/helm-charts  
          version: "55.5.0"  
        namespace: observability  
        skipCreateNamespace: true  
        values:  
          prometheus:  
            prometheusSpec:  
              retention: 30d  
              storageSpec:  
                volumeClaimTemplate:  
                  spec:  
                    storageClassName: standard  
                    resources:  
                      requests:  
                        storage: 10Gi  
          grafana:  
            enabled: true  
            adminPassword: admin123  
            service:  
              type: LoadBalancer  
            persistence:  
              enabled: true  
              size: 5Gi  
          # ... comprehensive monitoring configuration  
      providerConfigRef:  
        name: helm-provider

## Major Challenge: RBAC Permissions

The biggest challenge we faced was RBAC permissions for the Helm provider.

## The Problem
    
    
    failed to install release: serviceaccounts "argocd-application-controller" is forbidden:   
    User "system:serviceaccount:crossplane-system:provider-helm-503c3591121b" cannot get resource "serviceaccounts"

## Root Cause Analysis

  1. Helm provider tried to create namespaces (permission denied)
  2. Helm provider lacked permissions to manage resources in target namespaces
  3. The default service account for the provider had insufficient cluster access

## The Solution: Enhanced RBAC
    
    
    # helm-provider-rbac.yaml  
    apiVersion: rbac.authorization.k8s.io/v1  
    kind: ClusterRole  
    metadata:  
      name: provider-helm-system  
    rules:  
    - apiGroups: ["*"]  
      resources: ["*"]  
      verbs: ["*"]  
    - nonResourceURLs: ["*"]  
      verbs: ["*"]  
    ---  
    apiVersion: rbac.authorization.k8s.io/v1  
    kind: ClusterRoleBinding  
    metadata:  
      name: provider-helm-system  
    roleRef:  
      apiGroup: rbac.authorization.k8s.io  
      kind: ClusterRole  
      name: provider-helm-system  
    subjects:  
    - kind: ServiceAccount  
      name: provider-helm-503c3591121b  # Provider's service account  
      namespace: crossplane-system

## Key Fixes Applied:

  1. Separate Namespace Creation: Handle namespace creation via Kubernetes provider
  2. Skip Namespace Creation in Helm: Added `skipCreateNamespace: true`
  3. Enhanced RBAC: Granted comprehensive permissions to Helm provider
  4. Service Account Discovery: Identified the correct service account name

## Step 5: Deployment Strategy

## Deployment Order

  1. Apply RBAC permissions (critical first step)
  2. Create namespaces using Kubernetes provider
  3. Deploy applications using Helm provider
  4. Verify deployments and troubleshoot

## Commands Executed
    
    
    # Create namespaces  
    kubectl apply -f specific-namespaces.yaml
    
    
    # Apply RBAC  
    kubectl apply -f helm-provider-rbac.yaml# Deploy applications  
    kubectl apply -f argocd-helm-release.yaml  
    kubectl apply -f fluxcd-helm-release.yaml  
    kubectl apply -f prometheus-stack-helm-release.yaml

## Step 6: Verification and Access

## Check Deployment Status
    
    
    kubectl get releases.helm.crossplane.io  
    kubectl get objects.kubernetes.crossplane.io

## Access Applications

ArgoCD:
    
    
    kubectl port-forward -n argocd svc/argocd-server 8080:80  
    # Access: http://localhost:8080  
    # Password: kubectl -n argocd get secret argocd-initial-admin-secret -o jsonpath="{.data.password}" | base64 -d

Grafana:
    
    
    kubectl port-forward -n observability svc/kube-prometheus-stack-grafana 3000:80  
    # Access: http://localhost:3000  
    # Credentials: admin/admin123

Prometheus:
    
    
    kubectl port-forward -n observability svc/kube-prometheus-stack-prometheus 9090:9090  
    # Access: http://localhost:9090

## Final Results

## Successfully Deployed:

  * ✅ ArgoCD: 6 pods running in `argocd` namespace
  * ✅ FluxCD: 6 controllers running in `fluxcd` namespace
  * ✅ Prometheus Stack: 6 pods running in `observability` namespace

## Key Benefits Achieved:

  1. Infrastructure as Code: All applications defined declaratively
  2. Version Control: Deployments are reproducible and trackable
  3. Centralized Management: Single Crossplane control plane
  4. Namespace Isolation: Clean separation of concerns
  5. Persistent Storage: Data retention for metrics and configurations

## Lessons Learned

## 1\. RBAC is Critical

  * Always configure proper RBAC permissions first
  * Service account names are provider-specific
  * Cluster-wide permissions may be necessary for comprehensive deployments

## 2\. Provider Configuration Matters

  * Different providers have different ProviderConfig CRDs
  * Understanding provider capabilities is crucial
  * Test provider health before deploying workloads

## 3\. Namespace Management Strategy

  * Separate namespace creation from application deployment
  * Use `skipCreateNamespace: true` in Helm releases
  * Consider namespace-scoped vs cluster-scoped resources

## 4\. Troubleshooting Approach

  * Check provider status first
  * Examine RBAC permissions
  * Review resource events and logs
  * Understand provider-specific behaviors

## Advanced Considerations

## Production Readiness Checklist

  * Implement proper secrets management
  * Configure backup strategies for persistent data
  * Set up monitoring and alerting for Crossplane itself
  * Implement GitOps workflows for configuration updates
  * Configure proper resource limits and quotas
  * Set up multi-cluster scenarios if needed

## Security Enhancements

  * Use least-privilege RBAC policies
  * Implement Pod Security Standards
  * Configure network policies
  * Enable audit logging
  * Regular security scanning

## Conclusion

We successfully deployed a complete GitOps and observability stack using Crossplane, overcoming several challenges along the way. The key to success was understanding the provider model, proper RBAC configuration, and systematic troubleshooting.

This approach provides a solid foundation for managing complex Kubernetes workloads as infrastructure as code, with the flexibility to extend and customize as needed.

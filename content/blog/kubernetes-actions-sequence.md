---
title: "Kubernetes Sequences of Actions: What Happens"
date: 2025-11-05
summary: "Full local version of my Medium post on Kubernetes Sequences of Actions."
tags: ["DevOps", "SRE", "Infrastructure"]
---

# Kubernetes Sequences of Actions: What Happens

6 min read

·

Oct 17, 2024

\--

Share

Ever wondered what goes on behind the scenes when you execute a simple `kubectl apply -f pod-spec.yaml` command? While it might seem straightforward, this command triggers a complex sequence of actions within your Kubernetes cluster to create, update, or manage the specified resource. In this blog post, we'll delve into each step of this process to demystify how Kubernetes orchestrates your applications seamlessly.

**TL;DR:** Running `kubectl apply -f pod-spec.yaml` initiates a series of orchestrated actions in the Kubernetes cluster, including parsing and validation, communication with the API server, authentication and authorization, admission control, persistence to etcd, scheduling, node management, and ongoing resource management.

## 1\. Parsing and Validation

## kubectl Command Execution

When you execute `kubectl apply -f pod-spec.yaml`, the `kubectl` CLI begins by parsing the YAML manifest file. This step ensures that the file is well-structured and that the resources defined within it (such as Pods, Deployments, Services, etc.) are valid according to Kubernetes specifications.

## Client-Side Validation

Before sending any requests to the Kubernetes API server, `kubectl` performs client-side validation. This involves checking the syntax of your YAML file. For instance, if there's a typo in the resource definition or a missing required field, `kubectl` will immediately flag an error, preventing malformed configurations from reaching the cluster.

_Example:_
    
    
     apiVersion: v1  
    kind: Pod  
    metadata:  
      name: my-pod  
    spec:  
      containers:  
        - name: my-container  
          image: nginx:latest  
          ports:  
            - containerPort: 80

If you accidentally mistype `containerPort` as `containerPOrt`, `kubectl` will catch this error during client-side validation.

## 2\. Communication with the API Server

## API Request Submission

After successful validation, `kubectl` constructs an API request containing the resource definition from your YAML file. This request is sent to the Kubernetes API server, which serves as the central hub for all cluster operations. Depending on the current state of the cluster and the resource specified, the request will either create a new resource, update an existing one, or modify it accordingly.

## Version Check

If the resource you’re trying to create already exists, the API server performs a version comparison between the manifest’s version and the current resource version in the cluster. This check determines whether an update is necessary. If the versions differ, the API server proceeds with the update; otherwise, it may skip redundant operations.

## 3\. Authentication and Authorization

## Authentication

Security is paramount in Kubernetes. Once the API server receives the request, it first verifies the identity of the user or service account making the request. This authentication step ensures that only legitimate entities can interact with the cluster.

## Authorization

After authentication, the API server checks whether the authenticated user has the necessary permissions to perform the requested action on the specified resource. Kubernetes employs Role-Based Access Control (RBAC) policies to manage these permissions, ensuring that users can only perform actions they’re authorized for.

_Example:  
_ If a user tries to create a Deployment but lacks the necessary RBAC permissions, the API server will reject the request, returning an authorization error.

## 4\. Admission Controllers

## Admission Control Process

Once authenticated and authorized, the request passes through a series of admission controllers. These are plugins that can modify or reject incoming requests based on predefined policies. Admission controllers play a crucial role in enforcing security policies, setting default values, managing resource quotas, and more.

## Common Admission Controllers:

  * **LimitRanger:** Ensures that resource limits (like CPU and memory) are within specified bounds.
  * **PodSecurityPolicy:** Verifies that pods comply with defined security constraints.
  * **MutatingAdmissionWebhook:** Can modify the request, such as injecting sidecar containers into pods.

_Example:  
_ If a pod specification doesn’t specify resource limits, the LimitRanger admission controller can automatically inject default CPU and memory limits to prevent resource exhaustion.

## 5\. Persistence to etcd

## Storing the Desired State

After successfully passing through admission controllers, the resource definition is persisted to `etcd`, Kubernetes' distributed key-value store. `etcd` holds the entire state of the cluster, storing the "desired state" as specified by your YAML files.

## Resource Definition Saved

By writing the resource definition to `etcd`, Kubernetes ensures that the desired state is durable and can be retrieved or restored if necessary. This persistence is vital for maintaining cluster consistency and enabling features like high availability and disaster recovery.

## 6\. Scheduler Involvement (For Pods)

## Pod Scheduling Process

If the resource you’re applying is a Pod (or involves Pods), the Kubernetes scheduler comes into play. The scheduler’s responsibility is to assign the Pod to the most suitable node in the cluster based on several factors:

  * **Resource Requirements:** CPU, memory, and storage needs.
  * **Affinity Rules:** Preferences for certain nodes or avoiding others.
  * **Taints and Tolerations:** Ensuring Pods are placed on nodes that can tolerate specific conditions.
  * **Node Availability:** Current load and resource availability on nodes.

## Node Selection

Once the scheduler identifies the best-fit node, it assigns the Pod to that node, updating the resource’s specification accordingly.

## 7\. Kubelet on the Target Node

## Kubelet’s Role

The Kubelet is an agent running on each node within the Kubernetes cluster. Once a Pod is assigned to a node, the Kubelet on that node takes over. It reads the Pod’s specification and ensures that the containers are running as intended.

## Interacting with the Container Runtime

The Kubelet communicates with the node’s container runtime (such as Docker or containerd) to perform actions like:

  * **Pulling Container Images:** Fetching the necessary images from registries.
  * **Starting Containers:** Launching the containers as defined in the Pod specification.
  * **Monitoring Health:** Continuously checking the status of containers and restarting them if necessary.

_Example:  
_ If your Pod specifies an Nginx container, the Kubelet will pull the Nginx image and start the container, ensuring it’s running and accessible as defined.

## 8\. Ongoing Management

## Controller Manager’s Responsibilities

Kubernetes employs various controllers within the Controller Manager to maintain the desired state of the cluster. These controllers continuously monitor the actual state against the desired state stored in `etcd`. If discrepancies arise-such as a Pod crashing or being deleted unexpectedly-the controllers take corrective actions, like recreating or rescheduling the affected resources.

## Health Checks

Kubernetes also performs health checks using readiness and liveness probes:

  * **Readiness Probes:** Determine if a container is ready to accept traffic.
  * **Liveness Probes:** Check if a container is still running and functioning correctly.

If a container fails its liveness probe, Kubernetes will automatically restart it to maintain application availability.

## Additional Kubernetes Components

To fully grasp the sequence of actions, it’s essential to understand a few more Kubernetes components:

  * **etcd:** A distributed key-value store that holds the entire state of the Kubernetes cluster. Every change to a resource is recorded here as the desired state.
  * **Admission Controllers:** Plugins that enforce policies and ensure resource quotas before persisting changes to `etcd`.
  * **API Server:** Acts as the frontend for all control plane operations in Kubernetes. All interactions with the cluster go through the API server, making it a critical component for cluster management.

## What’s Not Included in kubeadm or Cloud-Managed Kubernetes (AKS, EKS, GKE)?

Understanding the differences between setting up Kubernetes manually with `kubeadm` and using cloud-managed services like Amazon EKS, Google GKE, or Azure AKS is crucial for effective cluster management.

## kubeadm

When you set up Kubernetes using `kubeadm`, certain components are automatically configured:

  * **Automated Setup:** Components like the scheduler and controller manager are set up for you.
  * **Manual Management:** You are responsible for managing control plane nodes, worker nodes, and `etcd` clusters. This includes tasks like scaling, patching, and ensuring high availability.

## Cloud-Managed Kubernetes (EKS, GKE, AKS)

Cloud providers offer managed Kubernetes services that abstract away much of the operational overhead:

  * **Control Plane Management:** Services like EKS handle the management of the control plane components, including the API server, `etcd`, and the scheduler.
  * **Worker Node Management:** You focus primarily on managing worker nodes, with the cloud provider handling the complexities of the control plane.
  * **Integrated Services:** These managed services offer tight integration with other cloud services, such as monitoring (CloudWatch), identity management (IAM), and load balancing (ELB).

## Key Differences Between kubeadm and Cloud-Managed Kubernetes (AKS, EKS, GKE)

## Control Plane Management

  * **kubeadm:** You have full control and responsibility over the entire control plane, including the API server, `etcd`, and scheduler.
  * **Cloud-Managed (EKS, GKE, AKS):** The cloud provider manages the control plane, allowing you to focus on deploying and managing your applications and worker nodes.

## Integration

  * **kubeadm:** Requires manual integration with other systems for monitoring, logging, and security.
  * **Cloud-Managed:** Offers seamless integration with native cloud services, enhancing functionality and simplifying management.

_Example:  
_ Using EKS, you can easily integrate with AWS CloudWatch for monitoring and IAM for fine-grained access control, whereas with `kubeadm`, you'd need to set up these integrations manually.

## Conclusion

Understanding the intricate sequence of actions that Kubernetes performs when you run `kubectl apply -f pod-spec.yaml` provides valuable insights into the platform's robustness and flexibility. From parsing your YAML files to ensuring that your containers are running smoothly, Kubernetes orchestrates a myriad of processes to maintain the desired state of your applications. Whether you're managing your own cluster with `kubeadm` or leveraging the power of cloud-managed services like EKS, GKE, or AKS, appreciating these underlying mechanisms can help you optimize your workflows and troubleshoot issues more effectively.

Embracing this knowledge empowers you to harness the full potential of Kubernetes, ensuring that your applications are deployed, scaled, and managed with precision and reliability.

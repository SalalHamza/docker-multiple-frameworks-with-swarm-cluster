# Multi-Framework Cloud Deployment on AWS Docker Swarm Cluster

A production-grade, highly available containerized deployment featuring four modern web application stacks (**Go**, **Laravel**, **Next.js**, and **Node.js**) distributed across a 3-node **Docker Swarm** cluster hosted on **AWS EC2 (Ubuntu 26.04 LTS)**.

## 🏗️ Architecture Overview

The cluster consists of three AWS EC2 virtual machines inside a single Virtual Private Cloud (VPC), operating over an encrypted Swarm overlay network with an internal private container registry.

```text
                           Internet / Client Traffic
                                      │
                         ┌────────────▼───────────┐
                         │   AWS Security Group   │
                         │  (Inbound: 8081-8084)  │
                         └────────────┬───────────┘
                                      │
           Docker Swarm Ingress Routing Mesh (Overlay Network)
       ┌──────────────────────────────┼──────────────────────────────┐
       │                              │                              │
┌──────▼─────────────────┐ ┌──────────▼──────────────┐ ┌──────────▼──────────────┐
│   EC2: Master Node     │ │   EC2: Worker Node 1    │ │   EC2: Worker Node 2    │
│    (Swarm Manager)     │ │    (Swarm Worker 1)     │ │    (Swarm Worker 2)     │
├────────────────────────┤ ├─────────────────────────┤ ├─────────────────────────┤
│ • Swarm Leader         │ │ • Swarm Worker Node     │ │ • Swarm Worker Node     │
│ • Persistent Registry  │ │ • Service Tasks         │ │ • Service Tasks         │
│ • Stack Orchestrator   │ │ • App Replicas          │ │ • App Replicas          │
│ • App Replicas         │ │                         │ │                         │
└────────────────────────┘ └─────────────────────────┘ └─────────────────────────┘
```

### Cluster Topology (3x AWS EC2 VMs)

* **1x Master VM (`Manager / Leader`)**: Maintains cluster state via Raft consensus, manages stack orchestration, hosts the persistent private Docker Registry (`localhost:5000`), and runs scheduled containers.
* **2x Worker VMs (`Worker 1` & `Worker 2`)**: Receive and execute container workloads distributed by the Master node across the Swarm overlay network.

---

## 📦 Projects & Framework Specifications

| Service Name | Framework / Stack | Image | Internal Container Port | Published Host Port | Port Mapping | Replicas | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **`my_apps_app1`** | **Go (Golang)** | `localhost:5000/app1:latest` | `8080` | `8081` | `8081:8080` | 2 | Running |
| **`my_apps_app2`** | **Laravel (PHP)** | `localhost:5000/app2:latest` | `8000` | `8082` | `8082:8000` | 2 | Running |
| **`my_apps_app3`** | **Next.js** | `localhost:5000/app3:latest` | `3000` | `8083` | `8083:3000` | 2 | Running |
| **`my_apps_app4`** | **Node.js (Express)** | `localhost:5000/app4:latest` | `3000` | `8084` | `8084:3000` | 2 | Running |

---

## 🔒 AWS Network & Security Configuration

### Required AWS Security Group Rules

#### 1. Internal Swarm Cluster Communication (VPC / Node-to-Node)
* **TCP `2377`**: Cluster management and Raft sync communications.
* **TCP & UDP `7946`**: Node-to-node communication and container network discovery.
* **UDP `4789`**: Overlay network traffic (VXLAN data path).

#### 2. External Public Access
* **Custom TCP `8081 - 8084`**: Public HTTP access to the 4 web frameworks (`0.0.0.0/0`).
* **SSH TCP `22`**: Administrative terminal access.

---

## 🚀 Setup & Deployment Guide

### 1. Install Docker on All 3 EC2 VMs (Ubuntu 26.04 LTS)

Run on the **Master**, **Worker 1**, and **Worker 2** nodes:

```bash
sudo apt-get update -y && sudo apt-get upgrade -y
sudo apt-get install -y ca-certificates curl gnupg lsb-release

sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

source /etc/os-release
CODENAME="${VERSION_CODENAME}"
if ! curl -s -f -I "https://download.docker.com/linux/ubuntu/dists/${CODENAME}/Release" >/dev/null 2>&1; then
    CODENAME="noble"
fi

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${CODENAME} stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update -y
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
newgrp docker
```

### 2. Initialize Docker Swarm Cluster

On the **Master VM**:

```bash
docker swarm init --advertise-addr <MASTER_PRIVATE_IP>
```

On **Worker VM 1** and **Worker VM 2**, run the join command output by the Master:

```bash
docker swarm join --token <SWARM_WORKER_TOKEN> <MASTER_PRIVATE_IP>:2377
```

Verify all 3 nodes are connected from the **Master VM**:

```bash
docker node ls
```

### 3. Deploy Persistent Private Registry on Master

Set up a local Swarm registry backed by a host directory on the Master node so worker VMs can pull images across the cluster and survive instance reboots:

```bash
sudo mkdir -p /var/lib/docker-registry

docker service create --name registry \
  --publish published=5000,target=5000 \
  --mount type=bind,src=/var/lib/docker-registry,dst=/var/lib/registry \
  --constraint 'node.role == manager' \
  registry:2
```

Verify the registry is active:

```bash
curl http://localhost:5000/v2/_catalog
```

### 4. Clone Repository, Build & Push Framework Images

On the **Master VM**:

```bash
git clone https://github.com/SalalHamza/docker-multiple-frameworks.git
cd docker-multiple-frameworks
```

Build and push all 4 projects into the local Swarm registry:

```bash
# Project 1: Go Application (Internal Port: 8080 -> Host Port: 8081)
docker build -t localhost:5000/app1:latest ./go-app
docker push localhost:5000/app1:latest

# Project 2: Laravel Application (Internal Port: 8000 -> Host Port: 8082)
docker build -t localhost:5000/app2:latest ./laravel-app
docker push localhost:5000/app2:latest

# Project 3: Next.js Application (Internal Port: 3000 -> Host Port: 8083)
mkdir -p ./nextjs-app/public
docker build -t localhost:5000/app3:latest ./nextjs-app
docker push localhost:5000/app3:latest

# Project 4: Node.js Application (Internal Port: 3000 -> Host Port: 8084)
docker build -t localhost:5000/app4:latest ./nodejs-app
docker push localhost:5000/app4:latest
```

### 5. Docker Swarm Stack Configuration (`docker-compose.yml`)

```yaml
# docker-compose.yml
version: '3.8'

services:
  app1:
    image: localhost:5000/app1:latest
    ports:
      - "8081:8080"
    deploy:
      replicas: 2
      restart_policy:
        condition: on-failure

  app2:
    image: localhost:5000/app2:latest
    ports:
      - "8082:8000"
    deploy:
      replicas: 2

  app3:
    image: localhost:5000/app3:latest
    ports:
      - "8083:3000"
    deploy:
      replicas: 2

  app4:
    image: localhost:5000/app4:latest
    ports:
      - "8084:3000"
    deploy:
      replicas: 2

networks:
  default:
    driver: overlay
```

### 6. Deploy Stack Across the Cluster

Deploy the stack from the **Master VM**:

```bash
docker stack deploy -c docker-compose.yml my_apps
```

Check running services, replicas, and port mappings:

```bash
docker stack services my_apps
```

Verify container distribution across Master, Worker 1, and Worker 2:

```bash
docker stack ps my_apps
```

---

## 🌐 Live Application Access & Verification

### Local Health Check (from Master Terminal)

```bash
for port in 8081 8082 8083 8084; do
  echo -n "Port $port: "
  curl -s -o /dev/null -w "HTTP %{http_code}\n" http://localhost:$port/
done
```

### Public Browser Access

Thanks to Docker Swarm's ingress routing mesh, you can access any application using the public IPv4 address of the Master or either Worker VM:

* **Go App (`8081 -> 8080`)**: `http://<EC2_PUBLIC_IP>:8081`
* **Laravel App (`8082 -> 8000`)**: `http://<EC2_PUBLIC_IP>:8082`
* **Next.js App (`8083 -> 3000`)**: `http://<EC2_PUBLIC_IP>:8083`
* **Node.js App (`8084 -> 3000`)**: `http://<EC2_PUBLIC_IP>:8084`

---

## 🛠️ Cluster Operations & Maintenance

```bash
# View real-time logs for a specific service
docker service logs -f my_apps_app1

# Scale any framework up or down dynamically
docker service scale my_apps_app3=4

# Force rebalance and update services across worker nodes
docker service update --force my_apps_app1
docker service update --force my_apps_app2
docker service update --force my_apps_app3
docker service update --force my_apps_app4

# Remove the entire stack
docker stack rm my_apps
```
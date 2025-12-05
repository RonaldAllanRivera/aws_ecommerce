# Deployment Guide (AWS + SES + Docker)

This document is a brief guide for **future you** when deploying this project to AWS and switching the Email service from Mailhog to **SES**.

---

## 1. Overview

- **Compute**: Single EC2 instance running Docker and this repo via `docker-compose.yml`.
- **Services**: `catalog-app`, `checkout-app`, `email-app`, `mysql`, `redis`, `mailhog`, `nginx`.
- **Messaging**: SQS queue `order-events` for `OrderCreated` messages.
- **Email**:
  - Local: Mailhog (SMTP on `mailhog:1025`).
  - AWS: SES (SMTP or native `ses` mailer).

The Email service is already wired with:

- `EmailLog` model + `email_logs` table.
- `OrderConfirmationMail` Mailable + `emails.order-confirmation` Blade view.
- `ProcessOrderCreated` job targeting the `order-events` queue.

You only need to point the mailer and queue config at AWS.

---

## 2. Local vs AWS Email configuration

### 2.1 Local (Docker + Mailhog)

Email service `.env` (already configured):

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="orders@example.test"
MAIL_FROM_NAME="AWS E-commerce"
```

- Mail is delivered to the **Mailhog UI** at `http://localhost:8025`.
- This is safe for local development and tests.

### 2.2 For AWS / SES later (IMPORTANT NOTE FOR FUTURE YOU)

When you move the Email service to **SES**, you will override mail settings via **environment variables or SSM Parameter Store**.

**Minimum SES-related env configuration:**

```dotenv
MAIL_MAILER=ses
MAIL_FROM_ADDRESS="verified-sender@your-domain.com"
MAIL_FROM_NAME="AWS E-commerce"
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
```

Notes:

- `MAIL_FROM_ADDRESS` **must** be a **verified identity** in SES (verified email address or domain).
- In **SES sandbox**, you also need to **verify the recipient addresses** you test with.
- Prefer to store these values in **SSM Parameter Store** (or Secrets Manager) and load them into the container at runtime, rather than baking them into `.env` in the repo.

Example SSM parameter names (you can change these):

- `/aws-ecommerce/email/MAIL_FROM_ADDRESS`
- `/aws-ecommerce/email/MAIL_FROM_NAME`
- `/aws-ecommerce/email/AWS_DEFAULT_REGION`
- `/aws-ecommerce/email/AWS_ACCESS_KEY_ID` (or use instance role instead of keys)
- `/aws-ecommerce/email/AWS_SECRET_ACCESS_KEY` (if not using instance role)

On the EC2 instance, you can export them into the environment before running `docker compose up` or mount an `.env.production` generated from SSM.

---

## 3. Queue configuration (Email service)

### 3.1 Local Docker

- `QUEUE_CONNECTION=redis` is set via `docker-compose.yml` for `email-app`.
- `ProcessOrderCreated` uses `onQueue('order-events')` so it will listen on the **`order-events` queue name** within Redis.

For local testing, you can run a worker inside the `email-app` container:

```bash
docker compose exec email-app php artisan queue:work --queue=order-events
```

(Checkout currently uses the `sync` queue driver in local Docker; switching Checkout to Redis/SQS can be done later.)

### 3.2 AWS / SQS

Later, in production-like AWS:

- Set `QUEUE_CONNECTION=sqs` for the Email service.
- Provide `SQS_QUEUE` / `AWS` credentials via env/SSM.
- Ensure the queue name/URL matches the one Checkout publishes `OrderCreated` to (`order-events`).

---

## 4. High-level AWS deployment steps

Very short version (details belong in `infra/cloudformation` templates and ops docs):

1. **Provision infrastructure** via CloudFormation:
   - VPC / subnet / security group.
   - EC2 instance with IAM role (access to SQS, SES, SSM).
   - In this repo, these are implemented for the local/test environment by `infra/cloudformation/networking.yml` (VPC, subnet, security group) and `infra/cloudformation/compute.yml` (single Free Tier-eligible EC2 Docker host).
2. **On EC2**, install Docker + Docker Compose, clone this repo.
3. **Fetch configuration** from SSM (DB creds, SES/MAIL_*, SQS URLs) into environment.
4. Run:

   ```bash
   sudo docker-compose -f docker-compose.yml -f docker-compose.aws.yml up -d --build
   ```

5. Configure SES identities and test sending:
   - Verify sender and recipient emails.
   - Watch CloudWatch logs and `email_logs` table for delivery status.

### 4.1 EC2 SSH commands (cheat sheet)

From your workstation (replace the key path and host with your own values):

```bash
ssh -i /path/to/aws-ecommerce-key.pem ec2-user@your-ec2-public-dns
```

On EC2, first deployment:

```bash
cd ~
git clone https://github.com/RonaldAllanRivera/aws_ecommerce.git
cd aws_ecommerce
sudo docker-compose -f docker-compose.yml -f docker-compose.aws.yml up -d --build
```

On EC2, updating to the latest code:

```bash
cd ~/aws_ecommerce
git pull origin main
sudo docker-compose -f docker-compose.yml -f docker-compose.aws.yml up -d --build
```

---

## 5. Frontend (Vue SPA) deployment on EC2

The Vue SPA is built with Vite and served by the same Nginx instance that fronts the Laravel services.

1. On your workstation or CI, build the frontend:

   ```bash
   cd frontend
   npm install
   npm run build
   ```

2. Copy the built assets (`frontend/dist`) into the Nginx container image or mount them into a directory that Nginx serves as the web root (for example `/var/www/frontend`).

3. Configure Nginx on EC2 so that:
   - `/` and SPA routes serve the built `index.html` from the Vue build.
   - API routes proxy to the Laravel services (for example `/catalog/*`, `/checkout/*`).
   - The local `docker-compose.yml` and `docker/nginx/nginx.conf` already follow this pattern by mounting `frontend/dist` into `/var/www/frontend` and defining a `localhost` server block that serves the SPA while proxying `/catalog/` and `/checkout/` requests to the respective Laravel containers.

4. Set the frontend environment for production builds so Axios points at the EC2 host (same origin as Nginx):

   ```dotenv
   VITE_API_BASE_URL=https://your-ec2-host-or-domain
   VITE_CHECKOUT_API_BASE_URL=https://your-ec2-host-or-domain
   ```

   In this topology, CORS can be locked down to that single origin, and the Email service still uses SES/SQS as described above.

Keep this file as the single place to remind you how to flip from **Mailhog** to **SES + SQS** safely and how to host the SPA alongside the Laravel services.

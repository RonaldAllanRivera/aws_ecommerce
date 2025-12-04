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
2. **On EC2**, install Docker + Docker Compose, clone this repo.
3. **Fetch configuration** from SSM (DB creds, SES/MAIL_*, SQS URLs) into environment.
4. Run:

   ```bash
   docker compose up -d --build
   ```

5. Configure SES identities and test sending:
   - Verify sender and recipient emails.
   - Watch CloudWatch logs and `email_logs` table for delivery status.

Keep this file as the single place to remind you how to flip from **Mailhog** to **SES + SQS** safely.

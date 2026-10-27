# Marketing Email Lab

A PHP command-line lab that prepares marketing campaigns in MySQL, publishes
them to Amazon SQS, and sends the emails through a Mailtrap SMTP sandbox.

## How it works

```
seed-customers.php  ->  customers table
prepare-campaign.php -> marketing_outbox (50 PENDING requests)
enqueue.php         ->  SQS queue (outbox rows become QUEUED)
worker.php          ->  SQS -> Mailtrap SMTP -> sent_marketing_emails
```

## Requirements

- PHP 8.3+ with the `pdo_mysql` extension
- Composer
- MySQL or MariaDB
- An AWS account with an SQS queue, and the AWS CLI
- A Mailtrap sandbox inbox

## Setup

1. Install dependencies:

   ```bash
   composer install
   ```

2. Create the database and a dedicated user (replace the placeholders):

   ```sql
   CREATE DATABASE marketing_lab CHARACTER SET utf8mb4;
   CREATE USER '<db_user>'@'localhost' IDENTIFIED BY '<strong_password>';
   GRANT ALL ON marketing_lab.* TO '<db_user>'@'localhost';
   ```

3. Create a local `env.sh` (it is git-ignored, never commit it) and fill in
   your own values:

   ```bash
   export DB_HOST="127.0.0.1"
   export DB_PORT="3306"
   export DB_NAME="<db_name>"
   export DB_USER="<db_user>"
   export DB_PASSWORD="<db_password>"

   export AWS_PROFILE="<aws_cli_profile>"
   export AWS_REGION="<queue_region>"
   export SQS_QUEUE_URL="<your_queue_url>"

   export MAILTRAP_HOST="<mailtrap_host>"
   export MAILTRAP_PORT="<mailtrap_port>"
   export MAILTRAP_USERNAME="<mailtrap_username>"
   export MAILTRAP_PASSWORD="<mailtrap_password>"
   export MAIL_FROM_ADDRESS="marketing@example.com"
   export MAILTRAP_DELAY_SECONDS="11"
   ```

   Load it in every new terminal before running any script:

   ```bash
   source env.sh
   ```

4. Configure AWS credentials with `aws configure --profile <aws_cli_profile>`.
   Credentials are stored in `~/.aws/`, never in this project.

## AWS permissions

The IAM user needs only these actions on the queue (and its dead-letter queue,
if any). Fill in the placeholders in [iam-policy.json](iam-policy.json):

- `sqs:SendMessage`
- `sqs:ReceiveMessage`
- `sqs:DeleteMessage`
- `sqs:GetQueueAttributes`
- `sqs:GetQueueUrl`

Grant the least privilege required and scope the policy to the specific queue
ARNs.

## Usage

Run the steps in order:

```bash
php init-db.php                      # create tables from schema.sql
php seed-customers.php               # seed 10,000 customers (8,000 opted in)
php prepare-campaign.php CAMP-001 PROMO15   # select 50 opted-in customers
php enqueue.php                      # publish PENDING requests to SQS
php worker.php                       # consume queue and send emails (Ctrl+C to stop)
php inspect.php                      # summary of customers, outbox and history
```

`prepare-campaign.php` takes an optional campaign ID and promo code
(defaults: `CAMP-001`, `PROMO15`).

## Project structure

| Path | Purpose |
| --- | --- |
| `src/Database.php` | PDO connection (validated settings, exceptions, real prepared statements) |
| `src/Settings.php` | Reads configuration from environment variables |
| `src/CustomerRepository.php`, `OutboxRepository.php`, `EmailHistory.php`, `DatabaseSummaryRepository.php` | All SQL lives here; every value is bound as a parameter |
| `src/CampaignPreparer.php`, `CustomerSeeder.php`, `QueuePublisher.php`, `MarketingProcessor.php`, `QueueWorker.php` | Application logic, free of SQL |
| `src/EmailSender.php` | Sends mail with PHPMailer |
| `src/ActivityLogger.php` | Writes JSON events to `logs/events.jsonl` |
| `schema.sql` | Table definitions (InnoDB, utf8mb4) |

## Security notes

- Configuration comes from environment variables only. No credentials are
  hardcoded in the source.
- `env.sh`, `.env`, `vendor/` and `logs/*.jsonl` are git-ignored. Do not use
  `git add -f` on them.
- All SQL is in repository classes and uses bound parameters.
- If a secret is ever committed or shared, rotate it immediately.

## Troubleshooting

| Error | Fix |
| --- | --- |
| `could not find driver` | Install the PDO MySQL driver, for example `sudo apt install php-mysql` |
| `Required environment variable ... is not set` | Run `source env.sh` in the same terminal |
| `AccessDenied` from SQS | Attach the IAM policy to your user and check the region and queue URL |

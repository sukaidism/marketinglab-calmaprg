<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use RuntimeException;

final class QueuePublisher
{
    public function __construct(
        private OutboxRepository $outbox,
        private SqsClient $sqs,
        private string $queueUrl,
        private ActivityLogger $logger
    ) {}

    public function publishPending(): void
    {
        $rows = $this->outbox->findPending();

        if (!$rows) {
            echo "No PENDING requests found in marketing_outbox.\n";
            return;
        }

        $publishedCount = 0;
        foreach ($rows as $row) {
            $payload = [
                'requestId'  => $row['request_id'],
                'campaignId' => $row['campaign_id'],
                'customerId' => $row['customer_id'],
                'promoCode'  => $row['promo_code'],
                'subject'    => $row['subject'],
                'body'       => $row['body'],
            ];

            try {
                // TODO Q1: Call the injected SQS client's sendMessage() method.
                $result = $this->sqs->sendMessage([
                    'QueueUrl'    => $this->queueUrl,
                    'MessageBody' => json_encode($payload, JSON_THROW_ON_ERROR),
                ]);

                $sqsMessageId = (string) $result->get('MessageId');
                $queuedAt = gmdate('Y-m-d H:i:s');

                if (!$this->outbox->markQueued($queuedAt, $sqsMessageId, $row['request_id'])) {
                    throw new RuntimeException(
                        "Outbox request {$row['request_id']} is no longer pending after SQS accepted it."
                    );
                }

                // TODO M1: Record a queued log event with request, campaign, customer, and SQS identifiers.
                $this->logger->record('queued', [
                    'requestId'    => $row['request_id'],
                    'campaignId'   => $row['campaign_id'],
                    'customerId'   => $row['customer_id'],
                    'sqsMessageId' => $sqsMessageId,
                ]);

                $publishedCount++;
            } catch (\Throwable $e) {
                $this->logger->record('enqueue_failed', [
                    'requestId'  => $row['request_id'],
                    'campaignId' => $row['campaign_id'],
                    'customerId' => $row['customer_id'],
                    'error'      => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        echo "Successfully published {$publishedCount} requests to SQS.\n";
    }
}

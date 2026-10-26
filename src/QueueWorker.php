<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;

final class QueueWorker
{
    public function __construct(
        private SqsClient $sqs,
        private string $queueUrl,
        private MarketingProcessor $processor,
        private ActivityLogger $logger
    ) {}

    public function run(): void
    {
        echo "Worker started. Polling SQS queue for messages (Press Ctrl+C to stop)...\n";
        while (true) {
            $result = $this->sqs->receiveMessage([
                'QueueUrl'            => $this->queueUrl,
                'MaxNumberOfMessages' => 1,
                'WaitTimeSeconds'     => 20,
                'AttributeNames'      => ['ApproximateReceiveCount'],
            ]);

            $messages = $result->get('Messages') ?? [];
            foreach ($messages as $message) {
                $this->handle($message);
            }
        }
    }

    public function handle(array $message): void
    {
        $sqsMessageId = $message['MessageId'] ?? null;
        $phase = 'process';
        $job = null;

        try {
            $decoded = json_decode((string) ($message['Body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                throw new \InvalidArgumentException('Message body must decode to a JSON object.');
            }
            $job = $decoded;

            $this->logger->record('received', [
                'requestId'    => $job['requestId'] ?? null,
                'campaignId'   => $job['campaignId'] ?? null,
                'customerId'   => $job['customerId'] ?? null,
                'sqsMessageId' => $sqsMessageId,
            ]);

            // TODO W1: Invoke the injected processor with this job.
            $this->processor->process($job);

            // TODO M3: Change the operation phase to 'delete' before acknowledgment.
            $phase = 'delete';

            // TODO W2: Delete the SQS message using the queue URL and ReceiptHandle.
            $this->sqs->deleteMessage([
                'QueueUrl'      => $this->queueUrl,
                'ReceiptHandle' => $message['ReceiptHandle'],
            ]);

            // TODO M3: Log 'deleted' only after DeleteMessage succeeds.
            $this->logger->record('deleted', [
                'requestId'    => $job['requestId'] ?? null,
                'campaignId'   => $job['campaignId'] ?? null,
                'customerId'   => $job['customerId'] ?? null,
                'sqsMessageId' => $sqsMessageId,
            ]);

            echo sprintf(
                "[OK] Processed campaign=%s customer=%s (SQS MessageId=%s)\n",
                $job['campaignId'],
                $job['customerId'],
                $sqsMessageId
            );
        } catch (\Throwable $e) {
            $eventName = ($phase === 'delete') ? 'ack_failed' : 'processing_failed';
            $this->logger->record($eventName, [
                'requestId'    => is_array($job) ? ($job['requestId'] ?? null) : null,
                'campaignId'   => is_array($job) ? ($job['campaignId'] ?? null) : null,
                'customerId'   => is_array($job) ? ($job['customerId'] ?? null) : null,
                'sqsMessageId' => $sqsMessageId,
                'phase'        => $phase,
                'error'        => $e->getMessage(),
            ]);

            fwrite(
                STDERR,
                sprintf("[ERROR] %s (phase=%s, sqsMessageId=%s): %s\n", $eventName, $phase, $sqsMessageId, $e->getMessage())
            );
        }
    }
}

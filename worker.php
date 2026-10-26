<?php
declare(strict_types=1);
require __DIR__ . '/config.php';

$db = (new Lab\Database())->connect();
$logger = new Lab\ActivityLogger();
$history = new Lab\EmailHistory($db);
$sender = new Lab\EmailSender(Lab\Settings::smtp());
$processor = new Lab\MarketingProcessor(
    new Lab\CustomerRepository($db),
    $history,
    $sender,
    $logger,
    Lab\Settings::sendDelaySeconds()
);
$worker = new Lab\QueueWorker(
    Lab\Settings::queue(),
    Lab\Settings::required('SQS_QUEUE_URL'),
    $processor,
    $logger
);
$worker->run();

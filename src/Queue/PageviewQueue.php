<?php

namespace LukaLtaApi\Queue;

use LukaLtaApi\Value\WebTracking\Tracking\AbstractTrackingPayload;
use LukaLtaApi\Value\WebTracking\Tracking\TrackingBatch;

class PageviewQueue
{
    private array $queue = [];
    private int $interval = 10; // seconds
    private bool $processing = false;

    public function __construct()
    {
    }

    public function add(array $pageview): void
    {
        $this->queue[] = $pageview;

        // Auto-process if queue is full
        if (count($this->queue) === 1) {
            $this->processQueue();
        } else {
            // Save queue to file for persistence
            $this->saveQueue();
        }
    }

    public function processQueue(): void
    {
        if ($this->processing || empty($this->queue)) {
            return;
        }

        $this->processing = true;

        $batch = TrackingBatch::from($this->queue);

        /** @var AbstractTrackingPayload $pageview */
        foreach ($batch as $pageview) {
            var_dump($pageview);
        }
    }
}
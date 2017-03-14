<?php

namespace Worker;

use Service\AMQPManager;

class DatabaseReprocessorWorker extends LinkProcessorWorker
{
    protected $queue = AMQPManager::REFETCHING;

    protected function process($links, $userId)
    {
        return $this->processorService->reprocess($links);
    }

}
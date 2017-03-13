<?php

namespace Service;

use PhpAmqpLib\Message\AMQPMessage;

class AMQPQueueManager
{
    public function buildRoutingKey($queue, $trigger)
    {
        return sprintf('brain.%s.%s', $queue, $trigger);
    }

    public function buildPattern($queue)
    {
        return sprintf('brain.%s.*', $queue);
    }

    public function buildQueueName($queue)
    {
        return ucfirst($queue);
    }

    public function getTrigger(AMQPMessage $message)
    {
        $routingKey = $message->delivery_info['routing_key'];
        $parts = explode('.',$routingKey);

        return $parts[2];
    }
}
<?php

namespace Service;


use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class AMQPManager
{
    const MATCHING = 'matching';
    const FETCHING = 'fetching';
    const PREDICTION = 'prediction';
    const SOCIAL_NETWORK = 'social_network';
    const CHANNEL = 'channel';

    protected $connection;

    /**
     * @var AMQPChannel[]
     */
    protected $publishingChannels = array();

    function __construct(AMQPStreamConnection $AMQPStreamConnection)
    {
        $this->connection = $AMQPStreamConnection;
    }

    public function enqueueFetching($messageData)
    {
        $this->enqueueMessage($messageData, 'brain.fetching.links');
    }

    public function enqueueMessage($messageData, $routingKey)
    {
        $message = new AMQPMessage(json_encode($messageData, JSON_UNESCAPED_UNICODE));

        $exchangeName = 'brain.topic';
        $exchangeType = 'topic';
        list ($topic, $queueName) = $this->buildData($routingKey);

        $channel = $this->getChannel($queueName);

        $channel->exchange_declare($exchangeName, $exchangeType, false, true, false);
        $channel->queue_declare($queueName, false, true, false, false);
        $channel->queue_bind($queueName, $exchangeName, $topic);
        $channel->basic_publish($message, $exchangeName, $routingKey);
    }

    private function getChannel($queueName)
    {
        if (isset($this->publishingChannels[$queueName])){
            $channel = $this->publishingChannels[$queueName];
        } else {
            $channel = $this->connection->channel();
            $this->publishingChannels[$queueName] = $channel;
        }

        return $channel;
    }

    private function buildData($routingKey)
    {
        $parts = explode('.', $routingKey);

        switch ($parts[1]){
            case $this::FETCHING:
                $topic = 'brain.fetching.*';
                $queueName = 'brain.fetching';
                break;
            case $this::MATCHING:
                $topic = 'brain.matching.*';
                $queueName = 'brain.matching';
                break;
            case $this::PREDICTION:
                $topic = 'brain.prediction.*';
                $queueName = 'brain.prediction';
                break;
            case $this::SOCIAL_NETWORK:
                $topic = 'brain.social_network.*';
                $queueName = 'brain.social_network';
                break;
            case $this::CHANNEL:
                $topic = 'brain.channel.*';
                $queueName = 'brain.channel';
                break;
            default:
                throw new \Exception('RabbitMQ queue not supported');
        }

        return array($topic, $queueName);
    }


}
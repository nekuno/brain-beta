<?php

namespace Worker;

use ApiConsumer\Fetcher\FetcherService;
use ApiConsumer\Fetcher\ProcessorService;
use Model\Neo4j\Neo4jException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\EventDispatcher\EventDispatcher;

class LinkProcessorWorker extends LoggerAwareWorker implements RabbitMQConsumerInterface
{

    /**
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * @var FetcherService
     */
    protected $fetcherService;

    protected $processorService;

    public function __construct(AMQPChannel $channel, EventDispatcher $dispatcher, FetcherService $fetcherService, ProcessorService $processorService)
    {
        $this->channel = $channel;
        $this->dispatcher = $dispatcher;
        $this->fetcherService = $fetcherService;
        $this->processorService = $processorService;
    }

    /**
     * { @inheritdoc }
     */
    public function consume()
    {
        $exchangeName = 'brain.topic';
        $exchangeType = 'topic';
        $topic = 'brain.fetching.*';
        $queueName = 'brain.fetching';

        $this->channel->exchange_declare($exchangeName, $exchangeType, false, true, false);
        $this->channel->queue_declare($queueName, false, true, false, false);
        $this->channel->queue_bind($queueName, $exchangeName, $topic);
        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($queueName, '', false, false, false, false, array($this, 'callback'));

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    /**
     * { @inheritdoc }
     */
    public function callback(AMQPMessage $message)
    {
        $data = json_decode($message->body, true);
        $resourceOwner = $data['resourceOwner'];
        $userId = $data['userId'];
        $exclude = array_key_exists('exclude', $data) ? $data['exclude'] : array();
        $public = isset($data['public']) ? $data['public'] : false;

        try {
            $links = $public ? $this->fetcherService->fetchAsClient($userId, $resourceOwner, $exclude) : $this->fetcherService->fetchUser($userId, $resourceOwner, $exclude);
            $this->processorService->process($links, $userId);

        } catch (\Exception $e) {
            $this->logger->error(sprintf('Worker: Error fetching for user %d with message %s on file %s, line %d', $userId, $e->getMessage(), $e->getFile(), $e->getLine()));
            if ($e instanceof Neo4jException) {
                $this->logger->error(sprintf('Query: %s' . "\n" . 'Data: %s', $e->getQuery(), print_r($e->getData(), true)));
            }
            $this->dispatchError($e, 'Fetching');
        }

        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

        $this->memory();
    }
}

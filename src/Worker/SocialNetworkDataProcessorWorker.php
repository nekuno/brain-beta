<?php

namespace Worker;

use Model\Neo4j\Neo4jException;
use PhpAmqpLib\Channel\AMQPChannel;
use Service\AMQPManager;
use Service\EventDispatcher;
use Service\SocialNetwork;


class SocialNetworkDataProcessorWorker extends LoggerAwareWorker implements RabbitMQConsumerInterface
{
    protected $queue = AMQPManager::SOCIAL_NETWORK;
    /**
     * @var SocialNetwork
     */
    protected $sn;


    public function __construct(AMQPChannel $channel, EventDispatcher $dispatcher, SocialNetwork $sn)
    {
        parent::__construct($dispatcher, $channel);
        $this->sn = $sn;
    }

    /**
     * { @inheritdoc }
     */
    public function callback(array $data, $trigger)
    {
        $userId = $data['id'];
        $socialNetworks = $data['socialNetworks'];
        try {
            switch ($trigger) {
                case 'added':
                    $this->sn->setSocialNetworksInfo($userId, $socialNetworks, $this->logger);
                    break;
                default;
                    throw new \Exception('Invalid social network trigger');
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Worker: Error fetching for user %d with message %s on file %s, line %d', $userId, $e->getMessage(), $e->getFile(), $e->getLine()));
            if ($e instanceof Neo4jException) {
                $this->logger->error(sprintf('Query: %s' . "\n" . 'Data: %s', $e->getQuery(), print_r($e->getData(), true)));
            }
            $this->dispatchError($e, 'Social network trigger');
        }
    }
}

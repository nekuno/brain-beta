<?php

namespace Worker;

use ApiConsumer\Fetcher\FetcherService;
use ApiConsumer\Fetcher\ProcessorService;
use Doctrine\DBAL\Connection;
use ApiConsumer\Factory\ResourceOwnerFactory;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;
use Model\Neo4j\Neo4jException;
use Model\User\SocialNetwork\SocialProfileManager;
use Model\User\Token\TokensModel;
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

    /**
     * @var Connection
     */
    protected $connectionBrain;

    /**
     * @var ResourceOwnerFactory
     */
    protected $resourceOwnerFactory;

    public function __construct(
        AMQPChannel $channel,
        EventDispatcher $dispatcher,
        FetcherService $fetcherService,
        ProcessorService $processorService,
        ResourceOwnerFactory $resourceOwnerFactory,
        Connection $connectionBrain
    ) {
        $this->channel = $channel;
        $this->dispatcher = $dispatcher;
        $this->fetcherService = $fetcherService;
        $this->processorService = $processorService;
        $this->resourceOwnerFactory = $resourceOwnerFactory;
        $this->connectionBrain = $connectionBrain;
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

        // Verify mysql connections are alive
        if ($this->connectionBrain->ping() === false) {
            $this->connectionBrain->close();
            $this->connectionBrain->connect();
        }

        $data = json_decode($message->body, true);
        $resourceOwner = $data['resourceOwner'];
        $userId = $data['userId'];
        $exclude = array_key_exists('exclude', $data) ? $data['exclude'] : array();
        $public = isset($data['public']) ? $data['public'] : false;

        try {
            $links = $public ? $this->fetcherService->fetchAsClient($userId, $resourceOwner, $exclude) : $this->fetcherService->fetchUser($userId, $resourceOwner, $exclude);
            $this->processorService->process($links, $userId);

//              $this->enqueueChannels($userId, $resourceOwner);

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

    protected function enqueueChannels($userId, $resourceOwner)
    {
        if ($resourceOwner === TokensModel::TWITTER) {

            /** @var TwitterResourceOwner $twitterResourceOwner */
            $twitterResourceOwner = $this->resourceOwnerFactory->build($resourceOwner);
            try {
                $twitterResourceOwner->dispatchChannel(
                    array(
                        'userId' => $userId,
                    )
                );
            } catch (\Exception $e) {
                $this->dispatchError($e, 'Error adding twitter channel');
                $this->logger->error('Error adding twitter channel: ' . $e->getMessage());
            }

            $this->logger->info(sprintf('Enqueued fetching old tweets for userId %d', $userId));
        };

    }

}

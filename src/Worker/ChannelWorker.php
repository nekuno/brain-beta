<?php

namespace Worker;

use ApiConsumer\Fetcher\FetcherService;
use ApiConsumer\Fetcher\GetOldTweets\GetOldTweets;
use ApiConsumer\Fetcher\ProcessorService;
use Doctrine\DBAL\Connection;
use Model\Neo4j\Neo4jException;
use Model\User\Token\TokensModel;
use PhpAmqpLib\Channel\AMQPChannel;
use Psr\Log\LoggerInterface;
use Service\AMQPManager;
use Service\EventDispatcher;

class ChannelWorker extends LoggerAwareWorker implements RabbitMQConsumerInterface
{
    protected $queue = AMQPManager::CHANNEL;
    /**
     * @var FetcherService
     */
    protected $fetcherService;

    /**
     * @var ProcessorService
     */
    protected $processorService;

    /**
     * @var Connection
     */
    protected $connectionBrain;

    /**
     * @var GetOldTweets
     */
    protected $getOldTweets;

    public function __construct(
        AMQPChannel $channel,
        EventDispatcher $dispatcher,
        FetcherService $fetcherService,
        ProcessorService $processorService,
        GetOldTweets $getOldTweets,
        Connection $connectionBrain
    ) {
        parent::__construct($dispatcher, $channel);
        $this->fetcherService = $fetcherService;
        $this->processorService = $processorService;
        $this->getOldTweets = $getOldTweets;
        $this->connectionBrain = $connectionBrain;
    }

    public function setLogger(LoggerInterface $logger)
    {
        parent::setLogger($logger);
        $this->fetcherService->setLogger($logger);
        $this->processorService->setLogger($logger);
    }

    /**
     * { @inheritdoc }
     */
    public function callback(array $data, $trigger)
    {
        if ($this->connectionBrain->ping() === false) {
            $this->connectionBrain->close();
            $this->connectionBrain->connect();
        }

        try {

            if (!isset($data['resourceOwner'])) {
                throw new \Exception('Enqueued message does not include resourceOwner parameter');
            }
            $resourceOwner = $data['resourceOwner'];

            switch ($resourceOwner) {
                case TokensModel::TWITTER:

                    $userId = $this->getUserId($data);
                    $links = $this->fetchChannelTwitter($data);
                    $this->processorService->process($links, $userId);

                    break;
                default:
                    throw new \Exception('Resource %s not supported in this queue', $resourceOwner);
            }

        } catch (\Exception $e) {
            $this->logger->error(sprintf('Worker: Error fetching for channel with message %s on file %s, line %d', $e->getMessage(), $e->getFile(), $e->getLine()));
            if ($e instanceof Neo4jException) {
                $this->logger->error(sprintf('Query: %s' . "\n" . 'Data: %s', $e->getQuery(), print_r($e->getData(), true)));
            }
            $this->dispatchError($e, 'Channel fetching');
        }
    }

    private function getUserId($data)
    {
        if (!isset($data['userId'])) {
            throw new \Exception('Enqueued message does not include userId parameter');
        }

        return $data['userId'];
    }

    private function fetchChannelTwitter(array $data)
    {
        $userId = $this->getUserId($data);
        $this->logger->info(sprintf('Fetching from user %d', $userId));

        $links = $this->fetchTwitterAPI($userId);

//        $links = $this->fetchFromGOT($data);

        return $links;
    }

    private function fetchTwitterAPI($userId)
    {
        $resourceOwner = TokensModel::TWITTER;

        $exclude = array('twitter_following', 'twitter_favorites');

        return $this->fetcherService->fetchAsClient($userId, $resourceOwner, $exclude);
    }

    private function fetchFromGOT(array $data)
    {
        if (!isset($data['username'])) {
            throw new \Exception('Enqueued message does not include  username parameter');
        }
        $username = $data['username'];
        $this->logger->info(sprintf('Using GetOldTweets to fetch from %s', $username));

        $links = $this->getOldTweets->fetchFromUser($username);
        $this->logger->info(sprintf('Total %d links fetched from tweets from %s', count($links), $username));

        return $links;
    }

}

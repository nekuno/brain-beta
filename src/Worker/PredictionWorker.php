<?php


namespace Worker;

use Model\Link\LinkManager;
use Model\Neo4j\Neo4jException;
use Model\Affinity\AffinityManager;
use Model\Similarity\SimilarityModel;
use PhpAmqpLib\Channel\AMQPChannel;
use Service\AffinityRecalculations;
use Service\AMQPManager;
use Service\EventDispatcher;

class PredictionWorker extends LoggerAwareWorker implements RabbitMQConsumerInterface
{
    const TRIGGER_RECALCULATE = 'recalculate';
    const TRIGGER_LIVE = 'live';

    protected $queue = AMQPManager::PREDICTION;

    /**
     * @var AffinityRecalculations
     */
    protected $affinityRecalculations;

    /**
     * @var AffinityManager
     */
    protected $affinityModel;

    /**
     * @var LinkManager
     */
    protected $linkModel;

    /**
     * @var SimilarityModel
     */
    protected $similarityModel;

    public function __construct(AMQPChannel $channel,
                                EventDispatcher $dispatcher,
                                AffinityRecalculations $affinityRecalculations,
                                AffinityManager $affinityModel,
                                LinkManager $linkModel)
    {
        parent::__construct($dispatcher, $channel);
        $this->linkModel = $linkModel;
        $this->affinityModel = $affinityModel;
        $this->affinityRecalculations = $affinityRecalculations;
    }

    /**
     * { @inheritdoc }
     */
    public function callback(array $data, $trigger)
    {
        $userId = $data['userId'];

        switch ($trigger) {
            case $this::TRIGGER_RECALCULATE:
                try {
                    $this->affinityRecalculations->recalculateAffinities($userId, 100, 20);
                } catch (\Exception $e) {
                    $this->logger->error(sprintf('Worker: Error recalculating affinity for user %d with message %s on file %s, line %d', $userId, $e->getMessage(), $e->getFile(), $e->getLine()));
                    if ($e instanceof Neo4jException) {
                        $this->logger->error(sprintf('Query: %s' . "\n" . 'Data: %s', $e->getQuery(), print_r($e->getData(), true)));
                    }
                    $this->dispatchError($e, 'Affinity recalculating with trigger recalculate');
                }
                break;
            case $this::TRIGGER_LIVE:
                try {
                    $links = $this->linkModel->getLivePredictedContent($userId);
                    foreach ($links as $link) {
                        $affinity = $this->affinityModel->getAffinity($userId, $link->getContent()['id']);
                        $this->logger->info(sprintf('Affinity between user %s and link %s: %s', $userId, $link->getContent()['id'], $affinity['affinity']));
                    }
                } catch (\Exception $e) {
                    $this->logger->error(sprintf('Worker: Error calculating live affinity for user %d with message %s on file %s, line %d', $userId, $e->getMessage(), $e->getFile(), $e->getLine()));
                    if ($e instanceof Neo4jException) {
                        $this->logger->error(sprintf('Query: %s' . "\n" . 'Data: %s', $e->getQuery(), print_r($e->getData(), true)));
                    }
                    $this->dispatchError($e, 'Affinity recalculating with live trigger');
                }

                break;
            default;
                throw new \Exception('Invalid affinity calculation trigger: ' . $trigger);
        }
    }

}

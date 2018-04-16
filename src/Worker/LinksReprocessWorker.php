<?php

namespace Worker;

use ApiConsumer\Fetcher\ProcessorService;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use Event\ReprocessEvent;
use Model\Link\Link;
use Model\Link\LinkManager;
use PhpAmqpLib\Channel\AMQPChannel;
use Service\AMQPManager;
use Service\EventDispatcher;

class LinksReprocessWorker extends LoggerAwareWorker implements RabbitMQConsumerInterface
{
    protected $queue = AMQPManager::LINKS_REPROCESS;

    /**
     * @var LinkManager
     */
    protected $linkModel;

    /**
     * @var ProcessorService
     */
    protected $processorService;

    public function __construct(AMQPChannel $channel, EventDispatcher $dispatcher, LinkManager $linkModel, ProcessorService $processorService)
    {
        parent::__construct($dispatcher, $channel);
        $this->linkModel = $linkModel;
        $this->processorService = $processorService;
    }

    /**
     * { @inheritdoc }
     */
    public function callback(array $data, $trigger)
    {
        $link = Link::buildFromArray($data['link']);
        $url = $link->getUrl();
        $reprocessEvent = new ReprocessEvent($url);
        $this->dispatcher->dispatch(\AppEvents::REPROCESS_START, $reprocessEvent);

        try {
            $preprocessedLink = new PreprocessedLink($url);
            $preprocessedLink->setFirstLink($link);
            $links = $this->processorService->reprocess(array($preprocessedLink));

            $reprocessEvent->setLinks($links);
            $this->dispatcher->dispatch(\AppEvents::REPROCESS_FINISH, $reprocessEvent);

        } catch (\Exception $e) {
            $reprocessEvent->setError(sprintf('Error reprocessing link url "%s" with message "%s"', $url, $e->getMessage()));
            $this->dispatcher->dispatch(\AppEvents::REPROCESS_ERROR, $reprocessEvent);
            $this->dispatchError($e, 'Reprocessing Links');

            return false;
        }

        return true;
    }
}

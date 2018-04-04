<?php

namespace Worker;

use ApiConsumer\LinkProcessor\LinkProcessor;
use Event\CheckEvent;
use Model\Link\Link;
use Model\Link\LinkManager;
use PhpAmqpLib\Channel\AMQPChannel;
use Service\AMQPManager;
use Service\EventDispatcher;

class LinksCheckWorker extends LoggerAwareWorker implements RabbitMQConsumerInterface
{
    protected $queue = AMQPManager::LINKS_CHECK;

    protected $linkProcessor;

    /**
     * @var LinkManager
     */
    protected $linkModel;

    public function __construct(AMQPChannel $channel, EventDispatcher $dispatcher, LinkManager $linkModel, LinkProcessor $linkProcessor)
    {
        parent::__construct($dispatcher, $channel);
        $this->linkProcessor = $linkProcessor;
        $this->linkModel = $linkModel;
    }

    /**
     * { @inheritdoc }
     */
    public function callback(array $data, $trigger)
    {
        $link = Link::buildFromArray($data['link']);
        $url = $link->getUrl();

        $checkEvent = new CheckEvent($url);
        $this->dispatcher->dispatch(\AppEvents::CHECK_START, $checkEvent);

        if (!$this->linkProcessor->isLinkWorking($url)) {
            $this->linkModel->setProcessed($url, false);
            $checkEvent->setError(sprintf('Bad response status code for url "%s"', $url));
            $this->dispatcher->dispatch(\AppEvents::CHECK_ERROR, $checkEvent);

            return;
        }

        $thumbnail = $link->getThumbnailLarge();
        if (!$this->linkProcessor->isLinkWorking($thumbnail)) {
            $this->linkModel->setProcessed($url, false);
            $checkEvent->setError(sprintf('Bad response status code for thumbnail "%s" for url "%s"', $thumbnail, $url));
            $this->dispatcher->dispatch(\AppEvents::CHECK_ERROR, $checkEvent);

            return;
        }

        $this->dispatcher->dispatch(\AppEvents::CHECK_SUCCESS, $checkEvent);
    }
}

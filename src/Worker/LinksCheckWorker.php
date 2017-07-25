<?php

namespace Worker;

use Event\CheckEvent;
use GuzzleHttp\Client;
use Model\Link\Link;
use Model\Link\LinkModel;
use PhpAmqpLib\Channel\AMQPChannel;
use Service\AMQPManager;
use Service\EventDispatcher;

class LinksCheckWorker extends LoggerAwareWorker implements RabbitMQConsumerInterface
{
    protected $queue = AMQPManager::LINKS_CHECK;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var LinkModel
     */
    protected $linkModel;

    public function __construct(AMQPChannel $channel, EventDispatcher $dispatcher, LinkModel $linkModel)
    {
        parent::__construct($dispatcher, $channel);
        $this->client = new Client();
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
        $this->client->setDefaultOption('verify', false);

        try {
            $response = $this->client->get($url);
            $checkEvent->setResponse($response->getStatusCode());
            $this->dispatcher->dispatch(\AppEvents::CHECK_RESPONSE, $checkEvent);

            if ($response->getStatusCode() >= 400) {
                $this->linkModel->setProcessed($url, false);
                $checkEvent->setError(sprintf('Response status code "%s" for url "%s"', $response->getStatusCode(), $url));
                $this->dispatcher->dispatch(\AppEvents::CHECK_ERROR, $checkEvent);

                return false;
            }
            $thumbnail = $link->getThumbnail();
            if (!$thumbnail) {
                $this->linkModel->setProcessed($url, false);
                $checkEvent->setError(sprintf('No thumbnail for url "%s"', $url));
                $this->dispatcher->dispatch(\AppEvents::CHECK_ERROR, $checkEvent);

                return false;
            }
            $response = $this->client->get($thumbnail);
            if ($response->getStatusCode() >= 400) {
                $this->linkModel->setProcessed($url, false);
                $checkEvent->setError(sprintf('Response status code "%s" for thumbnail "%s"', $response->getStatusCode(), $thumbnail));
                $this->dispatcher->dispatch(\AppEvents::CHECK_ERROR, $checkEvent);

                return false;
            }

        } catch (\Exception $e) {
            $this->linkModel->setProcessed($url, false);
            $checkEvent->setError(sprintf('Error getting link url "%s" with message "%s"', $url, $e->getMessage()));
            $this->dispatcher->dispatch(\AppEvents::CHECK_ERROR, $checkEvent);
            $this->dispatchError($e, 'Checking Links');

            return false;
        }

        $this->dispatcher->dispatch(\AppEvents::CHECK_SUCCESS, $checkEvent);
        return true;
    }
}

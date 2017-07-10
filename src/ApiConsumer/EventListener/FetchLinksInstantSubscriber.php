<?php


namespace ApiConsumer\EventListener;

use Event\FetchEvent;
use Event\ProcessLinkEvent;
use Event\ProcessLinksEvent;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Service\DeviceService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FetchLinksInstantSubscriber implements EventSubscriberInterface
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var DeviceService
     */
    protected $deviceService;

    /**
     * @var integer
     */
    protected $current;

    /**
     * @var integer
     */
    protected $links;

    public function __construct(ClientInterface $client, DeviceService $deviceService)
    {
        $this->client = $client;
        $this->deviceService = $deviceService;
    }

    public static function getSubscribedEvents()
    {

        return array(
            \AppEvents::FETCH_START => array('onFetchStart'),
            \AppEvents::FETCH_FINISH => array('onFetchFinish'),
            \AppEvents::PROCESS_START => array('onProcessStart'),
            \AppEvents::PROCESS_LINK => array('onProcessLink'),
            \AppEvents::PROCESS_FINISH => array('onProcessFinish'),
        );
    }

    public function onFetchStart(FetchEvent $event)
    {
        $json = array('userId' => $event->getUser(), 'resource' => $event->getResourceOwner());
        try {
            $this->client->post('api/fetch/start', array('json' => $json));
        } catch (RequestException $e) {

        }
    }

    public function onFetchFinish(FetchEvent $event)
    {
        $json = array('userId' => $event->getUser(), 'resource' => $event->getResourceOwner());
        try {
            $this->client->post('api/fetch/finish', array('json' => $json));
        } catch (RequestException $e) {

        }
    }

    public function onProcessStart(ProcessLinksEvent $event)
    {
        $this->current = 0;
        $this->links = count($event->getLinks());
        $json = array('userId' => $event->getUser(), 'resource' => $event->getResourceOwner());
        try {
            $this->client->post('api/process/start', array('json' => $json));
        } catch (RequestException $e) {

        }
    }

    public function onProcessLink(ProcessLinkEvent $event)
    {
        $percentage = floor($this->current++ / $this->links * 100);
        $json = array('userId' => $event->getUser(), 'resource' => $event->getResourceOwner(), 'percentage' => $percentage);
        try {
            $this->client->post('api/process/link', array('json' => $json));
        } catch (RequestException $e) {

        }
    }

    public function onProcessFinish(ProcessLinksEvent $event)
    {
        $jsonProcess = array('userId' => $event->getUser(), 'resource' => $event->getResourceOwner());
        $pushData = array(
            'resource' => $event->getResourceOwner(),
        );
        try {
            $this->client->post('api/process/finish', array('json' => $jsonProcess));
            $this->deviceService->pushMessage($pushData, $event->getUser(), DeviceService::PROCESS_FINISH_CATEGORY);
        } catch (RequestException $e) {

        }
    }
}
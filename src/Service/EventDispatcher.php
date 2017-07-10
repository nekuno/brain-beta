<?php

namespace Service;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher as BaseDispatcher;
use Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventDispatcher
{
    protected $dispatcher;

    /**
     * EventDispatcher constructor.
     * @param $dispatcher
     */
    public function __construct(BaseDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function dispatch($eventName, Event $event = null)
    {
        return $this->dispatcher->dispatch($eventName, $event);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber)
    {
        $this->dispatcher->addSubscriber($subscriber);
    }

    public function dispatchError(\Exception $e, $process)
    {
        return $this->dispatcher->dispatch(\AppEvents::EXCEPTION_ERROR, new ExceptionEvent($e, $process));
    }

    public function dispatchWarning(\Exception $e, $process)
    {
        return $this->dispatcher->dispatch(\AppEvents::EXCEPTION_WARNING, new ExceptionEvent($e, $process));
    }

    public function dispatchUrlUnprocessed(\Exception $e, $process)
    {
        return $this->dispatcher->dispatch(\AppEvents::URL_UNPROCESSED, new ExceptionEvent($e, $process));
    }
}
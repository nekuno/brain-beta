<?php

namespace Worker;

use Console\ApplicationAwareCommand;
use Event\ExceptionEvent;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Service\EventDispatcher;

abstract class LoggerAwareWorker implements LoggerAwareInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    public function __construct(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     * @return null
     */
    public function setLogger(LoggerInterface $logger)
    {

        $this->logger = $logger;
    }

    protected function memory()
    {
        $this->logger->notice(sprintf('Current memory usage: %s', ApplicationAwareCommand::formatBytes(memory_get_usage(true))));
        $this->logger->notice(sprintf('Peak memory usage: %s', ApplicationAwareCommand::formatBytes(memory_get_peak_usage(true))));
    }

    protected function getTrigger(AMQPMessage $message)
    {
        $routingKey = $message->delivery_info['routing_key'];
        $parts = explode('.',$routingKey);

        return $parts[2];
    }

    //TODO: Move to dispatcher to make it available everywhere
    protected function dispatchError(\Exception $e, $message)
    {
        $this->dispatcher->dispatch(\AppEvents::EXCEPTION_ERROR, new ExceptionEvent($e, $message));
    }

    protected function dispatchWarning(\Exception $e, $message)
    {
        $this->dispatcher->dispatch(\AppEvents::EXCEPTION_WARNING, new ExceptionEvent($e, $message));
    }
}
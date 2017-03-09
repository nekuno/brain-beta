<?php

namespace Worker;

use Console\ApplicationAwareCommand;
use Event\ExceptionEvent;
use PhpAmqpLib\Channel\AMQPChannel;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Service\AMQPQueueManager;
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

    /**
     * @var AMQPChannel
     */
    protected $channel;

    protected $queue;

    protected $queueManager;

    public function __construct(EventDispatcher $dispatcher, AMQPChannel $channel)
    {
        $this->dispatcher = $dispatcher;
        $this->channel = $channel;
        $this->queueManager = new AMQPQueueManager();
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function consume()
    {
        $exchangeName = 'brain.topic';
        $exchangeType = 'topic';
        $topic = $this->queueManager->buildPattern($this->queue);
        $queueName = $this->queueManager->buildQueueName($this->queue);

        $this->channel->exchange_declare($exchangeName, $exchangeType, false, true, false);
        $this->channel->queue_declare($queueName, false, true, false, false);
        $this->channel->queue_bind($queueName, $exchangeName, $topic);
        $this->channel->basic_qos(null, 1, null);
        $this->channel->basic_consume($queueName, '', false, false, false, false, array($this, 'callback'));

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }

    protected function getTopic()
    {
        return $this->queueManager->buildPattern($this->queue);
    }

    protected function memory()
    {
        $this->logger->notice(sprintf('Current memory usage: %s', ApplicationAwareCommand::formatBytes(memory_get_usage(true))));
        $this->logger->notice(sprintf('Peak memory usage: %s', ApplicationAwareCommand::formatBytes(memory_get_peak_usage(true))));
    }

    //TODO: Move to dispatcher to make it available everywhere. Differentiate from dispatcher->dispatchError (sets neo4j source)
    protected function dispatchError(\Exception $e, $message)
    {
        $this->dispatcher->dispatch(\AppEvents::EXCEPTION_ERROR, new ExceptionEvent($e, $message));
    }

    protected function dispatchWarning(\Exception $e, $message)
    {
        $this->dispatcher->dispatch(\AppEvents::EXCEPTION_WARNING, new ExceptionEvent($e, $message));
    }
}
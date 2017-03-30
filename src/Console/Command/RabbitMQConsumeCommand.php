<?php

namespace Console\Command;

use ApiConsumer\EventListener\FetchLinksInstantSubscriber;
use ApiConsumer\EventListener\FetchLinksSubscriber;
use Console\ApplicationAwareCommand;
use EventListener\ExceptionLoggerSubscriber;
use EventListener\SimilarityMatchingProcessSubscriber;
use EventListener\UserStatusSubscriber;
use PhpAmqpLib\Channel\AMQPChannel;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Service\AMQPManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Worker\ChannelWorker;
use Worker\DatabaseReprocessorWorker;
use Worker\LinkProcessorWorker;
use Worker\LoggerAwareWorker;
use Worker\MatchingCalculatorWorker;
use Worker\PredictionWorker;
use Worker\SocialNetworkDataProcessorWorker;


class RabbitMQConsumeCommand extends ApplicationAwareCommand
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $validConsumers = array(
        AMQPManager::FETCHING,
        AMQPManager::REFETCHING,
        AMQPManager::MATCHING,
        AMQPManager::PREDICTION,
        AMQPManager::SOCIAL_NETWORK,
        AMQPManager::CHANNEL,
    );

    protected function configure()
    {
        $this->setName('rabbitmq:consume')
            ->setDescription(sprintf('Starts a RabbitMQ consumer by name ("%s")', implode('", "', $this->validConsumers)))
            ->addArgument('consumer', InputArgument::OPTIONAL, 'Consumer to start up', 'fetching');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $consumer = $input->getArgument('consumer');

        if (!in_array($consumer, $this->validConsumers)) {
            throw new \Exception(sprintf('Invalid "%s" consumer name, valid consumers "%s".', $consumer, implode('", "', $this->validConsumers)));
        }

        $this->setLogger($output);

        $output->writeln(sprintf('Starting %s consumer', $consumer));

        $channel = $this->app['amqpManager.service']->getChannel($consumer);

        switch ($consumer) {

            case AMQPManager::FETCHING :
                $worker = $this->buildFetching($output, $channel);

                break;

            case AMQPManager::REFETCHING:
                $worker = $this->buildRefetching($output, $channel);

                break;

            case AMQPManager::MATCHING:
                $worker = $this->buildMatching($channel);
                break;

            case AMQPManager::PREDICTION:
                $worker = $this->buildPrediction($channel);
                break;

            case AMQPManager::SOCIAL_NETWORK:

                $worker = $this->buildSocialNetwork($channel);
                break;

            case AMQPManager::CHANNEL:

                $worker = $this->buildChannel($output, $channel);
                break;
            default:
                throw new \Exception('Invalid consumer name');
        }

        $worker->consume();
        $channel->close();
    }

    protected function setLogger(OutputInterface $output)
    {
        /* @var $logger LoggerInterface */
        $this->logger = $this->app['monolog'];

        if (OutputInterface::VERBOSITY_NORMAL < $output->getVerbosity()) {
            $this->logger = new ConsoleLogger($output, array(LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL));
        }
    }

    /**
     * @param EventSubscriberInterface[] $subscribers
     * @return mixed|\Service\EventDispatcher
     */
    protected function getDispatcher(array $subscribers)
    {
        $dispatcher = $this->app['dispatcher.service'];

        foreach ($subscribers as $subscribe)
        {
            $dispatcher->addSubscriber($subscribe);
        }

        return $dispatcher;
    }

    protected function buildFetching(OutputInterface $output, AMQPChannel $channel)
    {
        $subscribers = array(
            new ExceptionLoggerSubscriber($this->app['monolog']),
            new FetchLinksInstantSubscriber($this->app['guzzle.client'], $this->app['instant.host']),
            new FetchLinksSubscriber($output));
        $dispatcher = $this->getDispatcher($subscribers);

        $worker = new LinkProcessorWorker(
            $channel,
            $dispatcher,
            $this->app['api_consumer.fetcher'],
            $this->app['api_consumer.processor']);
        $worker->setLogger($this->logger);
        $this->noticeStart($worker);

        return $worker;
    }

    protected function buildRefetching(OutputInterface $output, AMQPChannel $channel)
    {
        $subscribers = array(
            new ExceptionLoggerSubscriber($this->app['monolog']),
            new FetchLinksInstantSubscriber($this->app['guzzle.client'], $this->app['instant.host']),
            new FetchLinksSubscriber($output));
        $dispatcher = $this->getDispatcher($subscribers);

        $worker = new DatabaseReprocessorWorker(
            $channel,
            $dispatcher,
            $this->app['api_consumer.fetcher'],
            $this->app['api_consumer.processor']);
        $worker->setLogger($this->logger);
        $this->noticeStart($worker);

        return $worker;
    }

    /**
     * @param AMQPChannel $channel
     * @return MatchingCalculatorWorker
     * @internal param OutputInterface $output
     */
    protected function buildMatching(AMQPChannel $channel)
    {
        $subscribers = array(
            new ExceptionLoggerSubscriber($this->app['monolog']),
            new UserStatusSubscriber($this->app['instant.client']),
            new SimilarityMatchingProcessSubscriber($this->app['instant.client']),
        );
        $dispatcher = $this->getDispatcher($subscribers);

        $worker = new MatchingCalculatorWorker(
            $channel,
            $this->app['users.manager'],
            $this->app['users.matching.model'],
            $this->app['users.similarity.model'],
            $this->app['questionnaire.questions.model'],
            $this->app['affinityRecalculations.service'],
            $this->app['dbs']['mysql_brain'],
            $dispatcher
        );
        $worker->setLogger($this->logger);
        $this->noticeStart($worker);

        return $worker;
    }

    /**
     * @param $channel
     * @return PredictionWorker
     */
    protected function buildPrediction(AMQPChannel $channel)
    {
        $subscribers = array(
            new ExceptionLoggerSubscriber($this->app['monolog']),
        );
        $dispatcher = $this->getDispatcher($subscribers);
        $worker = new PredictionWorker(
            $channel,
            $dispatcher,
            $this->app['affinityRecalculations.service'],
            $this->app['users.affinity.model'],
            $this->app['links.model']
        );
        $worker->setLogger($this->logger);
        $this->noticeStart($worker);

        return $worker;
    }

    /**
     * @param $channel
     * @return SocialNetworkDataProcessorWorker
     */
    protected function buildSocialNetwork(AMQPChannel $channel)
    {
        $subscribers = array(
            new ExceptionLoggerSubscriber($this->app['monolog']),
        );
        $dispatcher = $this->getDispatcher($subscribers);
        $worker = new SocialNetworkDataProcessorWorker($channel, $dispatcher, $this->app['socialNetwork.service']);
        $worker->setLogger($this->logger);
        $this->noticeStart($worker);

        return $worker;
    }

    /**
     * @param OutputInterface $output
     * @param $channel
     * @return ChannelWorker
     */
    protected function buildChannel(OutputInterface $output, AMQPChannel $channel)
    {
        $subscribers = array(
            new ExceptionLoggerSubscriber($this->app['monolog']),
            new FetchLinksInstantSubscriber($this->app['guzzle.client'], $this->app['instant.host']),
            new FetchLinksSubscriber($output)
        );
        $dispatcher = $this->getDispatcher($subscribers);

        $worker = new ChannelWorker($channel, $dispatcher, $this->app['api_consumer.fetcher'], $this->app['api_consumer.processor'], $this->app['dbs']['mysql_brain']);
        $worker->setLogger($this->logger);
        $this->noticeStart($worker);

        return $worker;
    }

    protected function noticeStart(LoggerAwareWorker $worker)
    {
        $message = 'Processing %s queue';
        $this->logger->notice(sprintf($message, $worker->getQueue()));
    }
}

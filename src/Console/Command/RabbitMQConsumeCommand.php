<?php

namespace Console\Command;

use ApiConsumer\EventListener\FetchLinksInstantSubscriber;
use ApiConsumer\EventListener\FetchLinksSubscriber;
use ApiConsumer\Fetcher\FetcherService;
use ApiConsumer\Fetcher\ProcessorService;
use Console\ApplicationAwareCommand;
use EventListener\ExceptionLoggerSubscriber;
use EventListener\SimilarityMatchingProcessSubscriber;
use EventListener\UserStatusSubscriber;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Service\AMQPManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Worker\ChannelWorker;
use Worker\LinkProcessorWorker;
use Worker\MatchingCalculatorWorker;
use Worker\PredictionWorker;
use Worker\SocialNetworkDataProcessorWorker;


class RabbitMQConsumeCommand extends ApplicationAwareCommand
{

    protected $validConsumers = array(
        AMQPManager::FETCHING,
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

        /* @var $logger LoggerInterface */
        $logger = $this->app['monolog'];

        if (OutputInterface::VERBOSITY_NORMAL < $output->getVerbosity()) {
            $logger = new ConsoleLogger($output, array(LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL));
        }

        $output->writeln(sprintf('Starting %s consumer', $consumer));

        $channel = $this->app['amqpManager.service']->getChannel($consumer);

        $dispatcher = $this->app['dispatcher.service'];

        $dispatcher->addSubscriber(new ExceptionLoggerSubscriber($this->app['monolog']));

        switch ($consumer) {

            case AMQPManager::FETCHING :
                $fetchLinksInstantSubscriber = new FetchLinksInstantSubscriber($this->app['guzzle.client'], $this->app['instant.host']);
                $fetchLinksSubscriber = new FetchLinksSubscriber($output);
                $dispatcher->addSubscriber($fetchLinksSubscriber);
                $dispatcher->addSubscriber($fetchLinksInstantSubscriber);
                /* @var $fetcher FetcherService */
                $fetcher = $this->app['api_consumer.fetcher'];
                $fetcher->setLogger($logger);
                /* @var $processorService ProcessorService */
                $processorService = $this->app['api_consumer.processor'];
                $processorService->setLogger($logger);

                $worker = new LinkProcessorWorker(
                    $channel,
                    $dispatcher,
                    $fetcher,
                    $processorService);
                $worker->setLogger($logger);
                $logger->notice('Processing fetching queue');
                break;

            case AMQPManager::MATCHING:
                $userStatusSubscriber = new UserStatusSubscriber($this->app['instant.client']);
                $similarityMatchingProcessSubscriber = new SimilarityMatchingProcessSubscriber($this->app['instant.client']);
                $dispatcher->addSubscriber($userStatusSubscriber);
                $dispatcher->addSubscriber($similarityMatchingProcessSubscriber);

                $worker = new MatchingCalculatorWorker(
                    $channel,
                    $this->app['users.manager'],
                    $this->app['users.matching.model'],
                    $this->app['users.similarity.model'],
                    $this->app['questionnaire.questions.model'],
                    $this->app['affinityRecalculations.service'],
                    $this->app['dbs']['mysql_brain'],
                    $dispatcher);
                $worker->setLogger($logger);
                $logger->notice('Processing matching queue');
                break;

            case AMQPManager::PREDICTION:

                $worker = new PredictionWorker(
                    $channel,
                    $dispatcher,
                    $this->app['affinityRecalculations.service'],
                    $this->app['users.affinity.model'],
                    $this->app['links.model']);
                $worker->setLogger($logger);
                $logger->notice('Processing prediction queue');
                break;

            case AMQPManager::SOCIAL_NETWORK:

                $worker = new SocialNetworkDataProcessorWorker($channel, $dispatcher, $this->app['socialNetwork.service']);
                $worker->setLogger($logger);
                $logger->notice('Processing social network queue');
                break;

            case AMQPManager::CHANNEL:

                $fetchLinksInstantSubscriber = new FetchLinksInstantSubscriber($this->app['guzzle.client'], $this->app['instant.host']);
                $fetchLinksSubscriber = new FetchLinksSubscriber($output);
                $dispatcher->addSubscriber($fetchLinksSubscriber);
                $dispatcher->addSubscriber($fetchLinksInstantSubscriber);
                /* @var $fetcher FetcherService */
                $fetcher = $this->app['api_consumer.fetcher'];
                $fetcher->setLogger($logger);
                /* @var $processorService ProcessorService */
                $processorService = $this->app['api_consumer.processor'];
                $processorService->setLogger($logger);

                $worker = new ChannelWorker($channel, $dispatcher, $fetcher, $processorService, $this->app['get_old_tweets'], $this->app['dbs']['mysql_brain']);
                $worker->setLogger($logger);
                $logger->notice('Processing channel queue');
                break;
            default:
                throw new \Exception('Invalid consumer name');
        }

        $worker->consume();
        $channel->close();
    }
}

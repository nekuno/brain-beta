<?php

namespace Console\Command;

use ApiConsumer\Fetcher\FetcherService;
use ApiConsumer\Fetcher\ProcessorService;
use Console\ApplicationAwareCommand;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Exception\MissingOptionsException;

class LinksFetchAndPreProcessCommand extends ApplicationAwareCommand
{
    protected function configure()
    {
        $this->setName('links:fetch-and-preprocess')
            ->setDescription('Preprocess urls')
            ->setDefinition(
                array(
                    new InputOption(
                        'resource',
                        null,
                        InputOption::VALUE_OPTIONAL,
                        'The resource owner which should preprocess links'
                    ),
                    new InputOption(
                        'userId',
                        null,
                        InputOption::VALUE_OPTIONAL,
                        'ID of the user to preprocess links from'
                    ),
                )
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $resource = $input->getOption('resource');
        $userId = $input->getOption('userId');

        if (null === $resource && null === $userId) {
            throw new MissingOptionsException ("You must provide the user or the resource to fetch links from", array("resource", "user"));
        }

        if (null !== $resource) {
            $resourceOwners = $this->app['api_consumer.config']['resource_owner'];
            $availableResourceOwners = implode(', ', array_keys($resourceOwners));

            if (!isset($resourceOwners[$resource])) {
                $output->writeln(sprintf('Resource owner %s not found, available resource owners: %s.', $resource, $availableResourceOwners));

                return;
            }
        }

        /* @var FetcherService $fetcherService */
        $fetcherService = $this->app['api_consumer.fetcher'];
        /* @var ProcessorService $processorService */
        $processorService = $this->app['api_consumer.processor'];

        $logger = new ConsoleLogger($output, array(LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL));
        $fetcherService->setLogger($logger);
        $processorService->setLogger($logger);

        try {
            $links = $fetcherService->fetchUser($userId, $resource);
            $processorService->preProcess($links);

        } catch (\Exception $e) {
            $output->writeln(
                sprintf(
                    'Error fetching links for user %d with message: %s',
                    $userId,
                    $e->getMessage()
                )
            );
        }

        $output->writeln('Success!');
    }
}

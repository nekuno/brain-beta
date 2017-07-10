<?php

namespace Console\Command;

use ApiConsumer\Fetcher\ProcessorService;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use Console\ApplicationAwareCommand;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class LinksPreProcessCommand extends ApplicationAwareCommand
{
    protected function configure()
    {
        $this->setName('links:preprocess')
            ->setDescription('Preprocess urls')
            ->addArgument(
                'urls',
                InputArgument::IS_ARRAY,
                'Urls to preprocess (separate multiple urls with a space)?'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $urls = $input->getArgument('urls');

        $output->writeln('Preprocessing urls.');


        foreach ($urls as $url) {
            try {
                $preprocessedLink = new PreprocessedLink($url);
                $preprocessedLink->setSource('nekuno');

                /* @var ProcessorService $processor */
                $processor = $this->app['api_consumer.processor'];
                $processor->setLogger(new ConsoleLogger($output, array(LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL)));
                /* @var $preprocessedLinks PreprocessedLink[] */
                $preProcessedLinks = $processor->preProcess(array($preprocessedLink));

                foreach ($preProcessedLinks as $preProcessedLink) {
                    $output->writeln($preProcessedLink->getUrl());
                }

            } catch (\Exception $e) {
                $output->writeln(sprintf('Error: %s', $e->getMessage()));
                $output->writeln(sprintf('Error: Link %s not pre processed', $url));
                continue;
            }
        }
    }
}

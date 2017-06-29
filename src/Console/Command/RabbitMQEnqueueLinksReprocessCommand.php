<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Service\Links\EnqueueLinksService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RabbitMQEnqueueLinksReprocessCommand extends ApplicationAwareCommand
{
    protected function configure()
    {
        $this->setName('rabbitmq:enqueue:links-reprocess')
            ->setDescription('Reprocess links with processed to 0.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EnqueueLinksService $enqueueLinksService */
        $enqueueLinksService = $this->app['enqueueLinks.service'];
        $enqueueLinksService->enqueueLinksReprocess($output);
        $output->writeln('Done!');
    }
}

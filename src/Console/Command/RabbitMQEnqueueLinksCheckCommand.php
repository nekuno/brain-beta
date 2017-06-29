<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Service\Links\EnqueueLinksService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RabbitMQEnqueueLinksCheckCommand extends ApplicationAwareCommand
{
    protected function configure()
    {
        $this->setName('rabbitmq:enqueue:links-check')
            ->setDescription('Check links and set processed to 0 for those with errors.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EnqueueLinksService $enqueueLinksService */
        $enqueueLinksService = $this->app['enqueueLinks.service'];
        $enqueueLinksService->enqueueLinksCheck($output);
        $output->writeln('Done!');
    }
}

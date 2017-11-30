<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Service\Links\MigrateLinksService;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LinksMigrationCommand extends ApplicationAwareCommand
{
    protected function configure()
    {
        $this->setName('links:migration')
            ->setDescription('Migrate links to LinkNetwork and Web labels');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var MigrateLinksService $migrateLinksService */
        $migrateLinksService = $this->app['migrateLinks.service'];

        $migrateLinksService->migrateLinks($output);

        $output->writeln('Done.');
    }
}

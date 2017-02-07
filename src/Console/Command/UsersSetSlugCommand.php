<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Manager\UserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UsersSetSlugCommand extends ApplicationAwareCommand
{

    protected function configure()
    {
        $this->setName('users:set-slugs')
            ->setDescription('Set slugs for all users if do not exist.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /* @var $manager UserManager */
        $manager = $this->app['users.manager'];

        $usersCount = $manager->setSlugs($output);
        $output->writeln($usersCount . ' users with slug');
        $output->writeln('Done.');
    }
}

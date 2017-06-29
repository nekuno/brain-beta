<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Service\AMQPManager;
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
        /* @var $amqpManager AMQPManager */
        $amqpManager = $this->app['amqpManager.service'];
        $linksModel = $this->app['links.model'];
        $links = $linksModel->getLinks(array('link.processed = 1'), 0 , 100000000);

        $output->writeln(count($links) . ' links');

        foreach ($links as $link) {
            $output->writeln('Link ' . $link['url'] . ' will be checked');
            $output->writeln(sprintf('Enqueuing url check for "%s"', $link['url']));
            $amqpManager->enqueueLinkCheck(array('link' => $link));
        }

        $output->writeln('Done!');
    }
}

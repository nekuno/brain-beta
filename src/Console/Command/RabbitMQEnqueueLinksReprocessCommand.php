<?php

namespace Console\Command;

use Console\ApplicationAwareCommand;
use Service\AMQPManager;
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
        /* @var $amqpManager AMQPManager */
        $amqpManager = $this->app['amqpManager.service'];
        $linksModel = $this->app['links.model'];
        $links = $linksModel->getLinks(array('link.processed = 0'), 0 , 100000000);

        $output->writeln(count($links) . ' links');

        foreach ($links as $link) {
            $output->writeln('Link ' . $link['url'] . ' will be reprocessed');
            $output->writeln(sprintf('Enqueuing url reprocess for "%s"', $link['url']));
            $amqpManager->enqueueLinkReprocess(array('link' => $link));
        }

        $output->writeln('Done!');
    }
}

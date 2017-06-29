<?php

namespace Service\Links;

use Model\Link\LinkModel;
use Service\AMQPManager;
use Symfony\Component\Console\Output\OutputInterface;

class EnqueueLinksService
{
    /**
     * @var LinkModel
     */
    protected $linkModel;

    /**
     * @var AMQPManager
     */
    protected $amqpManager;

    public function __construct(LinkModel $linkModel, AMQPManager $amqpManager)
    {
        $this->linkModel = $linkModel;
        $this->amqpManager = $amqpManager;
    }

    public function enqueueLinksCheck(OutputInterface $output)
    {
        $offset = 0;
        $limit = 1000;
        $links = array();
        while ($offset == 0 || count($links) > 0) {
            $links = $this->linkModel->getLinks(array('link.processed = 1'), $offset, $limit);
            $offset = $offset + $limit;

            $output->writeln(count($links) . ' links');

            foreach ($links as $link) {
                $output->writeln('Link ' . $link['url'] . ' will be checked');
                $output->writeln(sprintf('Enqueuing url check for "%s"', $link['url']));
                $this->amqpManager->enqueueLinkCheck(array('link' => $link));
            }
        }
    }

    public function enqueueLinksReprocess(OutputInterface $output)
    {
        $offset = 0;
        $limit = 1000;
        $links = array();
        while ($offset == 0 || count($links) > 0) {
            $links = $this->linkModel->getLinks(array('link.processed = 0'), $offset, $limit);

            $output->writeln(count($links) . ' links');

            foreach ($links as $link) {
                $output->writeln('Link ' . $link['url'] . ' will be reprocessed');
                $output->writeln(sprintf('Enqueuing url reprocess for "%s"', $link['url']));
                $this->amqpManager->enqueueLinkReprocess(array('link' => $link));
            }
        }
    }
}
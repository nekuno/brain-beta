<?php


namespace ApiConsumer\EventListener;

use Event\ReprocessEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


class ReprocessLinksSubscriber implements EventSubscriberInterface
{

    /**
     * @var OutputInterface
     */
    protected $output;

    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
    }

    public static function getSubscribedEvents()
    {

        return array(
            \AppEvents::REPROCESS_START => array('onReprocessStart'),
            \AppEvents::REPROCESS_FINISH => array('onReprocessFinish'),
            \AppEvents::REPROCESS_ERROR => array('onReprocessError'),
        );
    }

    public function onReprocessStart(ReprocessEvent $event)
    {
        // Disabled for avoiding too much logs in log file
        //$this->output->writeln(sprintf('[%s] Reprocessing link "%s"', date('Y-m-d H:i:s'), $event->getUrl()));
    }

    public function onReprocessFinish(ReprocessEvent $event)
    {
        // Disabled for avoiding too much logs in log file
        /*$this->output->writeln(sprintf('[%s] Reprocessed links from url "%s"', date('Y-m-d H:i:s'), $event->getUrl()));
        $linksCount = 0;
        foreach ($event->getLinks() as $link) {
            $link['processed'] ? $linksCount++ : null;
        }
        $this->output->writeln(sprintf('%s links found', $linksCount));*/
    }

    public function onReprocessError(ReprocessEvent $event)
    {
        $this->output->writeln(sprintf('[%s] Error reprocessing link "%s"', date('Y-m-d H:i:s'), $event->getUrl()));
        $this->output->writeln(sprintf('Problem is: "%s"', $event->getError()));
    }
}

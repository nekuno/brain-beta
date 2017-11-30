<?php

namespace Service\Links;

use ApiConsumer\LinkProcessor\Processor\FacebookProcessor\AbstractFacebookProcessor;
use ApiConsumer\LinkProcessor\Processor\InstagramProcessor\InstagramProcessor;
use ApiConsumer\LinkProcessor\Processor\SpotifyProcessor\AbstractSpotifyProcessor;
use ApiConsumer\LinkProcessor\Processor\TwitterProcessor\AbstractTwitterProcessor;
use ApiConsumer\LinkProcessor\Processor\YoutubeProcessor\AbstractYoutubeProcessor;
use Model\Link\Audio;
use Model\Link\Creator;
use Model\Link\Image;
use Model\Link\Link;
use Model\Link\Video;
use Model\Neo4j\GraphManager;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateLinksService
{
    const LIMIT = 1000;
    /**
     * @var GraphManager
     */
    protected $gm;

    public function __construct(GraphManager $gm)
    {
        $this->gm = $gm;
    }

    public function migrateLinks(OutputInterface $output)
    {
        $count = $this->deleteCreatorNetworkLinks();
        $output->writeln(sprintf("Deleted %s CreatorTwitter and CreatorFacebook labels", $count));

        $this->setNetworkLinks($output);

        $count = $this->setWebLabels();
        $output->writeln(sprintf("Added %s Web labels", $count));
    }

    protected function setNetworkLinks(OutputInterface $output)
    {
        $count = $this->setFacebookLinks();
        $output->writeln(sprintf("Added %s LinkFacebook labels", $count));

        $count = $this->setTwitterLinks();
        $output->writeln(sprintf("Added %s LinkTwitter labels", $count));

        $count = $this->setSpotifyLinks();
        $output->writeln(sprintf("Added %s LinkSpotify labels", $count));

        $count = $this->setYouTubeLinks();
        $output->writeln(sprintf("Added %s LinkYoutube labels", $count));

        $count = $this->setInstagramLinks();
        $output->writeln(sprintf("Added %s LinkInstagram labels", $count));
    }

    protected function deleteCreatorNetworkLinks()
    {
        $qb = $this->gm->createQueryBuilder();
        $linksCount = 0;

        $qb->match('(l:Link:CreatorFacebook)')
            ->remove('l :CreatorFacebook')
            ->returns('l AS link');

        $result = $qb->getQuery()->getResultSet();
        if (count($result) > 0) {
            $linksCount += count($result);
        }

        $qb = $this->gm->createQueryBuilder();
        $qb->match('(l:Link:CreatorTwitter)')
            ->remove('l :CreatorTwitter')
            ->returns('l AS link');

        $result = $qb->getQuery()->getResultSet();
        if (count($result) > 0) {
            $linksCount += count($result);
        }

        return $linksCount;
    }

    protected function setFacebookLinks()
    {
        return $this->setNetworkLinkLabels(AbstractFacebookProcessor::FACEBOOK_LABEL, "(?i)https?:\\/\\/(www\\.)?facebook\\.com.*");
    }

    protected function setTwitterLinks()
    {
        return $this->setNetworkLinkLabels(AbstractTwitterProcessor::TWITTER_LABEL, '(?i)https?:\\/\\/(www\\.|pic\\.)?twitter\\.com.*');
    }

    protected function setSpotifyLinks()
    {
        return $this->setNetworkLinkLabels(AbstractSpotifyProcessor::SPOTIFY_LABEL, '(?i)https?:\\/\\/(open\\.|play\\.)?spotify\\.com.*');
    }

    protected function setYouTubeLinks()
    {
        return $this->setNetworkLinkLabels(AbstractYoutubeProcessor::YOUTUBE_LABEL, '(?i)https?:\\/\\/(www\\.)?(youtube\\.com|youtu\\.be).*');
    }

    protected function setInstagramLinks()
    {
        return $this->setNetworkLinkLabels(InstagramProcessor::INSTAGRAM_LABEL, '(?i)https?:\\/\\/(www\\.)?instagram\\.com.*');
    }

    private function setNetworkLinkLabels($label, $regex)
    {
        $linksCount = 0;
        do {
            $qb = $this->gm->createQueryBuilder();
            $qb->match('(l:Link)')
                ->where("NOT l:$label", "l.url =~ { regex }")
                ->setParameter('regex', $regex)
                ->with('l')
                ->limit(self::LIMIT)
                ->set("l :$label")
                ->returns('l AS link');

            $result = $qb->getQuery()->getResultSet();
            if (count($result) > 0) {
                $linksCount += count($result);
            }
        } while (count($result) > 0);

        return $linksCount;
    }

    protected function setWebLabels()
    {
        $webLabel = Link::WEB_LABEL;
        $excludedQuery = array(
            'NOT l:' . Audio::AUDIO_LABEL,
            'NOT l:' . Video::VIDEO_LABEL,
            'NOT l:' . Creator::CREATOR_LABEL,
            'NOT l:' . Image::IMAGE_LABEL,
            'NOT l:' . $webLabel,
        );

        $linksCount = 0;

        do {
            $qb = $this->gm->createQueryBuilder();
            $qb->match('(l:Link)')
                ->where($excludedQuery)
                ->with('l')
                ->limit(self::LIMIT)
                ->set("l :$webLabel")
                ->returns('l AS link');

            $result = $qb->getQuery()->getResultSet();
            if (count($result) > 0) {
                $linksCount += count($result);
            }
        } while (count($result) > 0);

        return $linksCount;
    }
}
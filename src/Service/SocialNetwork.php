<?php

namespace Service;

use ApiConsumer\Factory\FetcherFactory;
use ApiConsumer\LinkProcessor\LinkAnalyzer;
use ApiConsumer\LinkProcessor\UrlParser\YoutubeUrlParser;
use Model\User\LookUpModel;
use Model\User\SocialNetwork\LinkedinSocialNetworkModel;
use Psr\Log\LoggerInterface;

/**
 * SocialNetwork
 */
class SocialNetwork
{
    /**
     * @var LinkedinSocialNetworkModel
     */
    protected $linkedinSocialNetworkModel;

    /**
     * @var LookupModel
     */
    protected $lookupModel;

    /**
     * @var FetcherFactory
     */
    protected $fetcherFactory;

    function __construct(
        LinkedinSocialNetworkModel $linkedinSocialNetworkModel,
        LookUpModel $lookupModel,
        FetcherFactory $fetcherFactory
    ) {
        $this->linkedinSocialNetworkModel = $linkedinSocialNetworkModel;
        $this->lookupModel = $lookupModel;
        $this->fetcherFactory = $fetcherFactory;
    }

    public function setSocialNetworksInfo($userId, $socialNetworks, LoggerInterface $logger = null)
    {
        foreach ($socialNetworks as $resource => $profileUrl) {
            $this->setSocialNetworkInfo($userId, $resource, $profileUrl, $logger, $socialNetworks);
        }
    }

    protected function setSocialNetworkInfo($userId, $resource, $profileUrl, LoggerInterface $logger = null, array $socialNetworks)
    {
        switch ($resource) {
            case 'linkedin':
                $this->linkedinSocialNetworkModel->set($userId, $profileUrl, $logger);
                if ($logger) {
                    $logger->info('linkedin social network info added for user ' . $userId . ' (' . $profileUrl . ')');
                }
                break;
            case 'googleplus':
                if (isset($socialNetworks['youtube'])) {
                    break;
                }

                if ($logger) {
                    $logger->info('Analyzing google plus profile for getting youtube profile');
                }

                $googleId = LinkAnalyzer::getUsername($profileUrl);

                $googleProfileFetcher = $this->fetcherFactory->build('google_profile');
                $profiles = $googleProfileFetcher->fetchAsClient($googleId);

                if (count($profiles) !== 1) {
                    $logger->info('Youtube profile not found.');
                    break;
                }

                foreach ($profiles as $profile) {
                    $url = $profile->getUrl();
                    if (strpos($url, 'youtube.com')) {
                        $socialProfile = array(YoutubeUrlParser::GENERAL_URL => $url);
                        $this->lookupModel->setSocialProfiles($socialProfile, $userId);
                        $this->lookupModel->dispatchSocialNetworksAddedEvent($userId, $socialProfile);
                        if ($logger) {
                            $logger->info('Youtube url ' . $url . ' found and joined to user ' . $userId . '.');
                        }
                    }
                }
        }
    }
}
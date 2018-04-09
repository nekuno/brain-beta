<?php

namespace Service;

use ApiConsumer\Fetcher\FetcherService;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\YoutubeUrlParser;
use Model\LookUp\LookUpManager;
use Model\Profile\ProfileManager;
use Model\Profile\ProfileTagManager;
use Model\SocialNetwork\LinkedinSocialNetworkManager;
use Psr\Log\LoggerInterface;

/**
 * SocialNetwork
 */
class SocialNetwork
{
    /**
     * @var LinkedinSocialNetworkManager
     */
    protected $linkedinSocialNetworkManager;

    /**
     * @var LookUpManager
     */
    protected $lookUpManager;

    protected $profileTagManager;

    protected $profileManager;

    protected $fetcherService;

    function __construct(
        LinkedinSocialNetworkManager $linkedinSocialNetworkManager,
        LookUpManager $lookUpManager,
        ProfileTagManager $profileTagManager,
        ProfileManager $profileManager,
        FetcherService $fetcherService
    ) {
        $this->linkedinSocialNetworkManager = $linkedinSocialNetworkManager;
        $this->lookUpManager = $lookUpManager;
        $this->profileTagManager = $profileTagManager;
        $this->profileManager = $profileManager;
        $this->fetcherService = $fetcherService;
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
                $this->linkedinSocialNetworkManager->set($userId, $profileUrl, $logger);
                $data = $this->linkedinSocialNetworkManager->getData($profileUrl, $logger);
                $locale = $this->profileManager->getInterfaceLocale($userId);

                $skills = array_filter($data['tags']);
                $this->profileTagManager->addTags($userId, $locale, 'Profession', $skills);

                $languages = array_filter($data['languages']);
                $this->profileTagManager->addTags($userId, $locale, 'Language', $languages);

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

                $profiles = $this->fetcherService->fetchGoogleProfiles($profileUrl);

                if (count($profiles) !== 1) {
                    $logger->info('Youtube profile not found.');
                    break;
                }

                /** @var PreprocessedLink $profile */
                foreach ($profiles as $profile) {
                    $url = $profile->getUrl();
                    if (strpos($url, 'youtube.com')) {
                        $socialProfile = array(YoutubeUrlParser::GENERAL_URL => $url);
                        $this->lookUpManager->setSocialProfiles($socialProfile, $userId);
                        $this->lookUpManager->dispatchSocialNetworksAddedEvent($userId, $socialProfile);
                        if ($logger) {
                            $logger->info('Youtube url ' . $url . ' found and joined to user ' . $userId . '.');
                        }
                    }
                }
        }
    }
}
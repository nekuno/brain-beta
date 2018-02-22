<?php

namespace Service;

use ApiConsumer\Factory\FetcherFactory;
use ApiConsumer\LinkProcessor\LinkAnalyzer;
use ApiConsumer\LinkProcessor\UrlParser\YoutubeUrlParser;
use Model\User\LookUpModel;
use Model\User\ProfileModel;
use Model\User\ProfileTagModel;
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

    protected $profileTagModel;

    protected $profileModel;

    /**
     * @var FetcherFactory
     */
    protected $fetcherFactory;

    function __construct(
        LinkedinSocialNetworkModel $linkedinSocialNetworkModel,
        LookUpModel $lookupModel,
        ProfileTagModel $profileTagModel,
        ProfileModel $profileModel,
        FetcherFactory $fetcherFactory
    ) {
        $this->linkedinSocialNetworkModel = $linkedinSocialNetworkModel;
        $this->lookupModel = $lookupModel;
        $this->profileTagModel = $profileTagModel;
        $this->profileModel = $profileModel;
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
                $data = $this->linkedinSocialNetworkModel->getData($profileUrl, $logger);
                $locale = $this->profileModel->getInterfaceLocale($userId);

                $skills = array_filter($data['tags']);
                $this->profileTagModel->addTags($userId, $locale, 'Profession', $skills);

                $languages = array_filter($data['languages']);
                $this->profileTagModel->addTags($userId, $locale, 'Language', $languages);

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
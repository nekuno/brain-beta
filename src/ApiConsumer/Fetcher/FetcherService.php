<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\Exception\PaginatedFetchingException;
use ApiConsumer\Factory\FetcherFactory;
use ApiConsumer\LinkProcessor\LinkAnalyzer;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\SynonymousParameters;
use ApiConsumer\LinkProcessor\UrlParser\YoutubeUrlParser;
use Event\ExceptionEvent;
use Event\FetchEvent;
use GuzzleHttp\Exception\RequestException;
use Model\User\SocialNetwork\SocialProfileManager;
use Model\User\Token\TokensModel;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Class FetcherService
 * @package ApiConsumer\Fetcher
 */
class FetcherService implements LoggerAwareInterface
{

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var FetcherFactory
     */
    protected $fetcherFactory;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var TokensModel
     */
    protected $tokensModel;

    /**
     * @var SocialProfileManager
     */
    protected $socialProfileManager;

    /**
     * @var array
     */
    protected $options;

    public function __construct(
        FetcherFactory $fetcherFactory,
        EventDispatcher $dispatcher,
        TokensModel $tokensModel,
        SocialProfileManager $socialProfileManager,
        array $options
    ) {
        $this->fetcherFactory = $fetcherFactory;
        $this->dispatcher = $dispatcher;
        $this->tokensModel = $tokensModel;
        $this->socialProfileManager = $socialProfileManager;
        $this->options = $options;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function fetchAllConnected($userId, $exclude = array())
    {
        $tokens = $this->tokensModel->getByUserOrResource($userId);

        $links = array();
        foreach ($tokens as $token) {
            $resourceOwner = $token['resourceOwner'];
            $fetchers = $this->chooseFetchers($resourceOwner, $exclude);

            foreach ($fetchers as $fetcher) {
                $links = array_merge($links, $this->fetchSingle($fetcher, $token, false, $resourceOwner));
            }
        }

        return $links;
    }

    //public function fetAllUnconnected($userId, $exclude = array())

    public function fetchUser($userId, $resourceOwner, $exclude = array())
    {
        $fetchers = $this->chooseFetchers($resourceOwner, $exclude);

        $tokens = $this->tokensModel->getByUserOrResource($userId, $resourceOwner);

        //TODO: If count(tokens) > 1, error?

        $this->dispatcher->dispatch(\AppEvents::FETCH_START, new FetchEvent($userId, $resourceOwner));

        $links = array();
        if (!empty($tokens)) {
            foreach ($tokens as $token) {
                foreach ($fetchers as $fetcher) {
                    $links = array_merge($links, $this->fetchSingle($fetcher, $token, false, $resourceOwner));
                }
            }
        } else {

            $socialProfiles = $this->socialProfileManager->getSocialProfiles($userId, $resourceOwner);

            //TODO: If count(socialProfiles) > 1, log, not always error
            foreach ($socialProfiles as $socialProfile){
                $url = $socialProfile->getUrl();

                $username = LinkAnalyzer::getUsername($url);

                foreach ($fetchers as $fetcher)
                {
                    $links = array_merge($links, $this->fetchPublic($fetcher, $userId, $username, $resourceOwner));
                }
            }
        }

        $this->dispatcher->dispatch(\AppEvents::FETCH_FINISH, new FetchEvent($userId, $resourceOwner));

    }

    /**
     * @param array $token
     * @param array $exclude fetcher names that are not to be used
     * @return \ApiConsumer\LinkProcessor\PreprocessedLink[]
     * @throws \Exception
     */
    public function fetch($token, $exclude = array())
    {
        if (!$token) {
            return array();
        }

        if (array_key_exists('id', $token)) {
            $userId = $token['id'];
        } else {
            return array();
        }

        $resourceOwner = isset($token['resourceOwner']) ? $token['resourceOwner'] : null;

        $public = isset($token['public']) ? $token['public'] : false;

        /* @var $links PreprocessedLink[] */
        $links = array();
        try {

            $this->dispatcher->dispatch(\AppEvents::FETCH_START, new FetchEvent($userId, $resourceOwner));

            $fetchers = $this->chooseFetchers($resourceOwner, $exclude);
            foreach ($fetchers as $fetcher) {
                $links = array_merge($links, $this->fetchSingle($fetcher, $token, $public, $resourceOwner));
            }

            foreach ($links as $link) {
                $link->setToken($token);
                $link->setSource($resourceOwner);
            }

            $this->dispatcher->dispatch(\AppEvents::FETCH_FINISH, new FetchEvent($userId, $resourceOwner));

        } catch (\Exception $e) {
            throw new \Exception(sprintf('Fetcher: Error fetching from resource "%s" for user "%d". Message: %s on file %s in line %d', $resourceOwner, $userId, $e->getMessage(), $e->getFile(), $e->getLine()), 1);
        }

        return $links;
    }

    private function fetchSingle(FetcherInterface $fetcher, $token, $public, $resourceOwner)
    {
        $userId = $token['id'];

        try {
            $links = $fetcher->fetchLinksFromUserFeed($token, $public);

            return $links;
        } catch (PaginatedFetchingException $e) {
            //TODO: Improve exception management between services, controllers, workers...
            $originalException = $e->getOriginalException();
            if ($originalException instanceof RequestException && $originalException->getCode() == 429) {
                if ($resourceOwner == TokensModel::TWITTER) {
                    $this->logger->warning('Pausing for 15 minutes due to Too Many Requests Error');
                    sleep(15 * 60);
                }
            }
            $newLinks = $e->getLinks();
            $this->logger->warning(sprintf('Fetcher: Error fetching feed for user "%s" with fetcher "%s" from resource "%s". Reason: %s', $userId, get_class($fetcher), $resourceOwner, $originalException->getMessage()));
            $this->dispatcher->dispatch(\AppEvents::EXCEPTION_WARNING, new ExceptionEvent($e, sprintf('Fetcher: Error fetching feed for user "%s" with fetcher "%s" from resource "%s". Reason: %s', $userId, get_class($fetcher), $resourceOwner, $originalException->getMessage())));
            $this->logger->info(sprintf('%d links were fetched before the exception happened and are going to be processed.', count($newLinks)));

            return $newLinks;

        } catch (\Exception $e) {
            $this->logger->error(sprintf('Fetcher: Error fetching feed for user "%s" with fetcher "%s" from resource "%s". Reason: %s', $userId, get_class($fetcher), $resourceOwner, $e->getMessage()));

            return array();
        }
    }

    private function fetchPublic(FetcherInterface $fetcher, $userId, $username, $resourceOwner)
    {
        try {
            $links = $fetcher->fetchAsClient($username);

            return $links;
        } catch (PaginatedFetchingException $e) {
            //TODO: Improve exception management between services, controllers, workers...
            $originalException = $e->getOriginalException();
            if ($originalException instanceof RequestException && $originalException->getCode() == 429) {
                if ($resourceOwner == TokensModel::TWITTER) {
                    $this->logger->warning('Pausing for 15 minutes due to Too Many Requests Error');
                    sleep(15 * 60);
                }
            }
            $newLinks = $e->getLinks();
            $this->logger->warning(sprintf('Fetcher: Error fetching feed for user "%s" with fetcher "%s" from resource "%s". Reason: %s', $userId, get_class($fetcher), $resourceOwner, $originalException->getMessage()));
            $this->dispatcher->dispatch(\AppEvents::EXCEPTION_WARNING, new ExceptionEvent($e, sprintf('Fetcher: Error fetching feed for user "%s" with fetcher "%s" from resource "%s". Reason: %s', $userId, get_class($fetcher), $resourceOwner, $originalException->getMessage())));
            $this->logger->info(sprintf('%d links were fetched before the exception happened and are going to be processed.', count($newLinks)));

            return $newLinks;

        } catch (\Exception $e) {
            $this->logger->error(sprintf('Fetcher: Error fetching feed for user "%s" with fetcher "%s" from resource "%s". Reason: %s', $userId, get_class($fetcher), $resourceOwner, $e->getMessage()));

            return array();
        }
    }

    /**
     * @param SynonymousParameters $parameters
     * @return \ApiConsumer\LinkProcessor\PreprocessedLink[]
     */
    public function fetchSynonymous(SynonymousParameters $parameters)
    {
        switch ($parameters->getType()) {
            case YoutubeUrlParser::VIDEO_URL:
                /** @var YoutubeFetcher $fetcher */
                $fetcher = $this->fetcherFactory->build('youtube');
                $synonymous = $fetcher->fetchVideos($parameters);
                break;
            default:
                $synonymous = array();
                break;
        }

        return $synonymous;
    }

    /**
     * @param $resourceOwner
     * @param array $exclude
     * @return FetcherInterface[]
     */
    protected function chooseFetchers($resourceOwner, $exclude = array())
    {
        $fetchers = array();
        foreach ($this->options as $fetcherName => $fetcherConfig) {

            if (in_array($fetcherName, $exclude)) {
                continue;
            }

            if ($fetcherConfig['resourceOwner'] === $resourceOwner) {
                $fetchers[] = $this->fetcherFactory->build($fetcherName);
            }
        }

        return $fetchers;
    }
}

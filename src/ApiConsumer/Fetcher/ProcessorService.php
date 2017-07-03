<?php

namespace ApiConsumer\Fetcher;

use ApiConsumer\Event\ChannelEvent;
use ApiConsumer\Exception\CannotProcessException;
use ApiConsumer\Exception\CouldNotResolveException;
use ApiConsumer\Exception\TokenException;
use ApiConsumer\Exception\UrlChangedException;
use ApiConsumer\Exception\UrlNotValidException;
use ApiConsumer\LinkProcessor\LinkAnalyzer;
use ApiConsumer\LinkProcessor\LinkProcessor;
use ApiConsumer\LinkProcessor\LinkResolver;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use Event\ConsistencyEvent;
use Event\ProcessLinkEvent;
use GuzzleHttp\Exception\RequestException;
use Model\Link\Creator\Creator;
use Model\Link\LinkModel;
use Model\Neo4j\Neo4jException;
use Model\User\RateModel;
use Model\User\Token\TokensModel;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Service\EventDispatcher;

class ProcessorService implements LoggerAwareInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    protected $fetcherService;

    protected $linkModel;

    protected $dispatcher;

    protected $rateModel;

    protected $resolver;

    protected $linkProcessor;

    public function __construct(FetcherService $fetcherService, LinkProcessor $linkProcessor, LinkModel $linkModel, EventDispatcher $dispatcher, RateModel $rateModel, LinkResolver $resolver)
    {
        $this->fetcherService = $fetcherService;
        $this->linkProcessor = $linkProcessor;
        $this->linkModel = $linkModel;
        $this->dispatcher = $dispatcher;
        $this->rateModel = $rateModel;
        $this->resolver = $resolver;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param PreprocessedLink[] $preprocessedLinks
     * @param int $userId
     * @return array
     */
    public function process(array $preprocessedLinks, $userId)
    {
        $links = array();
        $this->preProcess($preprocessedLinks);

        foreach ($preprocessedLinks as $key => $preprocessedLink) {

            $source = $preprocessedLink->getSource();
            $this->dispatcher->dispatch(\AppEvents::PROCESS_LINK, new ProcessLinkEvent($userId, $source, $preprocessedLink));

            try {
                $processedLinks = $this->fullProcessSingle($preprocessedLink, $userId);
                $links = array_merge($links, $processedLinks);

            } catch (TokenException $e) {
                $this->removeToken($preprocessedLinks);

                $message = sprintf('Processing with the token for user %d and resource %s', $e->getToken()->getUserId(), $e->getToken()->getResourceOwner());
                $this->manageError($e, $message);

                $processedLinks = $this->fullProcessSingle($preprocessedLink, $userId);
                $links = array_merge($links, $processedLinks);
            }
        }
        $source = $this->getCommonSource($preprocessedLinks);
        $links = array_merge($links, $this->processLastLinks($userId, $source));

        return $links;
    }

    /**
     * @param PreprocessedLink[] $preprocessedLinks
     * @return array
     */
    public function preProcess(array $preprocessedLinks)
    {
        $this->logNotice(sprintf('%s links to preprocess', count($preprocessedLinks)));
        $newPreprocessedLinks = array();
        foreach ($preprocessedLinks as $key => $preprocessedLink) {
            $url = $preprocessedLink->getUrl();
            $this->logInfo(sprintf('Preprocessing link %s', $url));

            $urls = $this->separateUrls($url);

            // Fuse urls if is query param, e.g. http://example.com/?q=http://example.com
            foreach ($urls as $index => $singleUrl) {
                if (substr($singleUrl, -1, 1) === "=" && isset($urls[$index + 1])) {
                    $urls[$index] .= $urls[$index + 1];
                }
                $this->logInfo(sprintf('Preprocessed link %s', $urls[$index]));
            }

            // Create new PreprocessedLinks if needed
            if (count($urls) > 1 || isset($urls[0]) && $url !== $urls[0]) {
                $this->logNotice(sprintf('Preprocessed link %s differs from original %s, or more urls can be extracted', $urls[0], $url));
                foreach ($urls as $singleUrl) {
                    $newPreprocessedLink = clone $preprocessedLink;
                    $newPreprocessedLink->setUrl($singleUrl);
                    $newPreprocessedLinks[] = $newPreprocessedLink;
                    $this->logNotice(sprintf('New preprocessed link %s created', $singleUrl));
                }
            } else {
                $newPreprocessedLinks[] = $preprocessedLink;
            }
        }
        $this->logNotice(sprintf('%s links preprocessed', count($newPreprocessedLinks)));

        return $newPreprocessedLinks;
    }

    private function separateUrls($url)
    {
        preg_match_all('~(?:https?://).*?(?=$|(?:https?://))~', $url, $matches);

        return $matches[0];
    }

    private function fullProcessSingle(PreprocessedLink $preprocessedLink, $userId, $processedTimes = 0)
    {
        try {
            $this->resolve($preprocessedLink);
        } catch (CouldNotResolveException $e) {
            $this->manageUrlUnprocessed($e, sprintf('resolving url %s while processing for user %d', $preprocessedLink->getUrl(), $userId), $preprocessedLink->getUrl());

            $links = $this->save($preprocessedLink);
            $this->like($userId, $links, $preprocessedLink);

            return $links;
        } catch (UrlChangedException $e) {
        }

        if ($this->isLinkSavedAndProcessed($preprocessedLink)) {
            $link = $this->linkModel->findLinkByUrl($preprocessedLink->getUrl());
            $this->like($userId, array($link), $preprocessedLink);

            return array();
        }

        try {
            $this->processLink($preprocessedLink);
        } catch (UrlChangedException $e) {

            if ($processedTimes <= 10) {
                $preprocessedLink->setUrl($e->getNewUrl());

                return $this->fullProcessSingle($preprocessedLink, $userId, ++$processedTimes);
            } else {
                return $this->scrape($preprocessedLink);
            }

        } catch (TokenException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->manageError($e, sprintf('processing url %s for user %d', $preprocessedLink->getUrl(), $userId));

            return array();
        }

        $this->addSynonymous($preprocessedLink);
        $this->checkCreator($preprocessedLink);

        $links = $this->save($preprocessedLink);
        $this->like($userId, $links, $preprocessedLink);

        return $links;
    }

    private function processLastLinks($userId, $source)
    {
        $processedLinks = $this->linkProcessor->processLastLinks();

        $links = array();
        foreach ($processedLinks as $processedLink) {
            $preprocessedLink = new PreprocessedLink($processedLink->getUrl());
            $preprocessedLink->setFirstLink($processedLink);
            $preprocessedLink->setSource($source);

            $savedLinks = $this->save($preprocessedLink);
            $this->like($userId, $savedLinks, $preprocessedLink);
            $links = array_merge($links, $savedLinks);
        }

        return $links;
    }

    /**
     * @param PreprocessedLink[] $preprocessedLinks
     * @throws \Exception
     * @return array[]
     */
    public function reprocess(array $preprocessedLinks)
    {
        if (isset($preprocessedLinks[0])) {
            if (!$this->linkModel->findLinkByUrl($preprocessedLinks[0]->getUrl())) {
                throw new \Exception(sprintf('Url %s not found in database', $preprocessedLinks[0]->getUrl()));
            }
        }

        $links = array();
        $this->preProcess($preprocessedLinks);

        foreach ($preprocessedLinks as $key => $preprocessedLink) {
            $this->logNotice(sprintf('Reprocessing link %s', $preprocessedLink->getUrl()));
            try {
                $reprocessedLinks = $this->fullReprocessSingle($preprocessedLink);
                $links = array_merge($links, $reprocessedLinks);

            } catch (TokenException $e) {
                $this->removeToken($preprocessedLinks);

                $message = sprintf('Reprocessing with the token for user %d and resource %s', $e->getToken()->getUserId(), $e->getToken()->getResourceOwner());
                $this->manageError($e, $message);

                $reprocessedLinks = $this->fullReprocessSingle($preprocessedLink);
                $links = array_merge($links, $reprocessedLinks);
            }
        }

        return $links;
    }

    /**
     * @param PreprocessedLink[] $preprocessedLinks
     */
    private function removeToken(array $preprocessedLinks)
    {
        foreach ($preprocessedLinks as $preprocessedLink) {
            $preprocessedLink->setToken(null);
        }
    }

    private function fullReprocessSingle(PreprocessedLink $preprocessedLink)
    {
        try {
            $this->resolve($preprocessedLink);
        } catch (CouldNotResolveException $e) {
            $this->manageError($e, sprintf('resolving url %s while reprocessing', $preprocessedLink->getUrl()));
            $links = $this->overwrite($preprocessedLink);

            return $links;
        } catch (UrlChangedException $e) {
            $this->manageChangedUrl($e->getOldUrl(), $e->getNewUrl());
        }

        try {
            $this->processLink($preprocessedLink);
            $links = $this->save($preprocessedLink);

            return $links;
        } catch (UrlChangedException $e) {

            $oldUrl = $e->getOldUrl();
            $newUrl = $e->getNewUrl();
            $this->manageChangedUrl($oldUrl, $newUrl);

            $preprocessedLink->setUrl($newUrl);

            return $this->fullReprocessSingle($preprocessedLink);

        } catch (TokenException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->manageError($e, sprintf('reprocessing link %s', $preprocessedLink->getUrl()));

            return array();
        }
    }

    private function manageChangedUrl($oldUrl, $newUrl)
    {
        if ($this->linkModel->findLinkByUrl($newUrl)) {
            $fusedLink = $this->linkModel->fuseLinks($oldUrl, $newUrl);
            $this->dispatcher->dispatch(\AppEvents::CONSISTENCY_LINK, new ConsistencyEvent($fusedLink['id']));
        } else {
            $this->linkModel->setProcessed($oldUrl, false);
            $this->linkModel->changeUrl($oldUrl, $newUrl);
        }
    }

    private function getCommonSource(array $preprocessedLinks)
    {
        if (empty($preprocessedLinks)) {
            $source = null;
        } else {
            $source = reset($preprocessedLinks)->getSource();
        }

        return $source;
    }

    private function resolve(PreprocessedLink $preprocessedLink)
    {
        if (!LinkAnalyzer::mustResolve($preprocessedLink)) {
            return;
        }

        $resolution = $this->resolver->resolve($preprocessedLink);

        if (null == $resolution->getFinalUrl()) {
            throw new CouldNotResolveException($preprocessedLink->getUrl());
        }

        $preprocessedLink->setUrl($resolution->getFinalUrl());

        if ($resolution->getStartingUrl() !== $resolution->getFinalUrl()) {
            throw new UrlChangedException($resolution->getStartingUrl(), $resolution->getFinalUrl());
        }
    }

    private function processLink(PreprocessedLink $preprocessedLink)
    {
        if (!$this->manageUrl($preprocessedLink)) {
            return;
        }

        try {
            $links = $this->linkProcessor->process($preprocessedLink);
        } catch (CannotProcessException $e) {
            $links = $this->scrape($preprocessedLink);
        } catch (RequestException $e) {
            $this->manageError($e, 'requesting while processing from linkProcessor');
            $links = $this->scrape($preprocessedLink);
        }

        $preprocessedLink->setLinks($links);
    }

    private function manageUrl(PreprocessedLink $preprocessedLink)
    {
        try {
            $cleanURL = LinkAnalyzer::cleanUrl($preprocessedLink->getUrl());
            $preprocessedLink->setUrl($cleanURL);

            $type = LinkAnalyzer::getProcessorName($preprocessedLink);
            $preprocessedLink->setType($type);
        } catch (UrlNotValidException $e) {
            $url = $preprocessedLink->getUrl();
            $this->manageUrlUnprocessed($e, sprintf('managing url while processing %s', $url), $url);
            $this->getUnprocessedLinks($preprocessedLink);

            return false;
        } catch (\Exception $e) {
            $this->manageError($e, sprintf('managing url while processing %s', $preprocessedLink->getUrl()));
            $this->getUnprocessedLinks($preprocessedLink);

            return false;
        }

        return true;
    }

    private function scrape(PreprocessedLink $preprocessedLink)
    {
        try {
            return $this->linkProcessor->scrape($preprocessedLink);
        } catch (CannotProcessException $e) {
            $this->manageError($e, sprintf('scraping %s', $preprocessedLink->getUrl()));

            return $this->getUnprocessedLinks($preprocessedLink);
        }
    }

    private function isLinkSavedAndProcessed(PreprocessedLink $preprocessedLink)
    {
        try {
            $linkUrl = $preprocessedLink->getUrl();
            $storedLink = $this->linkModel->findLinkByUrl($linkUrl);

            return $storedLink && isset($storedLink['processed']) && $storedLink['processed'] == '1';

        } catch (\Exception $e) {
            $this->manageError($e, sprintf('checking saved and processed for %s', $preprocessedLink->getUrl()));

            return false;
        }
    }

    private function checkCreator(PreprocessedLink $preprocessedLink)
    {
        foreach ($preprocessedLink->getLinks() as $link) {
            if ($link instanceof Creator && $preprocessedLink->getSource() == TokensModel::TWITTER) {
                try {
                    $username = (new TwitterUrlParser())->getProfileId($link->getUrl());
                    $this->dispatcher->dispatch(\AppEvents::CHANNEL_ADDED, new ChannelEvent(TokensModel::TWITTER, $link->getUrl(), $username));
                } catch (\Exception $e) {
                    $this->manageError($e, sprintf('checking creator for url %s', $link->getUrl()));
                }
            }
        }
    }

    private function addSynonymous(PreprocessedLink $preprocessedLink)
    {
        try {
            $synonymousPreprocessed = $this->fetcherService->fetchSynonymous($preprocessedLink->getSynonymousParameters());

            foreach ($synonymousPreprocessed as $singleSynonymous) {
                $this->processLink($singleSynonymous);
                $preprocessedLink->getFirstLink()->addSynonymous($singleSynonymous->getFirstLink());
            }
        } catch (\Exception $e) {
            $this->manageError($e, sprintf('fetching synonymous for %s', $preprocessedLink->getUrl()));

            return;
        }
    }

    private function save(PreprocessedLink $preprocessedLink)
    {
        $links = array();
        $this->readyToSave($preprocessedLink);
        foreach ($preprocessedLink->getLinks() as $link) {

            if (!$link->getUrl()) {
                continue;
            }

            try {
                $linkCreated = $this->linkModel->addOrUpdateLink($link->toArray());
                $links[] = $linkCreated;
            } catch (\Exception $e) {
                $this->manageError($e, sprintf('saving link %s from resource %s', $preprocessedLink->getUrl(), $preprocessedLink->getSource()));

                continue;
            }
        }

        return $links;
    }

    private function readyToSave(PreprocessedLink $preprocessedLink)
    {
        foreach ($preprocessedLink->getLinks() as $link) {
            if (!$link->isComplete()) {
                //log
                $this->getUnprocessedLinks($preprocessedLink);
            } else {
                $link->setProcessed(true);
            }
        }

        return $preprocessedLink->getLinks();
    }

    private function overwrite(PreprocessedLink $preprocessedLink)
    {
        $links = $this->getUnprocessedLinks($preprocessedLink);
        $updatedLinks = array();

        foreach ($links as $link) {
            $updatedLinks[] = $this->linkModel->updateLink($link->toArray());
        }

        return $updatedLinks;
    }

    private function getUnprocessedLinks(PreprocessedLink $preprocessedLink)
    {
        foreach ($preprocessedLink->getLinks() as $link) {
            $link->setProcessed(false);
        }

        return $preprocessedLink->getLinks();
    }

    private function like($userId, array $links, PreprocessedLink $preprocessedLink)
    {
        $likes = array();
        $source = $preprocessedLink->getSource() ?: 'nekuno';
        foreach ($links as $link) {
            $linkId = $link['id'];
            try {
                $like = $this->rateModel->userRateLink($userId, $linkId, $source, null, RateModel::LIKE, false);
                $likes[] = $like;
            } catch (\Exception $e) {
                $this->manageError($e, sprintf('liking while processing link with id %d for user $d', $linkId, $userId));
            }
        }

        return $likes;
    }

    private function logNotice($message)
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->notice($message);

            return true;
        }

        return false;
    }

    private function logInfo($message)
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->info($message);

            return true;
        }

        return false;
    }

    private function manageError(\Exception $e, $process)
    {
        $this->dispatcher->dispatchError($e, $process);

        if ($this->logger instanceof LoggerInterface) {
            $this->logger->error($e->getMessage());

            if ($e instanceof Neo4jException) {
                $this->logger->error(sprintf('Query: %s' . "\n" . 'Data: %s', $e->getQuery(), print_r($e->getData(), true)));
            }

            return true;
        }

        return false;
    }

    private function manageUrlUnprocessed(\Exception $e, $process, $url)
    {
        $this->logNotice(sprintf('Error processing url %s while %s', $url, $process));
        $this->dispatcher->dispatchUrlUnprocessed($e, $process);
    }

}
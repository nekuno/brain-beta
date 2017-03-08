<?php

namespace ApiConsumer\LinkProcessor\Processor\ScraperProcessor;

use ApiConsumer\Exception\CannotProcessException;
use ApiConsumer\Factory\GoutteClientFactory;
use ApiConsumer\Images\ImageResponse;
use ApiConsumer\LinkProcessor\MetadataParser\BasicMetadataParser;
use ApiConsumer\LinkProcessor\MetadataParser\FacebookMetadataParser;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use GuzzleHttp\Exception\RequestException;
use Model\Link\Image;
use Model\Link\Link;
use Symfony\Component\DomCrawler\Crawler;

class ScraperProcessor extends AbstractScraperProcessor
{
    /**
     * @var FacebookMetadataParser
     */
    private $facebookMetadataParser;

    /**
     * @var BasicMetadataParser
     */
    private $basicMetadataParser;

    /**
     * @param GoutteClientFactory $goutteGoutteClientFactory
     */
    public function __construct(GoutteClientFactory $goutteGoutteClientFactory)
    {
        parent::__construct($goutteGoutteClientFactory);
        $this->basicMetadataParser = new BasicMetadataParser();
        $this->facebookMetadataParser = new FacebookMetadataParser();
    }

    public function getResponse(PreprocessedLink $preprocessedLink)
    {
        $url = $preprocessedLink->getUrl();

        try {
            $this->client->getClient()->setDefaultOption('timeout', 30.0);
            $crawler = $this->client->request('GET', $url);
        } catch (\LogicException $e) {
            $this->client = $this->clientFactory->build();
            throw new CannotProcessException($url);
        } catch (RequestException $e) {
            $this->client = $this->clientFactory->build();
            throw new CannotProcessException($url);
        }

        $imageResponse = new ImageResponse($url, 200, $this->client->getResponse()->getHeader('Content-Type'));
        if ($imageResponse->isImage()) {
            $image = Image::buildFromArray($preprocessedLink->getFirstLink()->toArray());
            $preprocessedLink->setFirstLink($image);
        }

        return array('html' => $crawler->html());
    }

    public function hydrateLink(PreprocessedLink $preprocessedLink, array $data)
    {
        $link = $preprocessedLink->getFirstLink();

        $crawler = new Crawler();
        $crawler->addHtmlContent($data['html']);

        $basicMetadata = $this->basicMetadataParser->extractMetadata($crawler);
        $this->overrideFieldsData($link, $basicMetadata);

        $fbMetadata = $this->facebookMetadataParser->extractMetadata($crawler);
        $this->overrideFieldsData($link, $fbMetadata);
    }

    public function getImages(PreprocessedLink $preprocessedLink, array $data)
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($data['html']);

        $images = $this->basicMetadataParser->getImages($crawler);

        $url = $preprocessedLink->getUrl();
        $this->fixRelativeUrls($images, $url);
        $this->fixSchemeUrls($images, $url);

        return $images;
    }

    private function fixRelativeUrls(array &$images, $url)
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['scheme']) || !isset($parsedUrl['host'])) {
            return;
        }
        $prefix = $parsedUrl['scheme'] . '://' . trim($parsedUrl['host'], '/') . '/';

        foreach ($images as &$imageUrl) {
            if ($this->isRelativeUrl($imageUrl)) {
                $imageUrl = $prefix . $imageUrl;
            }
        }
    }

    private function fixSchemeUrls(array &$images, $url)
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['scheme'])) {
            return;
        }
        $prefix = $parsedUrl['scheme'] . ':';
        foreach ($images as &$imageUrl) {
            if ($this->startsWithDoubleSlash($imageUrl)) {
                $imageUrl = $prefix . $imageUrl;
            }
        }
    }

    private function isRelativeUrl($url)
    {
        $starsWithHttp = strpos($url, 'http://') === 0;
        $starsWithHttps = strpos($url, 'https://') === 0;
        $startsWithDoubleSlash = $this->startsWithDoubleSlash($url);

        return !$starsWithHttp && !$starsWithHttps && !$startsWithDoubleSlash;
    }

    private function startsWithDoubleSlash($url)
    {
        return strpos($url, '//') === 0;
    }

    private function overrideFieldsData(Link $link, array $scrapedData)
    {
        foreach (array('title', 'description', 'language', 'thumbnail') as $field) {
            if (!isset($scrapedData[$field]) || empty($scrapedData[$field])) {
                continue;
            }

            $setter = 'set' . ucfirst($field);
            $link->$setter($scrapedData[$field]);
        }
    }

    public function addTags(PreprocessedLink $preprocessedLink, array $data)
    {
        $link = $preprocessedLink->getFirstLink();
        $crawler = new Crawler();
        $crawler->addHtmlContent($data['html']);

        $basicMetadata['tags'] = $this->basicMetadataParser->extractTags($crawler);
        $this->addScrapedTags($link, $basicMetadata);

        $basicMetadata['tags'] = $this->facebookMetadataParser->extractTags($crawler);
        $this->addScrapedTags($link, $basicMetadata);
    }

    private function addScrapedTags(Link $link, array $scrapedData)
    {
        if (array_key_exists('tags', $scrapedData) && is_array($scrapedData['tags'])) {

            foreach ($scrapedData['tags'] as $tag) {
                $link->addTag($tag);
            }
        }
    }
}

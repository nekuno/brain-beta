<?php


namespace ApiConsumer\LinkProcessor\MetadataParser;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Class BasicMetadataParser
 * @package ApiConsumer\LinkProcessor\MetadataParser
 */
class BasicMetadataParser implements MetadataParserInterface
{

    /**
     *
     */
    const MAX_WORDS = 2;

    /**
     *{ @inheritdoc }
     */
    public function extractMetadata(Crawler $crawler)
    {

        $htmlTagsWithValidMetadata = array();

        $htmlTagsWithValidMetadata['title'] = $this->getTitleTagText($crawler);
        $htmlTagsWithValidMetadata['description'] = $this->getMetaDescriptionText($crawler);

        return $htmlTagsWithValidMetadata;
    }

    /**
     * @param Crawler $crawler
     * @return null|string
     */
    private function getTitleTagText(Crawler $crawler)
    {

        try {
            $title = $crawler->filterXPath('//title')->text();
        } catch (\InvalidArgumentException $e) {
            $title = null;
        }

        return '' !== trim($title) ? $title : null;
    }

    /**
     * @param Crawler $crawler
     * @return null|string
     */
    private function getMetaDescriptionText(Crawler $crawler)
    {

        try {
            $description = $crawler->filterXPath('//meta[@name="description"]')->attr('content');
        } catch (\InvalidArgumentException $e) {
            $description = null;
        }

        return '' !== trim($description) ? $description : null;
    }

    /**
     * Extracts tags form keywords
     */
    public function extractTags(Crawler $crawler)
    {

        try {
            $keywords = $crawler->filterXPath('//meta[@name="keywords"]')->attr('content');
        } catch (\InvalidArgumentException $e) {
            return array();
        }

        if ('' === trim($keywords)) {
            return array();
        }

        $keywords = explode(',', $keywords);

        foreach ($keywords as $keyword) {
            $scrapedTags[] = array('name' => trim(strtolower($keyword)));
        }

        $this->filterTags($scrapedTags);

        return $scrapedTags;
    }

    /**
     * @param $scrapedTags
     */
    private function filterTags(array &$scrapedTags)
    {

        foreach ($scrapedTags as $index => $tag) {
            if (null === $tag['name'] || '' === $tag['name'] || str_word_count($tag['name']) > self::MAX_WORDS) {
                unset($scrapedTags[$index]);
            }
        }
    }
}

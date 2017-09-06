<?php

namespace Tests\ApiConsumer\LinkProcessor\Processor\TwitterProcessor;

use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\Processor\TwitterProcessor\AbstractTwitterProcessor;
use ApiConsumer\LinkProcessor\Processor\TwitterProcessor\TwitterPicProcessor;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;
use Model\Link\Link;
use Tests\ApiConsumer\LinkProcessor\Processor\AbstractProcessorTest;

class TwitterPicProcessorTest extends AbstractProcessorTest
{
    /**
     * @var TwitterResourceOwner|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $resourceOwner;

    /**
     * @var TwitterUrlParser|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $parser;

    /**
     * @var TwitterPicProcessor
     */
    protected $processor;

    public function setUp()
    {
        $this->resourceOwner = $this->getMockBuilder('ApiConsumer\ResourceOwner\TwitterResourceOwner')
            ->disableOriginalConstructor()
            ->getMock();

        $this->parser = $this->getMockBuilder('ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser')
            ->getMock();

        $this->processor = new TwitterPicProcessor($this->resourceOwner, $this->parser, $this->brainBaseUrl . TwitterUrlParser::DEFAULT_IMAGE_PATH);
    }

    /**
     * @dataProvider getStatusForRequestItem
     */
    public function testRequestItem($url)
    {
        $this->setExpectedException('ApiConsumer\Exception\CannotProcessException', 'Twitter pic needs to be scraped');
        $link = new PreprocessedLink($url);
        $this->processor->getResponse($link);
    }

    /**
     * @dataProvider getResponseHydration
     */
    public function testHydrateLink($url, $expectedArray)
    {
        $link = new PreprocessedLink($url);
        $this->processor->hydrateLink($link, array());

        $this->assertEquals($expectedArray, $link->getFirstLink()->toArray(), 'Asserting correct hydrated link for ' . $url);
    }

    /**
     * @dataProvider getResponseTags
     */
    public function testAddTags($url, $response, $expectedTags)
    {
        $link = new PreprocessedLink($url);
        $this->processor->addTags($link, $response);

        $tags = $expectedTags;
        sort($tags);
        $resultTags = $link->getFirstLink()->getTags();
        sort($resultTags);
        $this->assertEquals($tags, $resultTags);
    }

    public function getBadUrls()
    {
        return array(
            array('this is not an url')
        );
    }

    public function getStatusForRequestItem()
    {
        return array(
            array(
                $this->getStatusUrl(),
            )
        );
    }

    public function getStatusForRequestItemWithEmbedded()
    {
        return array(
            array(
                $this->getStatusUrl(),
            )
        );
    }

    public function getResponseHydration()
    {
        $expected = new Link();
        return array(
            array(
                $this->getStatusUrl(),
                $expected->toArray(),
            )
        );
    }

    public function getResponseTags()
    {
        return array(
            array(
                $this->getStatusUrl(),
                $this->getStatusResponse(),
                $this->getStatusTags(),
            )
        );
    }

    public function getStatusUrl()
    {
        return 'https://twitter.com/yawmoght/status/782909345961050112';
    }

    public function getStatusResponse()
    {
        return array();
    }

    public function getStatusTags()
    {
        return array();
    }

}
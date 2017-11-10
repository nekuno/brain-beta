<?php

namespace Tests\ApiConsumer\LinkProcessor\Processor\TwitterProcessor;

use ApiConsumer\Exception\UrlNotValidException;
use ApiConsumer\Images\ProcessingImage;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\Processor\TwitterProcessor\AbstractTwitterProcessor;
use ApiConsumer\LinkProcessor\Processor\TwitterProcessor\TwitterProfileProcessor;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;
use Model\Link\Creator;
use Model\User\Token\TokensModel;
use Tests\ApiConsumer\LinkProcessor\Processor\AbstractProcessorTest;

class TwitterProfileProcessorTest extends AbstractProcessorTest
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
     * @var TwitterProfileProcessor
     */
    protected $processor;

    public function setUp()
    {
        $this->resourceOwner = $this->getMockBuilder('ApiConsumer\ResourceOwner\TwitterResourceOwner')
            ->disableOriginalConstructor()
            ->getMock();

        $this->parser = $this->getMockBuilder('ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser')
            ->getMock();

        $this->processor = new TwitterProfileProcessor($this->resourceOwner, $this->parser, $this->brainBaseUrl . TwitterUrlParser::DEFAULT_IMAGE_PATH);
    }

    /**
     * @dataProvider getBadUrls
     */
    public function testBadUrlRequestItem($url)
    {
        $this->setExpectedException('ApiConsumer\Exception\CannotProcessException', 'Could not process url ' . $url);

        $this->parser->expects($this->once())
            ->method('getProfileId')
            ->will($this->throwException(new UrlNotValidException($url)));

        $link = new PreprocessedLink($url);
        $this->processor->getResponse($link);
    }

    /**
     * @dataProvider getProfileForRequestItem
     */
    public function testRequestItem($url, $id, $profile)
    {
        $this->parser->expects($this->once())
            ->method('getProfileId')
            ->will($this->returnValue($id));

        $this->resourceOwner->expects($this->once())
            ->method('lookupUsersBy')
            ->will($this->returnValue($profile));

        $link = new PreprocessedLink($url);
        $response = $this->processor->getResponse($link);

        $this->assertEquals($response, $profile, 'Asserting correct response for ' . $url);
    }

    /**
     * @dataProvider getResponseHydration
     */
    public function testHydrateLink($url, $response, $linkArrays, $expectedArray, $expectedId)
    {
        $this->resourceOwner->expects($this->once())
            ->method('buildProfilesFromLookup')
            ->will($this->returnValue($linkArrays));

        $link = new PreprocessedLink($url);
        $this->processor->hydrateLink($link, $response);

        $this->assertEquals($expectedArray, $link->getFirstLink()->toArray(), 'Asserting correct hydrated link for ' . $url);
        $this->assertEquals($expectedId, $link->getResourceItemId(), 'Asserting correct resourceItemId while hydrating ' . $url);
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

    /**
     * @dataProvider getResponseImages
     * @param $expectedImages ProcessingImage[]
     */
    public function testGetImages($url, $response, $expectedImages)
    {
        $this->parser->expects($this->once())
            ->method('getSmallProfileUrl')
            ->will($this->returnValue($expectedImages[0]->getUrl()));

        $this->parser->expects($this->once())
            ->method('getMediumProfileUrl')
            ->will($this->returnValue($expectedImages[1]->getUrl()));

        $this->parser->expects($this->once())
            ->method('getOriginalProfileUrl')
            ->will($this->returnValue($expectedImages[2]->getUrl()));

        $link = new PreprocessedLink($url);
        $images = $this->processor->getImages($link, $response);

        $this->assertEquals($expectedImages, $images, 'Images gotten from response');
    }

    public function getBadUrls()
    {
        return array(
            array('this is not an url')
        );
    }

    public function getProfileForRequestItem()
    {
        return array(
            array(
                $this->getProfileUrl(),
                $this->getProfileId(),
                $this->getProfileResponse(),
            )
        );
    }

    public function getResponseHydration()
    {
        $expected = new Creator();
        $expected->setTitle('yawmoght');
        $expected->setDescription('Tool developer & data junkie');
        $expected->setThumbnail('http://pbs.twimg.com/profile_images/639462703858380800/ZxusSbUW.png');
        $expected->setUrl('https://twitter.com/yawmoght');
        $expected->setCreated(time() * 1000);
        $expected->addAdditionalLabels(AbstractTwitterProcessor::TWITTER_LABEL);
        $expected->setProcessed(true);

        return array(
            array(
                $this->getProfileUrl(),
                $this->getProfileItemResponse(),
                array($this->getProfileLink()),
                $expected->toArray(),
                34529134,
            )
        );
    }

    public function getResponseTags()
    {
        return array(
            array(
                $this->getProfileUrl(),
                $this->getProfileItemResponse(),
                $this->getProfileTags(),
            )
        );
    }

    public function getProfileResponse()
    {
        return array(
            $this->getProfileItemResponse(),
        );
    }

    public function getResponseImages()
    {
        return array(
            array(
                $this->getProfileUrl(),
                $this->getProfileItemResponse(),
                $this->getProcessingImages()
            )
        );
    }

    public function getProfileItemResponse()
    {
        return array(
            "id" => 34529134,
            "id_str" => "34529134",
            "name" => "yawmoght",
            "screen_name" => "yawmoght",
            "location" => "",
            "description" => "Tool developer & data junkie",
            "url" => null,
            "entities" => array(
                "description" => array(
                    "urls" => array()
                )
            ),
            "protected" => false,
            "followers_count" => 274,
            "friends_count" => 650,
            "listed_count" => 24,
            "created_at" => "Thu Apr 23 04:17:29 +0000 2009",
            "favourites_count" => 3370,
            "utc_offset" => 7200,
            "time_zone" => "Madrid",
            "geo_enabled" => true,
            "verified" => false,
            "statuses_count" => 2385,
            "lang" => "es",
            "status" => array(
                "created_at" => "Tue Oct 04 09:10:52 +0000 2016",
                "id" => 783232909993996300,
                "id_str" => "783232909993996288",
                "text" => "@Nurichigo Bueno, clases particulares suele haber, pero claro, son pasta.. Si quieres mandame una direccion de mail por DM y mando libros",
                "truncated" => false,
                "entities" => array(
                    "hashtags" => array(),
                    "symbols" => array(),
                    "user_mentions" => array(
                        array(
                            "screen_name" => "Nurichigo",
                            "name" => "I am Wenceslao",
                            "id" => 119144541,
                            "id_str" => "119144541",
                            "indices" => array(
                                0,
                                10
                            )
                        )
                    ),
                    "urls" => array()
                ),
                "source" => "<a href='http://twitter.com' rel='nofollow'>Twitter Web Client</a>",
                "in_reply_to_status_id" => 783231485247651800,
                "in_reply_to_status_id_str" => "783231485247651840",
                "in_reply_to_user_id" => 119144541,
                "in_reply_to_user_id_str" => "119144541",
                "in_reply_to_screen_name" => "Nurichigo",
                "geo" => null,
                "coordinates" => null,
                "place" => null,
                "contributors" => null,
                "is_quote_status" => false,
                "retweet_count" => 0,
                "favorite_count" => 0,
                "favorited" => false,
                "retweeted" => false,
                "lang" => "es"
            ),
            "contributors_enabled" => false,
            "is_translator" => false,
            "is_translation_enabled" => false,
            "profile_background_color" => "C0DEED",
            "profile_background_image_url" => "http://pbs.twimg.com/profile_background_images/364366364/Tardis_background.JPG",
            "profile_background_image_url_https" => "https://pbs.twimg.com/profile_background_images/364366364/Tardis_background.JPG",
            "profile_background_tile" => true,
            "profile_image_url" => "http://pbs.twimg.com/profile_images/639462703858380800/ZxusSbUW_normal.png",
            "profile_image_url_https" => "https://pbs.twimg.com/profile_images/639462703858380800/ZxusSbUW_normal.png",
            "profile_banner_url" => "https://pbs.twimg.com/profile_banners/34529134/1452345615",
            "profile_link_color" => "0084B4",
            "profile_sidebar_border_color" => "FFFFFF",
            "profile_sidebar_fill_color" => "DDEEF6",
            "profile_text_color" => "333333",
            "profile_use_background_image" => true,
            "has_extended_profile" => false,
            "default_profile" => false,
            "default_profile_image" => false,
            "following" => false,
            "follow_request_sent" => false,
            "notifications" => false
        );
    }

    public function getProfileLink()
    {
        return array(
            'description' => 'Tool developer & data junkie',
            'url' => 'https://twitter.com/yawmoght',
            'thumbnail' => "http://pbs.twimg.com/profile_images/639462703858380800/ZxusSbUW.png",
            'additionalLabels' => array('LinkTwitter', 'Creator'),
            'resource' => TokensModel::TWITTER,
            'timestamp' => 1000 * time(),
            'processed' => 1,
            'title' => 'yawmoght',
        );
    }

    public function getNewUrl()
    {
        return 'http://www.nature.com/news/democratic-databases-science-on-github-1.20719';
    }

    public function getProfileUrl()
    {
        return 'https://twitter.com/yawmoght';
    }

    public function getProfileId()
    {
        return array('screen_name' => 'yawmoght');
    }

    public function getProfileTags()
    {
        return array();
    }

    public function getProcessingImages()
    {
        $smallProcessingImage = new ProcessingImage('https://pbs.twimg.com/profile_images/639462703858380800/ZxusSbUW_normal.png');
        $smallProcessingImage->setHeight(48);
        $smallProcessingImage->setWidth(48);
        $smallProcessingImage->setLabel(ProcessingImage::LABEL_SMALL);

        $mediumProcessingImage = new ProcessingImage('https://pbs.twimg.com/profile_images/639462703858380800/ZxusSbUW_bigger.png');
        $mediumProcessingImage->setHeight(73);
        $mediumProcessingImage->setWidth(73);
        $mediumProcessingImage->setLabel(ProcessingImage::LABEL_MEDIUM);

        $largeProcessingImage = new ProcessingImage('https://pbs.twimg.com/profile_images/639462703858380800/ZxusSbUW.png');
        $largeProcessingImage->setLabel(ProcessingImage::LABEL_LARGE);

        return array($smallProcessingImage, $mediumProcessingImage, $largeProcessingImage);
    }

}
<?php

namespace Tests\ApiConsumer\LinkProcessor\Processor\TwitterProcessor;

use ApiConsumer\Exception\UrlNotValidException;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use ApiConsumer\LinkProcessor\Processor\TwitterProcessor\TwitterIntentProcessor;
use ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser;
use ApiConsumer\ResourceOwner\TwitterResourceOwner;
use Model\User\Token\TokensModel;

class TwitterIntentProcessorTest extends \PHPUnit_Framework_TestCase
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
     * @var TwitterIntentProcessor
     */
    protected $processor;

    public function setUp()
    {
        $this->resourceOwner = $this->getMockBuilder('ApiConsumer\ResourceOwner\TwitterResourceOwner')
            ->disableOriginalConstructor()
            ->getMock();

        $this->parser = $this->getMockBuilder('ApiConsumer\LinkProcessor\UrlParser\TwitterUrlParser')
            ->getMock();

        $this->processor = new TwitterIntentProcessor($this->resourceOwner, $this->parser);
    }

    /**
     * @dataProvider getBadUrls
     */
    public function testBadUrlRequestItem($url)
    {
        $this->setExpectedException('ApiConsumer\Exception\CannotProcessException', 'Getting userId from a twitter intent url');

        $this->parser->expects($this->once())
            ->method('getProfileId')
            ->will($this->throwException(new UrlNotValidException($url)));

        $link = new PreprocessedLink($url);
        $this->processor->getResponse($link);
    }

    /**
     * @dataProvider getProfileForRequestItem
     */
    public function testRequestItem($url, $id, $profiles, $link)
    {
        $this->parser->expects($this->once())
            ->method('getProfileId')
            ->will($this->returnValue($id));

        $this->resourceOwner->expects($this->once())
            ->method('lookupUsersBy')
            ->will($this->returnValue($profiles));

        $this->resourceOwner->expects($this->once())
            ->method('buildProfileFromLookup')
            ->will($this->returnValue($link));

        $this->setExpectedException('ApiConsumer\Exception\UrlChangedException', 'Url changed from https://twitter.com/intent/user?screen_name=yawmoght to https://twitter.com/yawmoght');

        $link = new PreprocessedLink($url);
        $this->processor->getResponse($link);
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
                $this->getProfileLink(),
            )
        );
    }

    public function getResponseHydration()
    {
        return array(
            array(
                $this->getProfileUrl(),
                $this->getProfileItemResponse(),
                array(
                    'title' => null,
                    'description' => 'Tool developer & data junkie',
                    'thumbnail' => null,
                    'url' => null,
                    'id' => 34529134,
                    'tags' => array(),
                    'created' => null,
                    'processed' => true,
                    'language' => null,
                    'synonymous' => array(),
                    'imageProcessed' => null,
                )
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
            'additionalLabels' => array('Creator'),
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
        return 'https://twitter.com/intent/user?screen_name=yawmoght';
    }

    public function getProfileId()
    {
        return array('user_id' => '34529134');
    }

    public function getProfileTags()
    {
        return array();
    }

}
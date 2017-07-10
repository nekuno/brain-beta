<?php

namespace ApiConsumer\Images;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Message\ResponseInterface;

class ImageAnalyzer
{
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function selectImage(array $imageUrls)
    {
        $selectedImage = null;
        foreach ($imageUrls as $imageUrl) {
            $image = $this->buildResponse($imageUrl);
            if ($image->isValid()) {
                $selectedImage = $image;
                break;
            }
        }

        return null == $selectedImage ? null : $selectedImage->getUrl();
    }

    private function isValidImage($url)
    {
        if (empty($url)) {
            return false;
        }

        $image = $this->buildResponse($url);

        return $image->isValid() ? $image : null;
    }

    public function filterToReprocess(array $links)
    {
        $toReprocess = array();
        foreach ($links as $key => $link) {
            if ($this->needsReprocessing($link)) {
                $toReprocess[] = $link;
            }
        }

        return $toReprocess;
    }

    private function needsReprocessing(array $link)
    {
        $isOld = $this->isImageOld($link);
        $isInvalid = !$this->isValidThumbnail($link);

        return $isOld || $isInvalid;
    }

    private function isImageOld(array $link)
    {
        $timeToReprocess = 1000 * 3600 * 24 * 7; //1 week in milliseconds

        //we save timestamps in neo4j as milliseconds
        $imageTimestamp = isset($link['imageProcessed']) ? $link['imageProcessed'] : 1;
        $nowTimestamp = (new \DateTime())->getTimestamp() * 1000;

        return $imageTimestamp < ($nowTimestamp - $timeToReprocess);
    }

    private function isValidThumbnail($link)
    {
        $thumbnailUrl = $this->getThumbnailFromLink($link);

        return $this->isValidImage($thumbnailUrl);
    }

    //this logic could go in Link->getImageUrl()
    private function getThumbnailFromLink($link)
    {
        return isset($link['additionalLabels']) && in_array('Image', array($link['additionalLabels'])) ? $link['url']
            : isset($link['thumbnail']) ? $link['thumbnail'] : null;
    }

    public function buildResponse($imageUrl)
    {
        try {
            $head = $this->client->head($imageUrl);
        } catch (\Exception $e) {
            $head = new Response(404);
        }

        $length = $this->getLength($head, $imageUrl);

        $response = new ImageResponse($imageUrl, $head->getStatusCode(), $head->getHeader('Content-Type'), $length);

        return $response;
    }

    private function getLength(ResponseInterface $response, $imageUrl)
    {
        $length = !empty($response->getHeader('Content-Length')) ? $response->getHeader('Content-Length') : null;

        $length = $length ?: ($response->getHeader('Accept-Ranges') ? $this->getPartialImage($imageUrl) : null);

        return null !== $length ? $length : ImageResponse::MAX_SIZE + 1;
    }

    //http://stackoverflow.com/questions/2032924/how-to-partially-download-a-remote-file-with-curl
    private function getPartialImage($imageUrl)
    {
        $maxSize = ImageResponse::MAX_SIZE;
        $datadump = null;
        $writefn = function ($ch, $chunk) use ($maxSize, &$datadump) {
            static $data = '';

            $len = strlen($data) + strlen($chunk);
            if ($len >= $maxSize) {
                $data .= substr($chunk, 0, $maxSize - strlen($data));
                $datadump = $data;

                return -1;
            }
            $data .= $chunk;

            return strlen($chunk);
        };

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $imageUrl);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        //curl_setopt($ch, CURLOPT_RANGE, '0-1000'); //not honored by many sites, maybe just remove it altogether.
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, $writefn);
        $data = curl_exec($ch);
        curl_close($ch);

        $length = strlen($datadump);

        return $length < $maxSize ? $length : null;
    }
}
<?php

namespace Controller;

use ApiConsumer\Images\ImageAnalyzer;
use ApiConsumer\LinkProcessor\PreprocessedLink;
use Model\Link\Link;
use Model\Content\Interest;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

class LinkController
{
    /**
     * @param Request $request
     * @param Application $app
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Exception
     */
    public function checkImagesAction(Request $request, Application $app)
    {
        $data = $request->request->all();
        $urls = $data['urls'];

        $linkModel = $app['links.model'];
        $processorService = $app['api_consumer.processor'];
        /** @var ImageAnalyzer $imageAnalyzer */
        $imageAnalyzer = $app['api_consumer.link_processor.image_analyzer'];

        $links = $linkModel->findLinksByUrls($urls);
        $linksToReprocess = $imageAnalyzer->filterToReprocess($links);

        $preprocessedLinks = array();
        foreach ($linksToReprocess as $link) {
            $preprocessedLink = new PreprocessedLink($link['url']);
            $preprocessedLink->setFirstLink(Link::buildFromArray($link));
            $preprocessedLinks[] = $preprocessedLink;
        }
        $reprocessedLinks = $processorService->reprocess($preprocessedLinks);

        $interests = array();
        foreach ($reprocessedLinks as $reprocessedLink) {
            $interests[] = Interest::buildFromLinkArray($reprocessedLink);
        }

        return $app->json($interests);
    }
}
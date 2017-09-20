<?php

namespace Paginator;

use Model\Exception\ValidationException;
use Model\User\Recommendation\ContentRecommendation;
use Model\User\Recommendation\UserRecommendation;
use Symfony\Component\HttpFoundation\Request;

class ContentPaginator extends Paginator
{
    /**
     * @param array $filters
     * @param PaginatedInterface $paginated Slice returns array (items, newForeign)
     * @param Request $request
     * @return array
     */
    public function paginate(array $filters, PaginatedInterface $paginated, Request $request)
    {
        $limit = $request->get('limit', $this->getDefaultLimit());
        $limit = min($limit, $this->getMaxLimit());

        $offset = $request->get('offset', 0);
        $locale = $request->get('locale', null);
        $filters['locale'] = $locale;

        if (!$paginated->validateFilters($filters)) {
            throw new ValidationException(array(), sprintf('Invalid filters in "%s"', get_class($paginated)));
        }

        $slice = $paginated->slice($filters, $offset, $limit);
        $total = $paginated->countTotal($filters);


        $noCommonObjectives = 0;
        if (isset($filters['noCommonObjectives'])) {
            $noCommonObjectives = $filters['noCommonObjectives'];
        }

        $newNoCommonObjectives = isset($slice['newNoCommonObjectives']) ? $slice['newNoCommonObjectives'] : $noCommonObjectives;

        $foreign = 0;
        if (isset($filters['foreign'])) {
            $foreign = $filters['foreign'];
        }

        $newForeign = isset($slice['newForeign']) ? $slice['newForeign'] : $foreign;

        $ignored = 0;
        if (isset($filters['ignored'])) {
            $ignored = $filters['ignored'];
        }

        $newIgnored = isset($slice['newIgnored']) ? $slice['newIgnored'] : $ignored;

        $prevLink = $this->createContentPrevLink($request, $offset, $limit, $noCommonObjectives, $newNoCommonObjectives, $foreign, $newForeign, $newIgnored);
        if (count($slice['items']) < $limit) {
            $nextLink = null;
        } else {
            $nextLink = $this->createContentNextLink($request, $offset, $limit, $total, $noCommonObjectives, $newNoCommonObjectives, $foreign, $newForeign, $ignored, $newIgnored);
        }
        $pagination = array();
        $pagination['total'] = $total;
        $pagination['offset'] = $offset;
        $pagination['limit'] = $limit;
        $pagination['prevLink'] = $prevLink;
        $pagination['nextLink'] = $nextLink;

        $result = array();
        $result['pagination'] = $pagination;
        $result['items'] = $slice['items'];

        return $result;
    }

    /**
     * @param Request $request
     * @param $offset
     * @param $limit
     * @param $noCommonObjectives
     * @param $noCommonObjectivesContent
     * @param $foreign
     * @param $foreignContent
     * @param $ignored
     * @return string
     */
    protected function createContentPrevLink(Request $request, $offset, $limit, $noCommonObjectives, $noCommonObjectivesContent, $foreign, $foreignContent, $ignored)
    {
        $parentPrev = parent::createPrevLink($request, $offset, $limit);
        $prevLink = $this->addNoCommonObjectives($parentPrev, $noCommonObjectives, false, $noCommonObjectivesContent);
        $prevLink = $this->addForeign($prevLink, $foreign, false, $foreignContent);

        return $this->addIgnored($prevLink, $ignored, false);
    }

    /**
     * @param Request $request
     * @param $offset
     * @param $limit
     * @param $total
     * @param $noCommonObjectives
     * @param $noCommonObjectivesContent
     * @param $foreign
     * @param $foreignContent
     * @param $ignored
     * @param $newIgnored
     * @return string
     */
    protected function createContentNextLink(Request $request, $offset, $limit, $total, $noCommonObjectives, $noCommonObjectivesContent, $foreign, $foreignContent, $ignored, $newIgnored)
    {
        $parentNext = parent::createNextLink($request, $offset, $limit, $total);
        $nextLink = $this->addNoCommonObjectives($parentNext, $noCommonObjectives, true, $noCommonObjectivesContent);
        $nextLink = $this->addForeign($nextLink, $foreign, true, $foreignContent - $foreign);

        return $this->addIgnored($nextLink, $ignored, true, $newIgnored - $ignored);
    }

    /**
     * @param $url
     * @param $noCommonObjectives
     * @param bool $next
     * @param int $newNoCommonObjectives
     * @return string
     */
    protected function addNoCommonObjectives($url, $noCommonObjectives, $next = false, $newNoCommonObjectives = 0)
    {
        if (!$url || $newNoCommonObjectives === 0) {
            return $url;
        }

        if ($next && $noCommonObjectives < 0) {
            return null; //database completely searched
        }

        $url_parts = parse_url($url);
        parse_str($url_parts['query'], $params);

        $params['offset'] = isset($params['offset']) ? $params['offset'] : 0;
        if ($next) {
            $params['offset'] -= $newNoCommonObjectives;
            $params['noCommonObjectives'] = $noCommonObjectives + $newNoCommonObjectives;
        } else {
            if (isset($params['noCommonObjectives']) && $params['noCommonObjectives']) {
                $params['noCommonObjectives'] = $noCommonObjectives;
                $params['offset'] += $params['limit'];
            }
        }

        $url_parts['query'] = http_build_query($params);

        return http_build_url($url_parts);
    }

    /**
     * @param $url
     * @param $foreign
     * @param bool $next
     * @param int $newForeign
     * @return string
     */
    protected function addForeign($url, $foreign, $next = false, $newForeign = 0)
    {
        if (!$url || $newForeign === 0) {
            return $url;
        }

        if ($next && $foreign < 0) {
            return null; //database completely searched
        }

        $url_parts = parse_url($url);
        parse_str($url_parts['query'], $params);

        $params['offset'] = isset($params['offset']) ? $params['offset'] : 0;
        if ($next) {
            $params['offset'] -= $newForeign;
            $params['foreign'] = $foreign + $newForeign;
        } else {
            if (isset($params['foreign']) && $params['foreign']) {
                $params['foreign'] = $foreign;
                $params['offset'] += $params['limit'];
            }
        }

        $url_parts['query'] = http_build_query($params);

        return http_build_url($url_parts);
    }

    /**
     * @param $url
     * @param $ignored
     * @param bool $next
     * @param $newIgnored
     * @return string
     */
    protected function addIgnored($url, $ignored, $next = false, $newIgnored = 0)
    {
        if (!$url || $newIgnored === 0) {
            return $url;
        }

        $url_parts = parse_url($url);
        parse_str($url_parts['query'], $params);

        $params['offset'] = isset($params['offset']) ? $params['offset'] : 0;
        if ($next) {
            $params['offset'] = ($params['offset'] - $newIgnored) >= 0 ? $params['offset'] - $newIgnored : $params['offset'];
            $params['ignored'] = $ignored + $newIgnored;

        } else {
            if (isset($params['ignored']) && $params['ignored']) {
                $params['ignored'] = $ignored;
                $params['offset'] += $params['limit'];
            }
        }

        $url_parts['query'] = http_build_query($params);

        return http_build_url($url_parts);
    }

}
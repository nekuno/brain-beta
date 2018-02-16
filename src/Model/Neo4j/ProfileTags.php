<?php

namespace Model\Neo4j;

use Model\LanguageText\LanguageTextManager;
use Model\User\ProfileTagModel;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

class ProfileTags implements LoggerAwareInterface
{
    protected $profileTagModel;
    protected $languageTextManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OptionsResult
     */
    protected $result;

    public function __construct(ProfileTagModel $profileTagModel, LanguageTextManager $languageTextManager)
    {

        $this->profileTagModel = $profileTagModel;
        $this->languageTextManager = $languageTextManager;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return OptionsResult
     */
    public function load()
    {

        $this->result = new OptionsResult();

        $tags = array(
            'Sport' => array(
                array(
                    'locales' => array(
                        'es' => 'Fútbol',
                        'en' => 'Football'
                    ),
                ),
                array(
                    'locales' => array(
                        'es' => 'Basketball',
                        'en' => 'Baloncesto'
                    ),
                ),
            ),
            'Allergy' => array(
                array(
                    'locales' => array(
                        'es' => 'Polen',
                        'en' => 'Pollen'
                    ),
                ),
            ),
        );

        foreach ($tags as $label => $values) {

            $this->result->incrementTotal();

            foreach ($values as $value) {

                $tagId = $this->findTagId($label, $value);
                if (!$tagId)
                {
                    $googleGraphId = isset($value['googleGraphId']) ? $value['googleGraphId'] : null;
                    $tag = $this->profileTagModel->mergeTag($label, $googleGraphId);
                    $tagId = $tag['id'];
                    $this->result->incrementCreated();
                }

                foreach ($value['locales'] as $locale=>$text)
                {
                    $this->languageTextManager->merge($tagId, $locale, $text);
                    $this->result->incrementUpdated();
                }
            }
        }

        return $this->result;
    }

    protected function findTagId($label, array $value)
    {
        $locales = $value['locales'];

        $tagId = null;
        foreach ($locales as $locale=>$text)
        {
            $tagId = $this->languageTextManager->findNodeWithText($label, $locale, $text);
            if ($tagId)
            {
                break;
            }
        }

        return $tagId;
    }


} 
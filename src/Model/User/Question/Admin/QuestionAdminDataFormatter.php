<?php

namespace Model\User\Question\Admin;

class QuestionAdminDataFormatter
{
    public function getDataFromAdmin(array $data)
    {
        return array(
            'answerTexts' => $this->getAnswersTexts($data),
            'questionTexts' => $this->getQuestionTexts($data),
        );
    }

    protected function getAnswersTexts(array $data)
    {
        $answers = array();
        foreach ($data as $key => $value) {
            $hasAnswerText = strpos($key, 'answer') !== false;
            $isNotId = strpos($key, 'Id') === false;
            if ($hasAnswerText && $isNotId && !empty($value)) {
                $id = $this->extractAnswerId($key);
                $locale = $this->extractLocale($key);
                $answers[$id][$locale] = $value;
            }
        }

        return $answers;
    }

    protected function getQuestionTexts(array $data)
    {
        $texts = array();
        foreach ($data as $key => $value) {
            $isQuestionText = strpos($key, 'text') === 0;
            if ($isQuestionText) {
                $locale = $this->extractLocale($key);
                $texts[$locale] = $value;
            }
        }

        return $texts;
    }

    //To change with more locales
    protected function extractLocale($text)
    {
        if (strpos($text, 'Es') !== false) {
            return 'es';
        }

        if (strpos($text, 'En') !== false) {
            return 'en';
        }

        return null;
    }

    protected function extractAnswerId($text)
    {
        $prefixSize = strlen('answer');
        $number = substr($text, $prefixSize, 1);

        return (integer)$number;
    }
}
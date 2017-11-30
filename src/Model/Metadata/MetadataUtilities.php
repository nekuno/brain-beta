<?php

namespace Model\Metadata;

class MetadataUtilities
{
    public function labelToType($labelName)
    {
        return lcfirst($labelName);
    }

    public function typeToLabel($typeName)
    {
        return ucfirst($typeName);
    }

    public function getLocaleString($labelField, $locale)
    {
        if (null === $labelField || !is_array($labelField) || !isset($labelField[$locale])) {
            $errorMessage = sprintf('Locale %s not present for metadata', $locale);
            throw new \InvalidArgumentException($errorMessage);
        }

        return $labelField[$locale];
    }

    public function getLanguageFromTag($tag)
    {
        return $this->translateTypicalLanguage($this->formatLanguage($tag));
    }

    //TODO: Refactor this translation functions
    public function translateTypicalLanguage($language)
    {
        switch ($language) {
            case 'Español':
                return 'Spanish';
            case 'Castellano':
                return 'Spanish';
            case 'Inglés':
                return 'English';
            case 'Ingles':
                return 'English';
            case 'Francés':
                return 'French';
            case 'Frances':
                return 'French';
            case 'Alemán':
                return 'German';
            case 'Aleman':
                return 'German';
            case 'Portugués':
                return 'Portuguese';
            case 'Portugues':
                return 'Portuguese';
            case 'Italiano':
                return 'Italian';
            case 'Chino':
                return 'Chinese';
            case 'Japonés':
                return 'Japanese';
            case 'Japones':
                return 'Japanese';
            case 'Ruso':
                return 'Russian';
            case 'Árabe':
                return 'Arabic';
            case 'Arabe':
                return 'Arabic';
            default:
                return $language;
        }
    }

    public function translateLanguageToLocale($language, $locale)
    {
        if ($locale === 'en') {
            return $language;
        }
        if ($locale === 'es') {
            switch ($language) {
                case 'Spanish':
                    return 'Español';
                case 'English':
                    return 'Inglés';
                case 'French':
                    return 'Francés';
                case 'German':
                    return 'Alemán';
                case 'Portuguese':
                    return 'Portugués';
                case 'Italian':
                    return 'Italiano';
                case 'Chinese':
                    return 'Chino';
                case 'Japanese':
                    return 'Japonés';
                case 'Russian':
                    return 'Ruso';
                case 'Arabic':
                    return 'Árabe';
            }
        }

        return $language;
    }

    protected function formatLanguage($typeName)
    {
        $firstCharacter = mb_strtoupper(mb_substr($typeName, 0, 1, 'UTF-8'), 'UTF-8');
        $restString = mb_strtolower(mb_substr($typeName, 1, null, 'UTF-8'), 'UTF-8');

        return $firstCharacter . $restString;
    }

    public function getBirthdayRangeFromAgeRange($min = null, $max = null, $nowDate = null)
    {
        $return = array('max' => null, 'min' => null);
        if ($min) {
            $now = new \DateTime($nowDate);
            $maxBirthday = $now->modify('-' . ($min) . ' years')->format('Y-m-d');
            $return ['max'] = $maxBirthday;
        }
        if ($max) {
            $now = new \DateTime($nowDate);
            $minBirthday = $now->modify('-' . ($max + 1) . ' years')->modify('+ 1 days')->format('Y-m-d');
            $return['min'] = $minBirthday;
        }

        return $return;
    }

    /*
     * Please don't believe in this crap
     */
    public function getZodiacSignFromDate($date)
    {

        $sign = null;
        $birthday = \DateTime::createFromFormat('Y-m-d', $date);

        $zodiac[356] = 'capricorn';
        $zodiac[326] = 'sagittarius';
        $zodiac[296] = 'scorpio';
        $zodiac[266] = 'libra';
        $zodiac[235] = 'virgo';
        $zodiac[203] = 'leo';
        $zodiac[172] = 'cancer';
        $zodiac[140] = 'gemini';
        $zodiac[111] = 'taurus';
        $zodiac[78] = 'aries';
        $zodiac[51] = 'pisces';
        $zodiac[20] = 'aquarius';
        $zodiac[0] = 'capricorn';

        if (!$date) {
            return $sign;
        }

        $dayOfTheYear = $birthday->format('z');
        $isLeapYear = $birthday->format('L');
        if ($isLeapYear && ($dayOfTheYear > 59)) {
            $dayOfTheYear = $dayOfTheYear - 1;
        }

        foreach ($zodiac as $day => $sign) {
            if ($dayOfTheYear > $day) {
                break;
            }
        }

        return $sign;
    }
}
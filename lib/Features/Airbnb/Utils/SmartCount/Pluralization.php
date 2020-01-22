<?php

namespace Features\Airbnb\Utils\SmartCount;

class Pluralization
{
    /**
     * @param string $lang
     *
     * @return int
     */
    public static function getCountFromLang($lang){
        switch ($lang){
            case "az-AZ":
            case "zh-CN":
            case "zh-TW":
            case "zh-HK":
            case "id-ID":
            case "ja-JP":
            case "ko-KR":
            case "ms-MY":
            case "th-TH":
            case "tr-TR":
            case "vi-VN":
            case "ka-GE":
                return 1;

            case "sq-AL":
            case "bg-BG":
            case "ca-ES":
            case "da-DK":
            case "nl-NL":
            case "en-GB":
            case "et-EE":
            case "fi-FI":
            case "de-DE":
            case "el-GR":
            case "he-IL":
            case "hu-HU":
            case "it-IT":
            case "nb-NO":
            case "pt-PT":
            case "es-ES":
            case "sw-KE":
            case "sv-SE":
            case "hy-AM":
            case "pt-BR":
            case "fr-FR":
            case "hi-IN":
            case "is-IS":
            case "mk-MK":
            case "tl-PH":
                return 2;

            case "cs-CZ":
            case "sk-SK":
            case "bs-BA":
            case "hr-HR":
            case "sr-ME":
            case "sr-Latn-RS":
            case "uk-UA":
            case "ru-RU":
            case "pl-PL":
            case "lv-LV":
            case "lt-LT":
            case "ro-RO":
                return 3;

            case "mt-MT":
            case "sl-SI":
                return 4;

            case "ga-IE":
                return 5;

            case "ar-SA":
                return 6;
        }

        return 0;
    }
}

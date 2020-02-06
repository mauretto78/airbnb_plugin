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
            case "id-ID":
            case "ja-JP":
            case "ko-KR":
            case "ms-MY":
            case "th-TH":
            case "tr-TR":
            case "vi-VN":
            case "zh-CN":
            case "zh-HK":
            case "zh-TW":
                return 1;

            case "bg-BG":
            case "ca-ES":
            case "da-DK":
            case "de-DE":
            case "el-GR":
            case "en-GB":
            case "es-ES":
            case "es-AR":
            case "es-CO":
            case "es-419":
            case "es-MX":
            case "es-US":
            case "et-EE":
            case "fi-FI":
            case "fr-FR":
            case "he-IL":
            case "hi-IN":
            case "hu-HU":
            case "hy-AM":
            case "ka-GE":
            case "is-IS":
            case "it-IT":
            case "mk-MK":
            case "nb-NO":
            case "nl-NL":
            case "pt-BR":
            case "pt-PT":
            case "sq-AL":
            case "sv-SE":
            case "sw-KE":
            case "tl-PH":
            case "xh-ZA":
                return 2;

            case "bs-BA":
            case "cs-CZ":
            case "hr-HR":
            case "lt-LT":
            case "lv-LV":
            case "pl-PL":
            case "ro-RO":
            case "ru-RU":
            case "sk-SK":
            case "sr-Latn-RS":
            case "sr-ME":
            case "uk-UA":
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

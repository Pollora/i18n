<?php

declare(strict_types=1);

namespace Pollora\I18n\Tooling;

/**
 * The Plural-Forms header of the languages WordPress translates most, as translate.wordpress.org writes it.
 * A language that is not listed gets none: the translator states it, and two forms are assumed until then.
 */
final class PluralForms
{
    public const string ENGLISH = 'nplurals=2; plural=(n != 1);';

    private const string ONE_FORM = 'nplurals=1; plural=0;';

    private const string SECOND_FROM_TWO = 'nplurals=2; plural=(n > 1);';

    private const string EAST_SLAVIC = 'nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);';

    private const string POLISH = 'nplurals=3; plural=(n==1 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);';

    private const string CZECH = 'nplurals=3; plural=(n==1) ? 0 : (n>=2 && n<=4) ? 1 : 2;';

    private const string ARABIC = 'nplurals=6; plural=(n==0 ? 0 : n==1 ? 1 : n==2 ? 2 : n%100>=3 && n%100<=10 ? 3 : n%100>=11 ? 4 : 5);';

    /** @var array<string, string> By language code, lower case */
    private const array BY_LANGUAGE = [
        'ar' => self::ARABIC,
        'bg' => self::ENGLISH,
        'bs' => self::EAST_SLAVIC,
        'ca' => self::ENGLISH,
        'cs' => self::CZECH,
        'da' => self::ENGLISH,
        'de' => self::ENGLISH,
        'el' => self::ENGLISH,
        'en' => self::ENGLISH,
        'es' => self::ENGLISH,
        'et' => self::ENGLISH,
        'eu' => self::ENGLISH,
        'fi' => self::ENGLISH,
        'fr' => self::SECOND_FROM_TWO,
        'gl' => self::ENGLISH,
        'hr' => self::EAST_SLAVIC,
        'hu' => self::ENGLISH,
        'id' => self::ONE_FORM,
        'it' => self::ENGLISH,
        'ja' => self::ONE_FORM,
        'ko' => self::ONE_FORM,
        'nb' => self::ENGLISH,
        'nl' => self::ENGLISH,
        'nn' => self::ENGLISH,
        'pl' => self::POLISH,
        'pt' => self::ENGLISH,
        'ru' => self::EAST_SLAVIC,
        'sk' => self::CZECH,
        'sr' => self::EAST_SLAVIC,
        'sv' => self::ENGLISH,
        'th' => self::ONE_FORM,
        'tr' => self::SECOND_FROM_TWO,
        'uk' => self::EAST_SLAVIC,
        'vi' => self::ONE_FORM,
        'zh' => self::ONE_FORM,
    ];

    /**
     * @param  string  $locale  A WordPress locale such as "fr_FR" or "pt_BR"
     */
    public static function forLocale(string $locale): ?string
    {
        if ($locale === 'pt_BR') {
            return self::SECOND_FROM_TWO;
        }

        return self::BY_LANGUAGE[strtolower(explode('_', $locale, 2)[0])] ?? null;
    }
}

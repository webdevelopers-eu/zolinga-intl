<?php

declare(strict_types=1);

namespace Zolinga\Intl\Types;

/**
 * This is a complete list of all country ISO codes as described in the ISO 3166 international standard.
 * 
 * Supports constants CountriesEnum::EU and CountriesEnum::EFTA.
 * 
 * 
 * @see https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
 * @see https://www.iban.com/country-codes
 * 
 * @author Daniel Sevcik <danny@zolinga.net>
 * @since 2024-06-11
 */
enum CountryEnum: int
{
    case AD = 20; // AND Andorra
    case AE = 784; // ARE United Arab Emirates (the)
    case AF = 4; // AFG Afghanistan
    case AG = 28; // ATG Antigua and Barbuda
    case AI = 660; // AIA Anguilla
    case AL = 8; // ALB Albania
    case AM = 51; // ARM Armenia
    case AO = 24; // AGO Angola
    case AQ = 10; // ATA Antarctica
    case AR = 32; // ARG Argentina
    case AS = 16; // ASM American Samoa
    case AT = 40; // AUT Austria
    case AU = 36; // AUS Australia
    case AW = 533; // ABW Aruba
    case AX = 248; // ALA Åland Islands
    case AZ = 31; // AZE Azerbaijan
    case BA = 70; // BIH Bosnia and Herzegovina
    case BB = 52; // BRB Barbados
    case BD = 50; // BGD Bangladesh
    case BE = 56; // BEL Belgium
    case BF = 854; // BFA Burkina Faso
    case BG = 100; // BGR Bulgaria
    case BH = 48; // BHR Bahrain
    case BI = 108; // BDI Burundi
    case BJ = 204; // BEN Benin
    case BL = 652; // BLM Saint Barthélemy
    case BM = 60; // BMU Bermuda
    case BN = 96; // BRN Brunei Darussalam
    case BO = 68; // BOL Bolivia (Plurinational State of)
    case BQ = 535; // BES Bonaire, Sint Eustatius and Saba
    case BR = 76; // BRA Brazil
    case BS = 44; // BHS Bahamas (the)
    case BT = 64; // BTN Bhutan
    case BV = 74; // BVT Bouvet Island
    case BW = 72; // BWA Botswana
    case BY = 112; // BLR Belarus
    case BZ = 84; // BLZ Belize
    case CA = 124; // CAN Canada
    case CC = 166; // CCK Cocos (Keeling) Islands (the)
    case CD = 180; // COD Congo (the Democratic Republic of the)
    case CF = 140; // CAF Central African Republic (the)
    case CG = 178; // COG Congo (the)
    case CH = 756; // CHE Switzerland
    case CI = 384; // CIV Côte d'Ivoire
    case CK = 184; // COK Cook Islands (the)
    case CL = 152; // CHL Chile
    case CM = 120; // CMR Cameroon
    case CN = 156; // CHN China
    case CO = 170; // COL Colombia
    case CR = 188; // CRI Costa Rica
    case CU = 192; // CUB Cuba
    case CV = 132; // CPV Cabo Verde
    case CW = 531; // CUW Curaçao
    case CX = 162; // CXR Christmas Island
    case CY = 196; // CYP Cyprus
    case CZ = 203; // CZE Czechia
    case DE = 276; // DEU Germany
    case DJ = 262; // DJI Djibouti
    case DK = 208; // DNK Denmark
    case DM = 212; // DMA Dominica
    case DO = 214; // DOM Dominican Republic (the)
    case DZ = 12; // DZA Algeria
    case EC = 218; // ECU Ecuador
    case EE = 233; // EST Estonia
    case EG = 818; // EGY Egypt
    case EH = 732; // ESH Western Sahara
    case ER = 232; // ERI Eritrea
    case ES = 724; // ESP Spain
    case ET = 231; // ETH Ethiopia
    case FI = 246; // FIN Finland
    case FJ = 242; // FJI Fiji
    case FK = 238; // FLK Falkland Islands (the) [Malvinas]
    case FM = 583; // FSM Micronesia (Federated States of)
    case FO = 234; // FRO Faroe Islands (the)
    case FR = 250; // FRA France
    case GA = 266; // GAB Gabon
    case GB = 826; // GBR United Kingdom of Great Britain and Northern Ireland (the)
    case GD = 308; // GRD Grenada
    case GE = 268; // GEO Georgia
    case GF = 254; // GUF French Guiana
    case GG = 831; // GGY Guernsey
    case GH = 288; // GHA Ghana
    case GI = 292; // GIB Gibraltar
    case GL = 304; // GRL Greenland
    case GM = 270; // GMB Gambia (the)
    case GN = 324; // GIN Guinea
    case GP = 312; // GLP Guadeloupe
    case GQ = 226; // GNQ Equatorial Guinea
    case GR = 300; // GRC Greece
    case GS = 239; // SGS South Georgia and the South Sandwich Islands
    case GT = 320; // GTM Guatemala
    case GU = 316; // GUM Guam
    case GW = 624; // GNB Guinea-Bissau
    case GY = 328; // GUY Guyana
    case HK = 344; // HKG Hong Kong
    case HM = 334; // HMD Heard Island and McDonald Islands
    case HN = 340; // HND Honduras
    case HR = 191; // HRV Croatia
    case HT = 332; // HTI Haiti
    case HU = 348; // HUN Hungary
    case ID = 360; // IDN Indonesia
    case IE = 372; // IRL Ireland
    case IL = 376; // ISR Israel
    case IM = 833; // IMN Isle of Man
    case IN = 356; // IND India
    case IO = 86; // IOT British Indian Ocean Territory (the)
    case IQ = 368; // IRQ Iraq
    case IR = 364; // IRN Iran (Islamic Republic of)
    case IS = 352; // ISL Iceland
    case IT = 380; // ITA Italy
    case JE = 832; // JEY Jersey
    case JM = 388; // JAM Jamaica
    case JO = 400; // JOR Jordan
    case JP = 392; // JPN Japan
    case KE = 404; // KEN Kenya
    case KG = 417; // KGZ Kyrgyzstan
    case KH = 116; // KHM Cambodia
    case KI = 296; // KIR Kiribati
    case KM = 174; // COM Comoros (the)
    case KN = 659; // KNA Saint Kitts and Nevis
    case KP = 408; // PRK Korea (the Democratic People's Republic of)
    case KR = 410; // KOR Korea (the Republic of)
    case KW = 414; // KWT Kuwait
    case KY = 136; // CYM Cayman Islands (the)
    case KZ = 398; // KAZ Kazakhstan
    case LA = 418; // LAO Lao People's Democratic Republic (the)
    case LB = 422; // LBN Lebanon
    case LC = 662; // LCA Saint Lucia
    case LI = 438; // LIE Liechtenstein
    case LK = 144; // LKA Sri Lanka
    case LR = 430; // LBR Liberia
    case LS = 426; // LSO Lesotho
    case LT = 440; // LTU Lithuania
    case LU = 442; // LUX Luxembourg
    case LV = 428; // LVA Latvia
    case LY = 434; // LBY Libya
    case MA = 504; // MAR Morocco
    case MC = 492; // MCO Monaco
    case MD = 498; // MDA Moldova (the Republic of)
    case ME = 499; // MNE Montenegro
    case MF = 663; // MAF Saint Martin (French part)
    case MG = 450; // MDG Madagascar
    case MH = 584; // MHL Marshall Islands (the)
    case MK = 807; // MKD Republic of North Macedonia
    case ML = 466; // MLI Mali
    case MM = 104; // MMR Myanmar
    case MN = 496; // MNG Mongolia
    case MO = 446; // MAC Macao
    case MP = 580; // MNP Northern Mariana Islands (the)
    case MQ = 474; // MTQ Martinique
    case MR = 478; // MRT Mauritania
    case MS = 500; // MSR Montserrat
    case MT = 470; // MLT Malta
    case MU = 480; // MUS Mauritius
    case MV = 462; // MDV Maldives
    case MW = 454; // MWI Malawi
    case MX = 484; // MEX Mexico
    case MY = 458; // MYS Malaysia
    case MZ = 508; // MOZ Mozambique
    case NA = 516; // NAM Namibia
    case NC = 540; // NCL New Caledonia
    case NE = 562; // NER Niger (the)
    case NF = 574; // NFK Norfolk Island
    case NG = 566; // NGA Nigeria
    case NI = 558; // NIC Nicaragua
    case NL = 528; // NLD Netherlands (the)
    case NO = 578; // NOR Norway
    case NP = 524; // NPL Nepal
    case NR = 520; // NRU Nauru
    case NU = 570; // NIU Niue
    case NZ = 554; // NZL New Zealand
    case OM = 512; // OMN Oman
    case PA = 591; // PAN Panama
    case PE = 604; // PER Peru
    case PF = 258; // PYF French Polynesia
    case PG = 598; // PNG Papua New Guinea
    case PH = 608; // PHL Philippines (the)
    case PK = 586; // PAK Pakistan
    case PL = 616; // POL Poland
    case PM = 666; // SPM Saint Pierre and Miquelon
    case PN = 612; // PCN Pitcairn
    case PR = 630; // PRI Puerto Rico
    case PS = 275; // PSE Palestine, State of
    case PT = 620; // PRT Portugal
    case PW = 585; // PLW Palau
    case PY = 600; // PRY Paraguay
    case QA = 634; // QAT Qatar
    case RE = 638; // REU Réunion
    case RO = 642; // ROU Romania
    case RS = 688; // SRB Serbia
    case RU = 643; // RUS Russian Federation (the)
    case RW = 646; // RWA Rwanda
    case SA = 682; // SAU Saudi Arabia
    case SB = 90; // SLB Solomon Islands
    case SC = 690; // SYC Seychelles
    case SD = 729; // SDN Sudan (the)
    case SE = 752; // SWE Sweden
    case SG = 702; // SGP Singapore
    case SH = 654; // SHN Saint Helena, Ascension and Tristan da Cunha
    case SI = 705; // SVN Slovenia
    case SJ = 744; // SJM Svalbard and Jan Mayen
    case SK = 703; // SVK Slovakia
    case SL = 694; // SLE Sierra Leone
    case SM = 674; // SMR San Marino
    case SN = 686; // SEN Senegal
    case SO = 706; // SOM Somalia
    case SR = 740; // SUR Suriname
    case SS = 728; // SSD South Sudan
    case ST = 678; // STP Sao Tome and Principe
    case SV = 222; // SLV El Salvador
    case SX = 534; // SXM Sint Maarten (Dutch part)
    case SY = 760; // SYR Syrian Arab Republic
    case SZ = 748; // SWZ Eswatini
    case TC = 796; // TCA Turks and Caicos Islands (the)
    case TD = 148; // TCD Chad
    case TF = 260; // ATF French Southern Territories (the)
    case TG = 768; // TGO Togo
    case TH = 764; // THA Thailand
    case TJ = 762; // TJK Tajikistan
    case TK = 772; // TKL Tokelau
    case TL = 626; // TLS Timor-Leste
    case TM = 795; // TKM Turkmenistan
    case TN = 788; // TUN Tunisia
    case TO = 776; // TON Tonga
    case TR = 792; // TUR Turkey
    case TT = 780; // TTO Trinidad and Tobago
    case TV = 798; // TUV Tuvalu
    case TW = 158; // TWN Taiwan (Province of China)
    case TZ = 834; // TZA Tanzania, United Republic of
    case UA = 804; // UKR Ukraine
    case UG = 800; // UGA Uganda
    case UM = 581; // UMI United States Minor Outlying Islands (the)
    case US = 840; // USA United States of America (the)
    case UY = 858; // URY Uruguay
    case UZ = 860; // UZB Uzbekistan
    case VA = 336; // VAT Holy See (the)
    case VC = 670; // VCT Saint Vincent and the Grenadines
    case VE = 862; // VEN Venezuela (Bolivarian Republic of)
    case VG = 92; // VGB Virgin Islands (British)
    case VI = 850; // VIR Virgin Islands (U.S.)
    case VN = 704; // VNM Viet Nam
    case VU = 548; // VUT Vanuatu
    case WF = 876; // WLF Wallis and Futuna
    case WS = 882; // WSM Samoa
    case YE = 887; // YEM Yemen
    case YT = 175; // MYT Mayotte
    case ZA = 710; // ZAF South Africa
    case ZM = 894; // ZMB Zambia
    case ZW = 716; // ZWE Zimbabwe

    const EU = [
        self::AT,
        self::BE,
        self::BG,
        self::HR,
        self::CY,
        self::CZ,
        self::DK,
        self::EE,
        self::FI,
        self::FR,
        self::DE,
        self::GR,
        self::HU,
        self::IE,
        self::IT,
        self::LV,
        self::LT,
        self::LU,
        self::MT,
        self::NL,
        self::PL,
        self::PT,
        self::RO,
        self::SK,
        self::SI,
        self::ES,
        self::SE,
    ];

    const EM = self::EU; // European Market - EUIPO TmView uses this as a filter that has all 27 EU states

    const EFTA = [
        self::IS,
        self::LI,
        self::NO,
        self::CH,
    ];

    const BX = [ // Benelux
        self::BE,
        self::NL,
        self::LU,
    ];


    /**
     * Return all country codes.
     * 
     * @return array<string> Return list of 2-letter country codes.
     */
    static public function getCountryCodesAll(): array {
        return array_map(fn($c) => $c->name, self::cases());
    }

    /**
     * Return human-friendly name of the country.
     *
     * @return string
     */
    public function getFriendlyName(): string
    {
        return \Locale::getDisplayRegion("-{$this->name}");
    }

    /**
     * Convert one or many country codes to CountryEnum.
     *
     * @param array|integer|string|CountryEnum $country
     * @return CountryEnum|array<CountryEnum>
     */
    static function convert(array|int|string|CountryEnum $country): CountryEnum|array
    {
        if (is_array($country)) {
            return array_map(fn ($c) => self::convert($c), $country);
        }

        if ($country instanceof CountryEnum) {
            return $country;
        }

        if (is_int($country)) {
            return self::from($country);
        }

        return self::fromKey($country);
    }

    /**
     * Convert country code to CountryEnum.
     * 
     * Example:
     * 
     * CountryEnum::fromKey('CZ') === CountryEnum::CZ
     *
     * @param string $key
     * @return CountryEnum
     * @throws \InvalidArgumentException
     */
    static public function fromKey(string $key): self
    {
        $ret = self::tryFromKey($key)
            or throw new \InvalidArgumentException("Country code '$key' not found.");
        return $ret;
    }
    
    static public function tryFromKey(string $key): ?self {
        $key = strtoupper($key);
        // Other solution is cycle self::cases() 
        if (!defined("self::$key")) return null; 
        $obj = constant("self::$key");
        return  $obj instanceof self ? $obj : null;
    }

    public function getIconURL(): string
    {
        return "/dist/zolinga-commons/images/countries/" . strtolower($this->name) . ".svg";
    }
}

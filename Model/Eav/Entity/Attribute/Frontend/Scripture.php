<?php

namespace DevStone\ImageProducts\Model\Eav\Entity\Attribute\Frontend;

use Magento\Eav\Model\Entity\Attribute\Source\BooleanFactory;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json as Serializer;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\ObjectManager;

/**
 * OverRides default frontend display so it doesn't check input type but renders all keywords.
 *
 * @author dave
 */
class Scripture extends \Magento\Eav\Model\Entity\Attribute\Frontend\AbstractFrontend
{

    /**
     * @var \Dbt
     */
    private $dbt;

    private $codeLookup = [];

    /**
     * @var CacheInterface
     */
    private $cacheLocal;

    /**
     * @var StoreManagerInterface
     */
    private $storeManagerLocal;

    /**
     * @var \Magento\Framework\Escaper
     */
    private $escaper;

    public function __construct(
        BooleanFactory $attrBooleanFactory,
        \Magento\Framework\Escaper $escaper,
        CacheInterface $cache = null,
        $storeResolver = null,
        array $cacheTags = null,
        StoreManagerInterface $storeManager = null,
        Serializer $serializer = null
    ) {
        $this->storeManagerLocal = $storeManager ?: ObjectManager::getInstance()->get(StoreManagerInterface::class);
        $this->cacheLocal = $cache ?: ObjectManager::getInstance()->get(CacheInterface::class);
        $this->escaper = $escaper;
        parent::__construct($attrBooleanFactory, $cache, $storeResolver, $cacheTags, $storeManager, $serializer);
    }

    /**
     * Retrieve attribute value
     *
     * @param \Magento\Framework\DataObject $object
     * @return mixed
     */
    public function getValue(\Magento\Framework\DataObject $object)
    {
        $value = $object->getData($this->getAttribute()->getAttributeCode());

        $scripturesTranslated = $this->getOption($value);

        if (!is_array($scripturesTranslated)) {
            $scripturesTranslated = [$scripturesTranslated];
        }

        $this->getAttribute()->setStoreId(\Magento\Store\Model\Store::DEFAULT_STORE_ID);

        $scriptures = $this->getOption($value);

        if (empty($scriptures)) {
            return $scriptures;
        }
        if (!is_array($scriptures)) {
            $scriptures = [$scriptures];
        }

        $rendered = '<span data-bind="scope: \'devstone-scriptures\'">';
        foreach ($scripturesTranslated as $key => $scripture) {
            $rendered .= '<a class="scripture" href="#" data-bind="click: setCurrent.bind(\''.$key.'\')">' . $this->escaper->escapeHtml($scripture) . '</a> &nbsp; ';
        }
        $rendered .= '</div></td><tr><td colspan="2" id="devstone-scriptures" data-bind="scope: \'devstone-scriptures\'">';
        foreach ($scriptures as $key => $scripture) {
            $rendered .= '<div class="scripture-text" data-bind="visible: current() === \''.$key.'\'">' . $this->getScriptureText($scripture) . '</div>';
        }
        $rendered .= <<<'HTML'
</td></tr>
<script type="text/x-magento-init">
    {
        "#devstone-scriptures": {
            "Magento_Ui/js/core/app": {
                "components": {
                    "devstone-scriptures": {
                        "component": "DevStone_ImageProducts/scriptures"
                    }
                }
            }
        }
    }
</script>
HTML;

        return $rendered;
    }

    public function getScriptureText($scripture)
    {
        try {
            $code = $this->storeManagerLocal->getStore()->getCode();
        } catch (NoSuchEntityException $e) {
            $code = 'en';
        }

        $cacheKey = 'getScriptureText:'.$code.':'.$scripture;
        $text = $this->cacheLocal->load($cacheKey);
        if ($text) {
            return $text;
        }

        if (!preg_match(
            '/(?<book_id>\S+)\s+(?<chapter>\d+):?(?<start_verse>\d+)?-?(?<end_verse>\d+)?/',
            $scripture,
            $matches
        )) {
            return '';
        };
        $familyCode = $this->getFamilyCode($code);

        $damId = $this->getDamId($matches['book_id'], $familyCode);

        if (empty($damId)) {
            $damId = $this->getDamId($matches['book_id'], 'ENG');
        }

        $verses = $this->getDbt()->getTextVerse(
            $damId,
            $matches['book_id'],
            $matches['chapter'],
            $matches['start_verse'] ?? null,
            $matches['end_verse'] ?? null
        );

        if (!empty($verses)) {
            $text = '<h4>'.$verses[0]['book_name'].' '.$verses[0]['chapter_title'].'</h4><p>';

            foreach ($verses as $verse) {
                $text .= '<strong>'.$verse['verse_id'].'</strong> '.$verse['verse_text'];
            }
            $text .= '</p>';
        } else {
            $text = '';
        }

        $this->cacheLocal->save($text, $cacheKey, [], 60*60*24*14);

        return $text;
    }

    private function getFamilyCode($code)
    {
        $cacheKey = 'getFamilyCode:'.$code;
        $familyCode = $this->cacheLocal->load($cacheKey);
        if ($familyCode) {
            return $familyCode;
        }

        $oldcodes = [
            'sr-Cyrl' => 'sr',
            'fil' => 'tl',
            'zh-CHS' => 'zh',
            'zh-CHT' => 'zh',
            'zh_CHS' => 'zh',
            'zh_CHT' => 'zh',
            'default' => 'en',
            'Default' => 'en',
        ];

        $code = $oldcodes[$code] ?? $code;

        if (empty($this->codeLookup)) {
            $languages = $this->getDbt()->getLibraryLanguage() ?: array(array('language_iso_1' => 'en', 'language_family_code' => 'ENG')) ;
            foreach ($languages as $language) {
                if (!empty($language['language_iso_1'])) {
                    $this->codeLookup[$language['language_iso_1']] = $language['language_family_code'];
                }
            }

            $this->codeLookup['ms'] = 'ZLM';
            $this->codeLookup['zh'] = 'CHN';
            $this->codeLookup['el'] = 'GRK';
        };

        $familyCode = $this->codeLookup[$code];

        $this->cacheLocal->save($familyCode, $cacheKey, [], 60*60*24*14);

        return $familyCode;
    }

    private function getDamId($bookId, $familyCode)
    {
        $cacheKey = 'getDamId:' . $bookId . ':' . $familyCode;
        $demId = $this->cacheLocal->load($cacheKey);

        if ($demId) {
            return $demId;
        }

        $booksToCollection = [
            'Gen'    => 'OT',
            "Exod"   => 'OT',
            "Lev"    => 'OT',
            "Num"    => 'OT',
            "Deut"   => 'OT',
            "Josh"   => 'OT',
            "Judg"   => 'OT',
            "Ruth"   => 'OT',
            "1Sam"   => 'OT',
            "2Sam"   => 'OT',
            "1Kgs"   => 'OT',
            "2Kgs"   => 'OT',
            "1Chr"   => 'OT',
            "2Chr"   => 'OT',
            "Ezra"   => 'OT',
            "Neh"    => 'OT',
            "Esth"   => 'OT',
            "Job"    => 'OT',
            "Ps"     => 'OT',
            "Prov"   => 'OT',
            "Eccl"   => 'OT',
            "Song"   => 'OT',
            "Isa"    => 'OT',
            "Jer"    => 'OT',
            "Lam"    => 'OT',
            "Ezek"   => 'OT',
            "Dan"    => 'OT',
            "Hos"    => 'OT',
            "Joel"   => 'OT',
            "Amos"   => 'OT',
            "Obad"   => 'OT',
            "Jonah"  => 'OT',
            "Mic"    => 'OT',
            "Nah"    => 'OT',
            "Hab"    => 'OT',
            "Zeph"   => 'OT',
            "Hag"    => 'OT',
            "Zech"   => 'OT',
            "Mal"    => 'OT',
            "Matt"   => 'NT',
            "Mark"   => 'NT',
            "Luke"   => 'NT',
            "John"   => 'NT',
            "Acts"   => 'NT',
            "Rom"    => 'NT',
            "1Cor"   => 'NT',
            "2Cor"   => 'NT',
            "Gal"    => 'NT',
            "Eph"    => 'NT',
            "Phil"   => 'NT',
            "Col"    => 'NT',
            "1Thess" => 'NT',
            "2Thess" => 'NT',
            "1Tim"   => 'NT',
            "2Tim"   => 'NT',
            "Titus"  => 'NT',
            "Phlm"   => 'NT',
            "Heb"    => 'NT',
            "Jas"    => 'NT',
            "1Pet"   => 'NT',
            "2Pet"   => 'NT',
            "1John"  => 'NT',
            "2John"  => 'NT',
            "3John"  => 'NT',
            "Jude"   => 'NT',
            "Rev"    => 'NT',
        ];
        $defaultVersions = [
            'ENG' => 'ESV',
            'CHN' => 'CNV',
            'IND' => 'NTV',
            'GER' => 'D71',
            'SPN' => 'BDA',
            'FRN' => 'PDC',
            'HUN' => 'RNT',
            'VIE' => 'WTC',
            'GRK' => 'SFT',
            'THA' => 'TSV',
        ];

        $volumes = $this->getDbt()->getLibraryVolume(
            $damId = null,
            $fcbhId = null,
            $media = 'text',
            $delivery = null,
            $language = null,
            $languageCode = null,
            $versionCode = $defaultVersions[$familyCode] ?? null,
            $updated = null,
            $status = null,
            $expired = null,
            $orgId = null,
            $fullWord = null,
            $languageFamilyCode = $familyCode
        ) ?: array(array('collection_code' => 'rock', 'dem_id' => 'fixme'));

        $collectionCode = $booksToCollection[$bookId];

        $demId = false;
        foreach ($volumes as $volume) {
            if ($volume['collection_code'] === $collectionCode) {
                $demId = $volume['dam_id'];
                break;
            }
        }

        $this->cacheLocal->save($demId, $cacheKey, [], 60*60*24*14);
        return $demId;
    }

    /**
     * @return \Dbt
     */
    private function getDbt()
    {
        if (!isset($this->dbt)) {
            $this->dbt = new \Dbt('7ba37caa515eb8da28722301a122c3de', null, null, 'array');
        }
        return $this->dbt;
    }
}

<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Ui\DataProvider\Product\Form\Modifier\Data;

use DevStone\ImageProducts\Model\Product\Type;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Downloadable\Helper\File as DownloadableFile;
use Magento\Downloadable\Model\Link;
use Magento\Downloadable\Ui\DataProvider\Product\Form\Modifier\Data\Links as ParentLinks;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Override Magento's class to change the typeId check
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Links extends ParentLinks
{

    protected State $appState;

    public function __construct(
        Escaper $escaper,
        LocatorInterface $locator,
        ScopeConfigInterface $scopeConfig,
        DownloadableFile $downloadableFile,
        UrlInterface $urlBuilder,
        Link $linkModel,
        State $appState
    ) {
        parent::__construct($escaper, $locator, $scopeConfig, $downloadableFile, $urlBuilder, $linkModel);
        $this->appState = $appState;
    }

    /**
     * Retrieve default links title
     *
     * @return string
     */
    public function getLinksTitle()
    {
        return $this->locator->getProduct()->getId() &&
        $this->locator->getProduct()->getTypeId() == Type::TYPE_ID
            ? $this->locator->getProduct()->getLinksTitle()
            : $this->scopeConfig->getValue(
                Link::XML_PATH_LINKS_TITLE,
                ScopeInterface::SCOPE_STORE
            );
    }

    /**
     * Get Links data
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @return array
     */
    public function getLinksData()
    {
        $linksData = [];
        if ($this->locator->getProduct()->getTypeId() !== Type::TYPE_ID) {
            return $linksData;
        }

        $links = $this->locator->getProduct()->getTypeInstance()->getLinks($this->locator->getProduct());
        /** @var LinkInterface $link */
        foreach ($links as $link) {
            $linkData = [];
            $linkData['link_id'] = $link->getId();
            $linkData['title'] = $this->escaper->escapeHtml($link->getTitle() ?: 'image');
            $linkData['price'] = $this->getPriceValue($link->getPrice());
            $linkData['number_of_downloads'] = $link->getNumberOfDownloads();
            $linkData['is_shareable'] = $link->getIsShareable();
            $linkData['link_url'] = $link->getLinkUrl();
            $linkData['type'] = $link->getLinkType();
            $linkData['sample']['url'] = $link->getSampleUrl();
            $linkData['sample']['type'] = $link->getSampleType();
            $linkData['sort_order'] = $link->getSortOrder();
            $linkData['is_unlimited'] = $linkData['number_of_downloads'] ? '0' : '1';
            $linkData['gallery_size'] = 12;

            if ($this->locator->getProduct()->getStoreId()) {
                $linkData['use_default_price'] = $link->getWebsitePrice() ? '0' : '1';
                $linkData['use_default_title'] = $link->getStoreTitle() ? '0' : '1';
            }

            $linkData = $this->addLinkFile($linkData, $link);
            $linkData = $this->addSampleFile($linkData, $link);

            $linksData[] = $linkData;
        }

        return $linksData;
    }

    /**
     * Add Link File info into $linkData
     *
     * @param array $linkData
     * @param LinkInterface $link
     * @return array
     * @throws LocalizedException
     */
    protected function addLinkFile(array $linkData, LinkInterface $link): array
    {
        $area = 'adminhtml' === $this->appState->getAreaCode() ? 'adminhtml' : 'csproduct'; // See if we are in backend or frontend as vendor

        $linkFile = $link->getLinkFile();
        if ($linkFile) {
            $file = $this->downloadableFile->getFilePath($this->linkModel->getBasePath(), $linkFile);
            if ($this->downloadableFile->ensureFileInFilesystem($file)) {
                $linkData['file'][0] = [
                    'file' => $linkFile,
                    'name' => $this->downloadableFile->getFileFromPathFile($linkFile),
                    'size' => $this->downloadableFile->getFileSize($file),
                    'status' => 'old',
                    'url' => $this->urlBuilder->getUrl(
                        $area . '/downloadable_product_edit/link',
                        ['id' => $link->getId(), 'type' => 'link', '_secure' => true]
                    ),
                ];
            }
        }

        return $linkData;
    }
}

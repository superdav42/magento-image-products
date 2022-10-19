<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Controller\Adminhtml\Product\Initialization\Helper\Plugin;

use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Controller\Adminhtml\Product\Initialization\Helper;
use Magento\Catalog\Model\Product;
use Magento\Downloadable\Api\Data\LinkInterfaceFactory;
use Magento\Downloadable\Api\Data\SampleInterfaceFactory;
use Magento\Downloadable\Helper\Download;
use Magento\Downloadable\Model\Link\Builder as LinkBuilder;
use Magento\Downloadable\Model\ResourceModel\Sample\Collection;
use Magento\Downloadable\Model\Sample;
use Magento\Downloadable\Model\Sample\Builder as SampleBuilder;
use Magento\Framework\App\RequestInterface;

class Downloadable extends \Magento\Downloadable\Controller\Adminhtml\Product\Initialization\Helper\Plugin\Downloadable
{

    /**
     * @var RequestInterface
     */
    protected $request;
    private SampleInterfaceFactory $sampleFactory;
    private LinkInterfaceFactory $linkFactory;
    private SampleBuilder $sampleBuilder;
    private LinkBuilder $linkBuilder;

    public function __construct(
        RequestInterface $request,
        LinkBuilder $linkBuilder,
        SampleBuilder $sampleBuilder,
        SampleInterfaceFactory $sampleFactory,
        LinkInterfaceFactory $linkFactory
    ) {
        $this->request = $request;
        $this->linkBuilder = $linkBuilder;
        $this->sampleBuilder = $sampleBuilder;
        $this->sampleFactory = $sampleFactory;
        $this->linkFactory = $linkFactory;
        parent::__construct($request, $linkBuilder, $sampleBuilder, $sampleFactory, $linkFactory);
    }

    public function afterInitialize(Helper $subject, Product $product)
    {
        if ($product->getTypeId() !== Type::TYPE_ID) {
            return parent::afterInitialize($subject, $product);
        }
        if ($downloadable = $this->request->getPost('downloadable')) {
            $product->setDownloadableData($downloadable);
            $extension = $product->getExtensionAttributes();
            $productLinks = $product->getTypeInstance()->getLinks($product);
            $productSamples = $product->getTypeInstance()->getSamples($product);
            if (isset($downloadable['link']) && is_array($downloadable['link'])) {
                $links = [];
                foreach ($downloadable['link'] as $linkData) {
                    if (!$linkData || (isset($linkData['is_delete']) && $linkData['is_delete'])) {
                        continue;
                    } else {
                        $linkData = $this->processLink($linkData, $productLinks);
                        $links[] = $this->linkBuilder->setData(
                            $linkData
                        )->build(
                            $this->linkFactory->create()
                        );
                    }
                }
                $extension->setDownloadableProductLinks($links);
            } else {
                $extension->setDownloadableProductLinks([]);
            }
            if (isset($downloadable['sample']) && is_array($downloadable['sample'])) {
                $samples = [];
                foreach ($downloadable['sample'] as $sampleData) {
                    if (!$sampleData || (isset($sampleData['is_delete']) && (bool)$sampleData['is_delete'])) {
                        continue;
                    } else {
                        $sampleData = $this->processSample($sampleData, $productSamples);
                        $samples[] = $this->sampleBuilder->setData(
                            $sampleData
                        )->build(
                            $this->sampleFactory->create()
                        );
                    }
                }
                $extension->setDownloadableProductSamples($samples);
            } else {
                $extension->setDownloadableProductSamples([]);
            }
            $product->setExtensionAttributes($extension);
            if ($product->getLinksPurchasedSeparately()) {
                $product->setTypeHasRequiredOptions(true)->setRequiredOptions(true);
            } else {
                $product->setTypeHasRequiredOptions(false)->setRequiredOptions(false);
            }
        }
        return $product;
    }

    /**
     * Check Links type and status.
     *
     * @param array $linkData
     * @param array $productLinks
     * @return array
     */
    private function processLink(array $linkData, array $productLinks): array
    {
        $linkId = $linkData['link_id'] ?? null;
        if ($linkId && isset($productLinks[$linkId])) {
            $linkData = $this->processFileStatus($linkData, $productLinks[$linkId]->getLinkFile());
            $linkData['sample'] = $this->processFileStatus(
                $linkData['sample'] ?? [],
                $productLinks[$linkId]->getSampleFile()
            );
        } else {
            $linkData = $this->processFileStatus($linkData, null);
            $linkData['sample'] = $this->processFileStatus($linkData['sample'] ?? [], null);
        }

        return $linkData;
    }

    /**
     * Check Sample type and status.
     *
     * @param array $sampleData
     * @param Collection $productSamples
     * @return array
     */
    private function processSample(array $sampleData, Collection $productSamples): array
    {
        $sampleId = $sampleData['sample_id'] ?? null;
        /** @var Sample $productSample */
        $productSample = $sampleId ? $productSamples->getItemById($sampleId) : null;
        if ($sampleId && $productSample) {
            $sampleData = $this->processFileStatus($sampleData, $productSample->getSampleFile());
        } else {
            $sampleData = $this->processFileStatus($sampleData, null);
        }

        return $sampleData;
    }

    /**
     * Compare file path from request with DB and set status.
     *
     * @param array $data
     * @param string|null $file
     * @return array
     */
    private function processFileStatus(array $data, ?string $file): array
    {
        if (isset($data['type']) && $data['type'] === Download::LINK_TYPE_FILE && isset($data['file']['0']['file'])) {
            if ($data['file'][0]['file'] !== $file) {
                $data['file'][0]['status'] = 'new';
            } else {
                $data['file'][0]['status'] = 'old';
            }
        }

        return $data;
    }
}

<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\EntityManager\Observer;

use DevStone\ImageProducts\Model\Product\Gallery\Processor;
use DevStone\ImageProducts\Model\Product\Type;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Image;
use Magento\Framework\Image\Factory;
use Magento\Framework\Validator\Exception;
use Magento\MediaStorage\Helper\File\Storage\Database;

class BeforeImageProductSave implements ObserverInterface
{
    /**
     * @var WriteInterface
     */
    protected WriteInterface $mediaDirectory;

    /**
     *
     * @param Config $mediaConfig
     * @param Processor $mediaGalleryProcessor
     * @param Factory $imageFactory
     * @param Filesystem $filesystem
     * @param ProductAttributeRepositoryInterface $attributeRepository
     * @throws FileSystemException
     */
    public function __construct(
        protected Config $mediaConfig,
        protected Processor $mediaGalleryProcessor,
        protected Factory $imageFactory,
        Filesystem $filesystem,
        protected ProductAttributeRepositoryInterface $attributeRepository,
        protected Database $fileStorageDb,
        /**
         * Request instance
         */
        protected RequestInterface $request
    ) {
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }
    /**
     * Apply model save operation
     *
     * @param Observer $observer
     * @throws Exception
     * @return void
     */
    #[\Override]
    public function execute(Observer $observer)
    {
        $entity = $observer->getEvent()->getProduct();

        if ('image' === $this->request->getParam('type', null)) {
            $entity->setTypeId(Type::TYPE_ID);
        }

        if ($entity instanceof ProductInterface &&
                $entity->getTypeId() === Type::TYPE_ID) {
            $mediaGalleryData = $entity->getData('media_gallery');

            $linkFileNames = [];
            $fileLinks = [];
            /** @var LinkInterface[] $links */
            $links = $entity->getExtensionAttributes()->getDownloadableProductLinks() ?: [];
            foreach ($links as $link) {
                if ($link->getLinkType() !== 'file') {
                    continue;
                    // This is special type like gal_* or url;
                }
                $file = $link->getLinkFile();

                $linkFileName = pathinfo((string) $file, PATHINFO_FILENAME);
                if ($linkFileName === '') {
                    continue;
                }

                $linkFileNames[] = $linkFileName;
                $fileLinks[] = $link;
            }

            // A partial product save can omit downloadable links. In that case,
            // the gallery is not authoritative and must not be cleared.
            if ($linkFileNames === []) {
                return;
            }

            $existingLinkFileNames = [];
            if (isset($mediaGalleryData['images']) && is_array($mediaGalleryData['images'])) {
                foreach ($mediaGalleryData['images'] as $image) {
                    if (empty($image['removed'])) {
                        $existingLinkFileNames[] = $this->resolveLinkedFileName(
                            (string) ($image['file'] ?? ''),
                            $linkFileNames
                        );
                    }
                }
            }

            foreach ($fileLinks as $link) {
                $file = $link->getLinkFile();
                $linkFileName = pathinfo((string) $file, PATHINFO_FILENAME);
                if (!in_array($linkFileName, $existingLinkFileNames, true)) {

                    $file = $this->mediaGalleryProcessor->addImage(
                        $entity,
                        $link->getBasePath() . $file,
                        ['image', 'small_image', 'thumbnail'],
                        false,
                        false
                    );
                    $this->processImage($file, $entity);
                }
            }

            $updatedMediaGalleryData = $entity->getData('media_gallery');

            if (isset($updatedMediaGalleryData['images']) && is_array($updatedMediaGalleryData['images'])) {
                foreach ($updatedMediaGalleryData['images'] as &$image) {
                    if (isset($image['types']) && $this->stringInArray('frame_image', $image['types'])) {
                        continue;
                    }
                    $linkFileName = $this->resolveLinkedFileName(
                        (string) ($image['file'] ?? ''),
                        $linkFileNames
                    );

                    if (empty($linkFileName) ||
                        !in_array($linkFileName, $linkFileNames, true)
                    ) {
                        $image['removed'] = 1;
                    }
                }

                $entity->setData('media_gallery', $updatedMediaGalleryData);
            }
        }
    }
    /**
     *
     * @param string $file dispersed filename
     * @param ProductInterface $product
     */
    protected function processImage($file, ProductInterface $product)
    {
        $absolutePathToImage = $this->mediaDirectory->getAbsolutePath(
            $this->mediaConfig->getTmpMediaPath($file)
        );
        $processor = $this->imageFactory->create($absolutePathToImage);
        $this->updateAttributes($processor, $product);
        $processor->quality(92);
        $processor->keepAspectRatio(true);
        $processor->constrainOnly(true);
        $processor->resize(1024, 1024);
        $processor->save($absolutePathToImage);
        // no workie
        $this->fileStorageDb->saveFile($this->mediaConfig->getTmpMediaShortUrl($file));
    }

    /**
     *
     * @param Image $processor
     * @param ProductInterface $product
     */
    protected function updateAttributes(
        Image $processor,
        ProductInterface $product
    ) {
        $width = $processor->getOriginalWidth();
        $height = $processor->getOriginalHeight();
        $product->setWidth($width);
        $product->setHeight($height);
        $dar = 0;
        if ($height > 0) {
            $dar = $width / $height;
        }
        if ($dar < 0.9) {
            $orientation = 'Vertical';
        } elseif ($dar >= 0.9 && $dar <= 1.1) {
            $orientation = 'Square';
        } else {
            $orientation = 'Horizontal';
        }

        //$product->setClean(true);

        $product->setOrientation($this->getAttributeOptionId('orientation', $orientation));
    }

    /**
     * Get attribute by code.
     *
     * @param string $attributeCode
     * @return ProductAttributeInterface
     */
    protected function getAttribute($attributeCode): ProductAttributeInterface
    {
        return $this->attributeRepository->get($attributeCode);
    }

    protected function getAttributeOptionId($attributeCode, $label): ?string
    {
        /** @var Attribute $attribute */
        $attribute = $this->getAttribute($attributeCode);

        foreach ($attribute->getOptions() as $option) {
            if ($option->getLabel() == $label) {
                return $option->getValue();
            }
        }
        return null;
    }

    private function stringInArray($needle, array $haystack): bool
    {
        foreach ($haystack as $value) {
            if (str_contains((string) $value, (string) $needle) && !str_contains((string) $value, 'frameImage')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolve a gallery filename to its downloadable-link filename.
     *
     * Magento appends _N when a replacement image reuses a filename. Prefer
     * an exact link match, then remove that generated suffix only when the
     * resulting name matches a known downloadable link.
     *
     * @param string $file Gallery image path
     * @param string[] $linkFileNames Known downloadable-link filenames
     * @return string
     */
    protected function resolveLinkedFileName(string $file, array $linkFileNames): string
    {
        $filename = pathinfo($file, PATHINFO_FILENAME);
        $siteIdDelimiter = '-GoodSalt-';
        if (str_contains($filename, $siteIdDelimiter)) {
            $filename = (string) substr(strstr($filename, $siteIdDelimiter), strlen($siteIdDelimiter));
        }

        if (in_array($filename, $linkFileNames, true)) {
            return $filename;
        }

        $withoutDuplicateSuffix = preg_replace('/_\d+$/', '', $filename);
        if ($withoutDuplicateSuffix !== null && in_array($withoutDuplicateSuffix, $linkFileNames, true)) {
            return $withoutDuplicateSuffix;
        }

        return $filename;
    }
}

<?php
/**
 * Created by PhpStorm.
 * User: dave
 * Date: 6/14/18
 * Time: 5:46 PM
 */

namespace DevStone\ImageProducts\Controller\Downloads;

use DevStone\UsageCalculator\Api\SizeRepositoryInterface;
use Imagine\Image\Box;
use Imagine\Image\ImageInterface;
use Imagine\Image\Point;
use Imagine\Imagick\Imagine;
use Magento\Downloadable\Helper\Download as DownloadHelper;
use Magento\Downloadable\Model\Link\Purchased\Item as PurchasedLink;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\ItemRepository;

class Download extends \Magento\Downloadable\Controller\Download
{
    protected \Magento\Framework\Module\Dir\Reader $moduleReader;

    /**
     * @var ItemRepository
     */
    private $itemRepository;

    /**
     * @var \DevStone\UsageCalculator\Api\UsageRepositoryInterface
     */
    private $usageRepository;

    /**
     * @var SizeRepositoryInterface
     */
    private $sizeRepository;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    /**
     * @var \Magento\Framework\Image\Factory
     */
    private $imageFactory;

    /**
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    private $mediaDirectory;

    /**
     * @var \Magento\Downloadable\Helper\File
     */
    private $downloadableFile;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    private $orientationSizeArray = [
        "Center_SD" => [
            "width" => 500,
            "height" => 375
        ],
        "LSide_SD" => [
            "width" => 279,
            "height" => 375
        ],
        "LSide_Pan_SD" => [
            "width" => 177,
            "height" => 375
        ],
        "RSide_SD" => [
            "width" => 279,
            "height" => 375
        ],
        "RSide_Pan_SD" => [
            "width" => 177,
            "height" => 375
        ],
        "TLCorner_SD" => [
            "width" => 290,
            "height" => 250
        ],
        "Top_SD" => [
            "width" => 500,
            "height" => 302
        ],
        "Top_Pan_SD" => [
            "width" => 500,
            "height" => 174
        ],
        "TRCorner_SD" => [
            "width" => 290,
            "height" => 250
        ],
        "BLCorner_SD" => [
            "width" => 290,
            "height" => 250
        ],
        "Bottom_SD" => [
            "width" => 500,
            "height" => 302
        ],
        "Bottom_Pan_SD" => [
            "width" => 500,
            "height" => 172
        ],
        "BRCorner_SD" => [
            "width" => 290,
            "height" => 250
        ],
        "Center_HD" => [
            "width" => 500,
            "height" => 281
        ],
        "LSide_HD" => [
            "width" => 314,
            "height" => 281
        ],
        "LSide_Pan_HD" => [
            "width" => 179,
            "height" => 281
        ],
        "RSide_HD" => [
            "width" => 314,
            "height" => 281
        ],
        "RSide_Pan_HD" => [
            "width" => 179,
            "height" => 281
        ],
        "TLCorner_HD" => [
            "width" => 288,
            "height" => 241
        ],
        "Top_HD" => [
            "width" => 500,
            "height" => 226
        ],
        "Top_Pan_HD" => [
            "width" => 500,
            "height" => 142
        ],
        "TRCorner_HD" => [
            "width" => 288,
            "height" => 241
        ],
        "BLCorner_HD" => [
            "width" => 288,
            "height" => 241
        ],
        "Bottom_HD" => [
            "width" => 500,
            "height" => 226
        ],
        "Bottom_Pan_HD" => [
            "width" => 500,
            "height" => 142
        ],
        "BRCorner_HD" => [
            "width" => 288,
            "height" => 241
        ]
    ];


    public function __construct(
        \Magento\Framework\Module\Dir\Reader $moduleReader,
        ItemRepository $itemRepository,
        Context $context,
        \DevStone\UsageCalculator\Api\UsageRepositoryInterface $usageRepository,
        \Magento\Framework\Serialize\Serializer\Json $serializer,
        SizeRepositoryInterface $sizeRepository,
        \Magento\Framework\Image\Factory $imageFactory,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Downloadable\Helper\File $downloadableFile,
        \Psr\Log\LoggerInterface $logger,
    ) {
        $this->itemRepository = $itemRepository;
        $this->usageRepository = $usageRepository;
        $this->sizeRepository = $sizeRepository;
        $this->serializer = $serializer;
        $this->imageFactory = $imageFactory;
        $this->filesystem = $filesystem;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $this->downloadableFile = $downloadableFile;
        $this->logger = $logger;
        parent::__construct($context);
        $this->moduleReader = $moduleReader;
    }

    /**
     * Return customer session object
     *
     * @return \Magento\Customer\Model\Session
     */
    private function _getCustomerSession()
    {
        return $this->_objectManager->get(\Magento\Customer\Model\Session::class);
    }
    /**
     * Download link action
     *
     * @return void|ResponseInterface
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public function execute()
    {
        $session = $this->_getCustomerSession();

        $id = $this->getRequest()->getParam('id', 0);
        /** @var PurchasedLink $linkPurchasedItem */
        $linkPurchasedItem = $this->_objectManager->create(
            \Magento\Downloadable\Model\Link\Purchased\Item::class
        )->load(
            $id,
            'link_hash'
        );
        if (!$linkPurchasedItem->getId()) {
            $this->messageManager->addNotice(__("We can't find the link you requested."));
            return $this->_redirect('*/customer/products');
        }
        if (!$this->_objectManager->get(\Magento\Downloadable\Helper\Data::class)->getIsShareable($linkPurchasedItem)) {
            $customerId = $session->getCustomerId();
            if (!$customerId) {
                /** @var \Magento\Catalog\Model\Product $product */
                $product = $this->_objectManager->create(
                    \Magento\Catalog\Model\Product::class
                )->load(
                    $linkPurchasedItem->getProductId()
                );
                if ($product->getId()) {
                    $notice = __(
                        'Please sign in to download your product or purchase <a href="%1">%2</a>.',
                        $product->getProductUrl(),
                        $product->getName()
                    );
                } else {
                    $notice = __('Please sign in to download your product.');
                }
                $this->messageManager->addNotice($notice);
                $session->authenticate();
                $session->setBeforeAuthUrl(
                    $this->_objectManager->create(
                        \Magento\Framework\UrlInterface::class
                    )->getUrl(
                        'downloadable/customer/products/',
                        ['_secure' => true]
                    )
                );
                return;
            }
            /** @var \Magento\Downloadable\Model\Link\Purchased $linkPurchased */
            $linkPurchased = $this->_objectManager->create(
                \Magento\Downloadable\Model\Link\Purchased::class
            )->load(
                $linkPurchasedItem->getPurchasedId()
            );
            if ($linkPurchased->getCustomerId() != $customerId) {
                $this->messageManager->addNotice(__("We can't find the link you requested."));
                return $this->_redirect('*/customer/products');
            }
        }
        $downloadsLeft = $linkPurchasedItem->getNumberOfDownloadsBought() -
            $linkPurchasedItem->getNumberOfDownloadsUsed();

        $status = $linkPurchasedItem->getStatus();
        if ($status == PurchasedLink::LINK_STATUS_AVAILABLE && ($downloadsLeft ||
                $linkPurchasedItem->getNumberOfDownloadsBought() == 0)
        ) {
            $resource = '';
            $resourceType = '';
            if ($linkPurchasedItem->getLinkType() == DownloadHelper::LINK_TYPE_URL) {
                $resource = $linkPurchasedItem->getLinkUrl();
                $resourceType = DownloadHelper::LINK_TYPE_URL;
            } elseif ($linkPurchasedItem->getLinkType() == DownloadHelper::LINK_TYPE_FILE) {
                $resource = $this->_objectManager->get(
                    \Magento\Downloadable\Helper\File::class
                )->getFilePath(
                    $this->_getLink()->getBasePath(),
                    $linkPurchasedItem->getLinkFile()
                );

                $fileExists = $this->downloadableFile->ensureFileInFilesystem($resource);

                $resourceType = DownloadHelper::LINK_TYPE_FILE;
            }
            try {
                $this->resizeFile($resource, $resourceType, $linkPurchasedItem);
                $linkPurchasedItem->setNumberOfDownloadsUsed($linkPurchasedItem->getNumberOfDownloadsUsed() + 1);

                if ($linkPurchasedItem->getNumberOfDownloadsBought() != 0 && !($downloadsLeft - 1)) {
                    $linkPurchasedItem->setStatus(PurchasedLink::LINK_STATUS_EXPIRED);
                }
                $linkPurchasedItem->save();
                exit(0);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage(), ['exception' => $e]);
                $this->messageManager->addError(__('Something went wrong while getting the requested content.'));
            }
        } elseif ($status == PurchasedLink::LINK_STATUS_EXPIRED) {
            $this->messageManager->addNotice(__('The link has expired.'));
        } elseif ($status == PurchasedLink::LINK_STATUS_PENDING || $status == PurchasedLink::LINK_STATUS_PAYMENT_REVIEW
        ) {
            $this->messageManager->addNotice(__('The link is not available.'));
        } else {
            $this->messageManager->addError(__('Something went wrong while getting the requested content.'));
        }
        return $this->_redirect('*/customer/products');
    }

    private function resizeFile($path, $resourceType, \Magento\Downloadable\Model\Link\Purchased\Item $linkPurchasedItem)
    {
        $orderItem = $this->itemRepository->get($linkPurchasedItem->getOrderItemId());

        $productOptions = $orderItem->getProductOptions();

        $templateOptionsObject = $productOptions['template_options'] ?? '';

        if ($templateOptionsObject) {
            return $this->generateTemplate(
                $path,
                $resourceType,
                $templateOptionsObject,
                $orderItem->getQuoteItemId()
            );
        }

        $usageIdOption= $productOptions['usage_id'] ?? '';

        if (empty($usageIdOption)) {
            throw new LocalizedException(__("Failed to get usage"));
        }
        $usage = $this->usageRepository->getById((int)$usageIdOption);

        $submittedOptions = $productOptions['usage_options'] ?? [];

        $sizeId = $usage->getSizeId();

        foreach ($usage->getOptions() as $option) {
            if (! empty($submittedOptions[$option->getId()])) {
                if (is_numeric($submittedOptions[$option->getId()])) {
                    $valueSizeId = $option->getValueById($submittedOptions[$option->getId()])->getSizeId();
                    if (! empty($valueSizeId)) {
                        $sizeId = $valueSizeId;
                    }
                }
            }
        }

        $size = $this->sizeRepository->getById($sizeId);

        $cachePath = 'downloadable/cache/' . $size->getCode() . $linkPurchasedItem->getLinkFile();

        if (! $this->mediaDirectory->isExist($cachePath)) {
            $absolutePath = $this->mediaDirectory->getAbsolutePath(
                $path
            );
            $processor = $this->imageFactory->create(
                $this->mediaDirectory->getAbsolutePath(
                    $path
                )
            );
            $processor->quality(98);
            $processor->keepAspectRatio(true);
            $processor->constrainOnly(true);
            $processor->resize($size->getMaxWidth(), $size->getMaxHeight());
            $processor->save($this->mediaDirectory->getAbsolutePath(
                $cachePath
            ));
        }

        $this->_processDownload($cachePath, $resourceType);
    }

    protected function generateTemplate($path, $resourceType, array $options, $id)
    {
        try {
            $cachePath = 'downloadable/cache/template/' . $id . '.jpg';
        if (! $this->mediaDirectory->isExist($cachePath)) {
            $imagine = new Imagine();
            $pubDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            $image = $imagine->load($this->mediaDirectory->readFile($path));

            $size = $options['size'] ?? 'SD';
            $orientation = $options['orientation'] ?? 'SD';

            $canvasWidth = $size === 'SD' ? 1024 : 1920;

            $backgroundName = $options['backgroundName'] ?? 'Hieroglyphics';
            $templateBuilderViewDir = $this->moduleReader->getModuleDir(
                \Magento\Framework\Module\Dir::MODULE_VIEW_DIR,
                'DevStone_TemplateBuilder'
            );
            $background = $imagine->open(
                $templateBuilderViewDir . '/frontend/web/templates_full/' .
                $backgroundName . '/' . $backgroundName . '_' . $size . '_Background.jpg'
            );

            $mask = $imagine->open(
                $templateBuilderViewDir . '/frontend/web/templates_full/Alpha masks/' .
                $size . '_' . $orientation . '.jpg'
            );
            if ($options['hideBackground'] == ' true') {
                $background = $imagine->create($background->getSize());
            }

            $finalImage = $imagine->create($background->getSize(), $mask->palette()->color('fff', 0));

            $black = $imagine->create($mask->getSize(), $mask->palette()->color('000'));
            $mask = $black->paste($mask, new Point(0, 0));
            $scale = 1;
            if (isset($options['baseScale'])) {
                $scale = $options['scale'] / $options['baseScale'];
            }

            $position = $options['orientation'] . '_' . $options['size'];
            $imageHeight = $image->getSize()->getHeight();
            $imageWidth = $image->getSize()->getWidth();
            $positionHeight = $this->orientationSizeArray[$position]['height'];
            $positionWidth = $this->orientationSizeArray[$position]['width'];
            if ($positionWidth / $positionHeight >= $imageWidth / $imageHeight) {
                $image->resize(
                    $image->getSize()->widen(
                        $background->getSize()->getWidth() * $scale * $positionWidth / 500
                    ),
                    ImageInterface::FILTER_LANCZOS
                );
            } else {
                $value = $positionHeight / ($options['size'] === 'HD' ? 281 : 375);
                $image->resize(
                    $image->getSize()->heighten(
                        $background->getSize()->getHeight() * $scale * $positionHeight / ($options['size'] === 'HD' ? 281 : 375)
                    ),
                    ImageInterface::FILTER_LANCZOS
                );
            }

            if ($options['flipImage'] === 'true') {
                $image->flipHorizontally();
            }

            $imageHeigt = $image->getSize()->getHeight();
            $imageWidth = $image->getSize()->getWidth();
            $left = $options['left'];
            $top = $options['top'];

            if ($left < 0 || $top < 0) {

                $boxH = min($background->getSize()->getHeight(), $imageHeigt);
                $boxw = min($background->getSize()->getWidth(), $imageWidth);
                $image->crop(
                    new Point(
                        $left < 0 ? abs($left) : 0,
                        $top < 0 ? abs($top) : 0
                    ),
                    new Box(
                        $imageWidth,
                        $imageHeigt
                    )
                );

                if ($left < 0) {
                    $left = 0;
                }
                if ($top < 0) {
                    $top = 0;
                }
            }


            $location = new Point($left, $top);
            $finalImage->paste($image, $location)
                ->applyMask($mask);

            if ($options['opacity'] < 1) {
                $finalImage->getImagick()->evaluateImage(
                    \Imagick::EVALUATE_MULTIPLY,
                    $options['opacity'],
                    \Imagick::CHANNEL_ALPHA
                );
            }

            $finalImage = $background->paste($finalImage, new Point(0, 0));
            if ($options['showTitleBar'] === 'true') {
                $titlebar = $imagine->open(
                    $templateBuilderViewDir . '/frontend/web/templates_full/' . $backgroundName . '/' .
                    $backgroundName . '_' . $size . '_TitleBar.png'
                );
                $finalImage->paste($titlebar, new Point(0, $options['titleTop'] < 0 ? 0 : $options['titleTop']));
            }

            if (!$this->mediaDirectory->isWritable(dirname($cachePath))) {
                $this->mediaDirectory->create(dirname($cachePath));
            }
            $this->mediaDirectory->writeFile($this->mediaDirectory->getAbsolutePath($cachePath), $finalImage->get('png'));
        }
            return $this->_processDownload($cachePath, $resourceType);
        } catch (\Exception $e) {
            $this->messageManager->addWarningMessage(__('We were unable to generate your template. Please contact support as you might need to reorder the template.'));
            throw new \Exception(__('We were unable to generate your template. Please contact support as you might need to reorder the template.'));
        }
    }
}

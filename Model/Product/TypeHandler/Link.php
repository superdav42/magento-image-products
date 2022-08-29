<?php

namespace DevStone\ImageProducts\Model\Product\TypeHandler;

use Magento\Downloadable\Model\ComponentInterface;

class Link extends \Magento\Downloadable\Model\Product\TypeHandler\Link {

    /**
     * @param ComponentInterface $model
     * @param array $files
     * @return void
     */
    protected function setFiles(ComponentInterface $model, array $files)
    {
        parent::setFiles($model, $files);
        if (in_array($model->getLinkType(), [ 'gal_10', 'gal_12','gal_14', 'gal_16', 'gal_20', 'gal_24', 'gal_30', 'gal_36', 'gal_40' ])) {
            $linkFileName = $this->downloadableFile->moveFileFromTmp(
                $this->createItem()->getBaseTmpPath(),
                $this->createItem()->getBasePath(),
                $files
            );
            $model->setLinkFile($linkFileName);
        }
    }
}

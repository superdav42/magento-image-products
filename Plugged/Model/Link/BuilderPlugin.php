<?php
namespace DevStone\ImageProducts\Plugged\Model\Link;

class BuilderPlugin
{
    public function beforeBuild(\Magento\Downloadable\Model\Link\Builder $subject, \Magento\Downloadable\Api\Data\LinkInterface $link)
    {
        $closure = function () use ($link, $subject) {
            if (in_array(
                $subject->data['type'],
                [
                    'gal_10', 'gal_12','gal_14', 'gal_16', 'gal_20', 'gal_24', 'gal_30', 'gal_36', 'gal_40'
                ]
            )) {
                if (!isset($subject->data['file'])) {
                    throw new \Magento\Framework\Exception\LocalizedException(__('Link file not provided'));
                }
                $linkFileName = $subject->downloadableFile->moveFileFromTmp(
                    $subject->getComponent()->getBaseTmpPath(),
                    $subject->getComponent()->getBasePath(),
                    $subject->data['file']
                );
                $link->setLinkFile($linkFileName);
                $link->setLinkUrl(null);
            }
        };

        $closure->bindTo($subject, \Magento\Downloadable\Model\Link\Builder::class)();

        return [$link];
    }
}

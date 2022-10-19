<?php

declare(strict_types=1);

namespace DevStone\ImageProducts\Plugged\Model\Link;

use Magento\Downloadable\Api\Data\LinkInterface;
use Magento\Downloadable\Model\Link\Builder;
use Magento\Framework\Exception\LocalizedException;

class BuilderPlugin
{
    public function beforeBuild(Builder $subject, LinkInterface $link): array
    {
        $closure = function () use ($link, $subject) {
            if (in_array(
                $subject->data['type'],
                [
                    'gal_10', 'gal_12','gal_14', 'gal_16', 'gal_20', 'gal_24', 'gal_30', 'gal_36', 'gal_40'
                ]
            )) {
                if (!isset($subject->data['file'])) {
                    throw new LocalizedException(__('Link file not provided'));
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

        $closure->bindTo($subject, Builder::class)();

        return [$link];
    }
}

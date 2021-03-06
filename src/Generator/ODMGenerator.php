<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Generator;

use Sonata\MediaBundle\Model\MediaInterface;

/**
 * @final since sonata-project/media-bundle 3.21.0
 */
class ODMGenerator implements GeneratorInterface
{
    /**
     * {@inheritdoc}
     */
    public function generatePath(MediaInterface $media)
    {
        $id = $media->getId();

        return sprintf('%s/%04s/%02s', $media->getContext(), substr($id, 0, 4), substr($id, 4, 2));
    }
}

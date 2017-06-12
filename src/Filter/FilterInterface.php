<?php

declare(strict_types=1);

namespace Jmoati\FFMpeg\Filter;

use Jmoati\FFMpeg\Data\FilterCollection;
use Jmoati\FFMpeg\Data\Media;

interface FilterInterface
{
    /**
     * @return string
     */
    public function __toString(): string;

    /**
     * @return Media
     */
    public function media(): Media;

    /**
     * @param FilterCollection $parent
     * @return FilterAbstract
     */
    public function setParent(FilterCollection $parent): FilterAbstract;

    /**
     * @return FilterCollection
     */
    public function parent(): FilterCollection;
}

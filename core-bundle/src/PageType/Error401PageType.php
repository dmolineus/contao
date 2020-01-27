<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\PageType;

use Contao\PageError401;

class Error401PageType extends AbstractSinglePageType implements HasLegacyPageInterface
{
    protected $features = [];

    public function getLegacyPageClass(): string
    {
        return PageError401::class;
    }
}

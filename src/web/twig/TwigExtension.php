<?php

namespace Newism\Imgix\web\twig;

use Craft;
use Newism\Imgix\Imgix;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
/**
 * Twig extension
 */
class TwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'imgix' => Imgix::getInstance()->imgix,
        ];
    }
}

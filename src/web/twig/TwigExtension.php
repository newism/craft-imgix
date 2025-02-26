<?php

namespace Newism\Imgix\web\twig;

use Craft;
use Newism\Imgix\Plugin;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Twig extension
 */
class TwigExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'imgix' => Plugin::getInstance()->imgix,
        ];
    }
}

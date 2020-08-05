<?php

declare(strict_types=1);

namespace Bolt\Article;

use Bolt\Common\Json;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    /** @var ArticleConfig */
    private $articleConfig;

    public function __construct(ArticleConfig $articleConfig)
    {
        $this->articleConfig = $articleConfig;
    }

    public function getFunctions(): array
    {
        $safe = [
            'is_safe' => ['html'],
        ];

        return [
            new TwigFunction('article_settings', [$this, 'articleSettings'], $safe),
        ];
    }

    public function articleSettings(): string
    {
        $settings = $this->articleConfig->getConfig();

        return Json::json_encode($settings, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_PRETTY_PRINT);
    }
}

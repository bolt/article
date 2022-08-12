<?php

declare(strict_types=1);

namespace Bolt\Article;

use Bolt\Common\Json;
use Symfony\Component\Filesystem\Path;
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
            new TwigFunction('article_includes', [$this, 'articleIncludes'], $safe),
        ];
    }

    public function articleSettings(): string
    {
        $settings = $this->articleConfig->getConfig();

        return Json::json_encode($settings, JSON_HEX_QUOT | JSON_HEX_APOS);
    }

    public function articleIncludes(): string
    {
        $used = $this->articleConfig->getConfig()['plugins'];
        $plugins = collect($this->articleConfig->getPlugins());

        $output = '';

        foreach ($used as $item) {
            if (! is_string($item) || ! $plugins->get($item)) {
                continue;
            }

            foreach ($plugins->get($item) as $file) {
                if (Path::getExtension($file) === 'css') {
                    $output .= sprintf('<link rel="stylesheet" href="/assets/article/plugins/%s">', $file);
                }
                if (Path::getExtension($file) === 'js') {
                    $output .= sprintf('<script src="/assets/article/plugins/%s"></script>', $file);
                }
                $output .= "\n";
            }
        }

        return $output;
    }
}

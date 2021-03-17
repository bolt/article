<?php

declare(strict_types=1);

namespace Bolt\Article;

use Bolt\Common\Json;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Twig\Environment;
use Webmozart\PathUtil\Path;

class TwigExtension extends AbstractExtension
{
    /** @var ArticleConfig */
    private $articleConfig;

    /** @var Environment */
    private $twig;

    public function __construct(ArticleConfig $articleConfig, Environment $twig)
    {
        $this->articleConfig = $articleConfig;
        $this->twig = $twig;
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

        if(array_key_exists('draggable', $settings)) {
            $loader = $this->twig->getLoader();
            foreach ($settings['draggable'] as $key => $component) {
                if ($loader->exists($component)) {
                    $settings['draggable'][$key] = $this->twig->render($component);
                }
            }
        }

        return Json::json_encode($settings, JSON_HEX_QUOT | JSON_HEX_APOS);
    }

    public function articleIncludes(): string
    {
        $output = $this->getPluginIncludes();
        $output .= sprintf($output, $this->getSettingsIncludes());

        return $output;
    }

    private function getSettingsIncludes(): string {
        $output = '';
        $settings = $this->articleConfig->getConfig();

        if(array_key_exists('draggable_menu', $settings)) {
            $output .= sprintf('<link rel="stylesheet" href="%s">', $settings['draggable_menu']['css']);
        }

        return $output;
    }

    private function getPluginIncludes(): string
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

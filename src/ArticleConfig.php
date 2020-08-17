<?php

declare(strict_types=1);

namespace Bolt\Article;

use Bolt\Extension\ExtensionRegistry;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ArticleConfig
{
    /** @var ExtensionRegistry */
    private $registry;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var CsrfTokenManagerInterface */
    private $csrfTokenManager;

    public function __construct(ExtensionRegistry $registry, UrlGeneratorInterface $urlGenerator, CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->registry = $registry;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function getConfig(): array
    {
        $extension = $this->registry->getExtension(Extension::class);

        return array_merge($this->getDefaults(), $extension->getConfig()['default']);
    }

    public function getPlugins(): array
    {
        $extension = $this->registry->getExtension(Extension::class);

        $plugins = $this->getDefaultPlugins();

        if (is_array($extension->getConfig()['plugins'])) {
            $plugins = array_merge($plugins, $extension->getConfig()['plugins']);
        }

        return $plugins;
    }

    public function getDefaults()
    {
        return [
            'image' => [
                'upload' => $this->urlGenerator->generate('bolt_article_upload', ['location' => 'files']),
                'select' => $this->urlGenerator->generate('bolt_article_images', [
                    '_csrf_token' => $this->csrfTokenManager->getToken('bolt_article')->getValue(),
                    'foo' => '1', // To ensure token is cut off correctly
                ]),
                'data' => [
                    '_csrf_token' => $this->csrfTokenManager->getToken('bolt_article')->getValue(),
                ],
                'multiple' => false,
            ],
            'minHeight' => '200px',
            'maxHeight' => '700px',
            'css' => '/assets/article/css/',
            'custom' => [
                'css' => [
                    '/assets/article/css/bolt-additions.css'
                ]
            ]
        ];
    }

    public function getDefaultPlugins()
    {
        return [
            'blockcode' => ['blockcode/blockcode.min.js'],
            'counter' => ['counter/counter.min.js'],
            'icons' => ['icons/icons.min.js'],
            'inlineformat' => ['inlineformat/inlineformat.min.js'],
            'reorder' => ['reorder/reorder.min.js'],
            'selector' => ['selector/selector.min.js'],
            'specialchars' => ['specialchars/specialchars.min.js'],
            'style' => ['style/style.min.js'],
            'tags' => ['tags/tags.min.js', 'tags/tags.min.css'],
            'underline' => ['underline/underline.min.js'],
            'variable' => ['variable/variable.min.js'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Bolt\Article;

use Bolt\Common\Arr;
use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Extension\ExtensionInterface;
use Bolt\Extension\ExtensionRegistry;
use Bolt\Storage\Query;
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

    /** @var Config */
    private $boltConfig;

    /** @var Query */
    private $query;

    /** @var ExtensionInterface */
    private $extension = null;

    /** @var array */
    private $config = null;

    /** @var array */
    private $plugins = null;

    public function __construct(ExtensionRegistry $registry, UrlGeneratorInterface $urlGenerator, CsrfTokenManagerInterface $csrfTokenManager, Config $boltConfig, Query $query)
    {
        $this->registry = $registry;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->boltConfig = $boltConfig;
        $this->query = $query;
    }

    public function getConfig(): array
    {
        if ($this->config) {
            return $this->config;
        }

        $extension = $this->getExtension();

        $this->config = array_replace_recursive($this->getDefaults(), $extension->getConfig()['default'], $this->getLinks());

        return $this->config;
    }

    public function getPlugins(): array
    {
        if ($this->plugins) {
            return $this->plugins;
        }

        $extension = $this->getExtension();

        $this->plugins = $this->getDefaultPlugins();

        if (is_array($extension->getConfig()['plugins'])) {
            $this->plugins = array_replace_recursive($plugins, $extension->getConfig()['plugins']);
        }

        return $this->plugins;
    }

    /**
     * This seems trivial, but it's a _huge_ performance boost to get this just once and hold on to it.
     *
     * @return ExtensionInterface|null
     */
    private function getExtension()
    {
        if (! $this->extension) {
            $this->extension = $this->registry->getExtension(Extension::class);
        }

        return $this->extension;
    }

    private function getDefaults(): array
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
                'thumbnail' => '1000×1000×max',
            ],
            'minHeight' => '200px',
            'maxHeight' => '700px',
            'css' => '/assets/article/css/',
            'custom' => [
                'css' => [
                    '/assets/article/css/bolt-additions.css',
                ],
            ],
            'toolbar' => [
                'stickyTopOffset' => 50,
            ],
        ];
    }

    private function getDefaultPlugins(): array
    {
        return [
            'blockcode' => ['blockcode/blockcode.min.js'],
            'buttonlink' => ['buttonlink/buttonlink.min.js'],
            'counter' => ['counter/counter.min.js'],
            'definedlinks' => ['definedlinks/definedlinks.min.js'],
            'handle' => ['handle/handle.min.js'],
            'icons' => ['icons/icons.min.js'],
            'inlineformat' => ['inlineformat/inlineformat.min.js'],
            'print' => ['print/print.min.js'],
            'reorder' => ['reorder/reorder.min.js'],
            'selector' => ['selector/selector.min.js'],
            'specialchars' => ['specialchars/specialchars.min.js'],
            'style' => ['style/style.min.js'],
            'tags' => ['tags/tags.min.js', 'tags/tags.min.css'],
            'underline' => ['underline/underline.min.js'],
            'variable' => ['variable/variable.min.js'],
        ];
    }

    private function getLinks(): array
    {
        $amount = 100;
        $params = [
            'status' => 'published',
            'returnmultiple' => true,
            'order' => '-modifiedAt',
        ];
        $contentTypes = $this->boltConfig->get('contenttypes')->where('viewless', false)->keys()->implode(',');

        $records = $this->query->getContentForTwig($contentTypes, $params)->setMaxPerPage($amount);

        $links = [
            '___' => [
                'name' => '(Choose an existing Record)',
                'url' => '',
            ],
        ];

        /** @var Content $record */
        foreach ($records as $record) {
            $extras = $record->getExtras();

            $links[$extras['title']] = [
                'name' => sprintf('%s [%s № %s]', $extras['title'], $extras['name'], $record->getId()),
                'url' => $extras['link'],
            ];
        }

        ksort($links, SORT_STRING | SORT_FLAG_CASE);

        return [
            'definedlinks' => [
                'items' => array_values($links),
            ],
        ];
    }
}

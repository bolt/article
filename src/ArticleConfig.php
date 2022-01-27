<?php

declare(strict_types=1);

namespace Bolt\Article;

use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Extension\ExtensionRegistry;
use Bolt\Storage\Query;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ArticleConfig
{
    private const CACHE_DURATION = 1800; // 30 minutes

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

    /** @var array */
    private $config = null;

    /** @var array */
    private $plugins = null;

    /** @var CacheInterface */
    private $cache;

    /** @var Security */
    private $security;

    public function __construct(
        ExtensionRegistry $registry,
        UrlGeneratorInterface $urlGenerator,
        CsrfTokenManagerInterface $csrfTokenManager,
        Config $boltConfig,
        Query $query,
        CacheInterface $cache,
        Security $security
    ) {
        $this->registry = $registry;
        $this->urlGenerator = $urlGenerator;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->boltConfig = $boltConfig;
        $this->query = $query;
        $this->cache = $cache;
        $this->security = $security;
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
            $this->plugins = array_replace_recursive($this->plugins, $extension->getConfig()['plugins']);
        }

        return $this->plugins;
    }

    private function getExtension()
    {
        return  $this->extension = $this->registry->getExtension(Extension::class);
    }

    private function getDefaults(): array
    {
        $defaults = [
            'image' => [
                'upload' => $this->urlGenerator->generate('bolt_article_image_upload', ['location' => 'files']),
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
            'filelink' => [
                'upload' => $this->urlGenerator->generate('bolt_article_file_upload', ['location' => 'files']),
                'select' => $this->urlGenerator->generate('bolt_article_files', [
                    '_csrf_token' => $this->csrfTokenManager->getToken('bolt_article')->getValue(),
                    'foo' => '1', // To ensure token is cut off correctly
                ]),
                'data' => [
                    '_csrf_token' => $this->csrfTokenManager->getToken('bolt_article')->getValue(),
                ],
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

        if (! $this->security->isGranted('upload')) {
            $defaults['imageUpload'] = null;
        }

        if (! $this->security->isGranted('list_files:files')) {
            $defaults['imageManagerJson'] = null;
        }

        return $defaults;
    }

    private function getDefaultPlugins(): array
    {
        return [
            'blockcode' => ['blockcode/blockcode.min.js'],
            'buttonlink' => ['buttonlink/buttonlink.min.js'],
            'carousel' => ['carousel/carousel.min.js', 'carousel/carousel.min.css'],
            'counter' => ['counter/counter.min.js'],
            'definedlinks' => ['definedlinks/definedlinks.min.js'],
            'filelink' => ['filelink/filelink.min.js'],
            'handle' => ['handle/handle.min.js'],
            'icons' => ['icons/icons.min.js'],
            'imageposition' => ['imageposition/imageposition.min.js'],
            'imageresize' => ['imageresize/imageresize.min.js'],
            'inlineformat' => ['inlineformat/inlineformat.min.js'],
            'makebutton' => ['makebutton/makebutton.min.js'],
            'math' => ['math/math.min.js'],
            'print' => ['print/print.min.js'],
            'removeformat' => ['removeformat/removeformat.min.js'],
            'reorder' => ['reorder/reorder.min.js'],
            'selector' => ['selector/selector.min.js'],
            'slideshow' => ['slideshow/slideshow.min.js', 'slideshow/slideshow.min.css'],
            'specialchars' => ['specialchars/specialchars.min.js'],
            'style' => ['style/style.min.js'],
            'tags' => ['tags/tags.min.js', 'tags/tags.min.css'],
            'textdirection' => ['textdirection/textdirection.min.js'],
            'underline' => ['underline/underline.min.js'],
            'variable' => ['variable/variable.min.js'],
        ];
    }

    private function getLinks(): array
    {
        return $this->cache->get('editor_insert_links', function (ItemInterface $item) {
            $item->expiresAfter(self::CACHE_DURATION);

            return $this->getLinksHelper();
        });
    }

    private function getLinksHelper(): array
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

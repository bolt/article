<?php

declare(strict_types=1);

namespace Bolt\Article;

use Bolt\Configuration\Config;
use Bolt\Entity\Content;
use Bolt\Extension\ExtensionRegistry;
use Bolt\Storage\Query;
use Pagerfanta\PagerfantaInterface;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ArticleConfig
{
    private const CACHE_DURATION = 1800; // 30 minutes

    /** @var array<string, null|bool|string|array<string, array<string, bool|string>|string>> */
    private ?array $config = null;

    /** @var array<string, string[]> */
    private ?array $plugins = null;

    public function __construct(
        private readonly ExtensionRegistry $registry,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly Config $boltConfig,
        private readonly Query $query,
        private readonly CacheInterface $cache,
        private readonly Security $security
    ) {
    }

    /**
     * @phpstan-ignore missingType.iterableValue (complex type)
     */
    public function getConfig(): array
    {
        if ($this->config) {
            return $this->config;
        }

        $extension = $this->getExtension();

        $this->config = array_replace_recursive($this->getDefaults(), $extension->getConfig()['default'], $this->getLinks());

        return $this->config;
    }

    /**
     * @phpstan-ignore missingType.iterableValue (complex type)
     */
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

    /**
     * @phpstan-ignore missingType.iterableValue (complex type)
     */
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

    /**
     * @return array<string, string[]>
     */
    private function getDefaultPlugins(): array
    {
        return [
            'blockcode' => ['blockcode/blockcode.min.js'],
            'buttonlink' => ['buttonlink/buttonlink.min.js'],
            'carousel' => ['carousel/carousel.min.js', 'carousel/carousel.min.css'],
            'clips' => ['clips/clips.min.js'],
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

    /**
     * @phpstan-ignore missingType.iterableValue (complex type)
     */
    private function getLinks(): array
    {
        return $this->cache->get('editor_insert_links', function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_DURATION);

            return $this->getLinksHelper();
        });
    }

    /**
     * @phpstan-ignore missingType.iterableValue (complex type)
     */
    private function getLinksHelper(): array
    {
        $amount = 100;
        $params = [
            'status' => 'published',
            'returnmultiple' => true,
            'order' => '-modifiedAt',
        ];
        $contentTypes = $this->boltConfig->get('contenttypes')->where('viewless', false)->keys()->implode(',');

        /** @var Content[]|PagerfantaInterface<Content> $records */
        $records = $this->query->getContentForTwig($contentTypes, $params) ?? [];
        if ($records instanceof PagerfantaInterface) {
            $records->setMaxPerPage($amount);
        }

        $links = [
            '___' => [
                'name' => '(Choose an existing Record)',
                'url' => '',
            ],
        ];

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

    private function getExtension(): Extension
    {
        /** @var Extension|null $extension */
        $extension = $this->registry->getExtension(Extension::class);

        return $extension ?? throw new RuntimeException('Redactor extension not registered');
    }
}

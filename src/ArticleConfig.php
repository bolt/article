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

    public function getDefaults()
    {
        return [
            'imageUpload' => $this->urlGenerator->generate('bolt_article_upload', ['location' => 'files']),
            'imageUploadParam' => 'file',
            'multipleUpload' => 'false',
            'imageData' => [
                '_csrf_token' => $this->csrfTokenManager->getToken('bolt_article')->getValue(),
            ],
            'minHeight' => '200px',
            'maxHeight' => '700px',
            'css' => '/assets/article/css/',
        ];
    }
}

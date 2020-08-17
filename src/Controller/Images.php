<?php

declare(strict_types=1);

namespace Bolt\Article\Controller;

use Bolt\Configuration\Config;
use Bolt\Controller\Backend\Async\AsyncZoneInterface;
use Bolt\Controller\CsrfTrait;
use Bolt\Twig\TextExtension;
use Bolt\Utils\ThumbnailHelper;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Tightenco\Collect\Support\Collection;

/**
 * @Security("is_granted('ROLE_ADMIN')")
 */
class Images implements AsyncZoneInterface
{
    use CsrfTrait;

    /** @var Config */
    private $config;

    /** @var TextExtension */
    private $textExtension;

    /** @var Request */
    private $request;

    /** @var ThumbnailHelper */
    private $thumbnailHelper;

    public function __construct(Config $config, CsrfTokenManagerInterface $csrfTokenManager, TextExtension $textExtension, RequestStack $requestStack, UrlGeneratorInterface $urlGenerator, ThumbnailHelper $thumbnailHelper)
    {
        $this->config = $config;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->textExtension = $textExtension;
        $this->request = $requestStack->getCurrentRequest();
        $this->thumbnailHelper = $thumbnailHelper;
    }

    /**
     * @Route("/article_images", name="bolt_article_images", methods={"GET"})
     */
    public function getImagesList(Request $request): JsonResponse
    {
        try {
            $this->validateCsrf('bolt_article');
        } catch (InvalidCsrfTokenException $e) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $locationName = $this->request->query->get('location', 'files');
        $type = $this->request->query->get('type', '');

        $path = $this->config->getPath($locationName, true);

        $files = $this->getFilesIndex($path, $type);

        return new JsonResponse($files);
    }

    private function getFilesIndex(string $path, string $type): Collection
    {
        $glob = '*.{jpg,png,gif,jpeg,svg}';

        $files = [];

        foreach ($this->findFiles($path, $glob) as $file) {
            // @todo Make the size of the images configurable

            $files[] = [
                'thumb' => $this->thumbnailHelper->path($file->getRelativePathname(), 400, 300, null, null, 'crop'),
                'url' => $this->thumbnailHelper->path($file->getRelativePathname(), 1000, 1000, null, null, 'fit'),
            ];
        }

        return new Collection($files);
    }

    private function findFiles(string $path, ?string $glob = null): Finder
    {
        $finder = new Finder();
        $finder->in($path)->depth('< 3')->sortByType()->files();

        if ($glob) {
            $finder->name($glob);
        }

        return $finder;
    }
}

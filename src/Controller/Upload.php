<?php

declare(strict_types=1);

namespace Bolt\Article\Controller;

use Bolt\Article\ArticleConfig;
use Bolt\Configuration\Config;
use Bolt\Controller\Backend\Async\AsyncZoneInterface;
use Bolt\Controller\CsrfTrait;
use Bolt\Twig\TextExtension;
use Cocur\Slugify\Slugify;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sirius\Upload\Handler;
use Sirius\Upload\Result\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Webmozart\PathUtil\Path;

/**
 * @Security("is_granted('ROLE_ADMIN')")
 */
class Upload implements AsyncZoneInterface
{
    use CsrfTrait;

    /** @var Config */
    private $config;

    /** @var TextExtension */
    private $textExtension;

    /** @var Request */
    private $request;

    /** @var ArticleConfig */
    private $articleConfig;

    public function __construct(Config $config, CsrfTokenManagerInterface $csrfTokenManager, TextExtension $textExtension, RequestStack $requestStack, ArticleConfig $articleConfig)
    {
        $this->config = $config;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->textExtension = $textExtension;
        $this->request = $requestStack->getCurrentRequest();
        $this->articleConfig = $articleConfig;
    }

    /**
     * @Route("/article_upload", name="bolt_article_upload", methods={"POST"})
     */
    public function handleUpload(Request $request): JsonResponse
    {
        try {
            $this->validateCsrf('bolt_article');
        } catch (InvalidCsrfTokenException $e) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $locationName = $this->request->query->get('location', '');
        $path = $this->request->query->get('path', '');

        $target = $this->config->getPath($locationName, true, $path);

        $uploadHandler = new Handler($target, [
            Handler::OPTION_AUTOCONFIRM => true,
            Handler::OPTION_OVERWRITE => false,
        ]);

        $acceptedFileTypes = array_merge($this->config->getMediaTypes()->toArray(), $this->config->getFileTypes()->toArray());
        $maxSize = $this->config->getMaxUpload();
        $uploadHandler->addRule(
            'extension',
            [
                'allowed' => $acceptedFileTypes,
            ],
            'The file for field \'{label}\' was <u>not</u> uploaded. It should be a valid file type. Allowed are <code>' . implode('</code>, <code>', $acceptedFileTypes) . '.',
            'Upload file'
        );

        $uploadHandler->addRule(
            'size',
            ['size' => $maxSize],
            'The file for field \'{label}\' was <u>not</u> uploaded. The upload can have a maximum filesize of <b>' . $this->textExtension->formatBytes($maxSize) . '</b>.',
            'Upload file'
        );

        $uploadHandler->setSanitizerCallback(function ($name) {
            return $this->sanitiseFilename($name);
        });

        try {
            /** @var File $result */
            $result = $uploadHandler->process($request->files->get('file'));
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Ensure the upload does NOT exceed the maximum filesize of ' . $this->textExtension->formatBytes($maxSize) . ', and that the destination folder (on the webserver) is writable.',
            ], Response::HTTP_OK);
        }

        if ($result->isValid()) {
            $thumbnail = '/thumbs/' . $this->articleConfig->getConfig()['image']['thumbnail'] . '/';

            $resultMessage = [
                'filekey' => [
                    'url' => $thumbnail . $result->name,
                    'id' => 1,
                ],
            ];

            return new JsonResponse($resultMessage, Response::HTTP_OK);
        }

        // image was not moved to the container, where are error messages
        $messages = $result->getMessages();

        return new JsonResponse([
            'error' => true,
            'message' => implode(', ', $messages),
        ], Response::HTTP_BAD_REQUEST);
    }

    private function sanitiseFilename(string $filename): string
    {
        $extensionSlug = new Slugify(['regexp' => '/([^a-z0-9]|-)+/']);
        $filenameSlug = new Slugify(['lowercase' => false]);

        $extension = $extensionSlug->slugify(Path::getExtension($filename));
        $filename = $filenameSlug->slugify(Path::getFilenameWithoutExtension($filename));

        return $filename . '.' . $extension;
    }
}

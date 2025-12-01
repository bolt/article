<?php

declare(strict_types=1);

namespace Bolt\Article\Controller;

use Bolt\Article\ArticleConfig;
use Bolt\Configuration\Config;
use Bolt\Controller\Backend\Async\AsyncZoneInterface;
use Bolt\Controller\CsrfTrait;
use Bolt\Twig\TextExtension;
use Cocur\Slugify\Slugify;
use Sirius\Upload\Handler;
use Sirius\Upload\Result\File;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[IsGranted('upload')]
class Upload implements AsyncZoneInterface
{
    use CsrfTrait;

    public function __construct(
        private readonly Config $config,
        private readonly TextExtension $textExtension,
        private readonly ArticleConfig $articleConfig,
        CsrfTokenManagerInterface $csrfTokenManager,
    ) {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    #[Route('/bolt_article_image_upload', name: 'bolt_article_image_upload', methods: [Request::METHOD_POST])]
    public function handleImageUpload(Request $request): JsonResponse
    {
        return $this->handleUpload($request, 'image');
    }

    #[Route('/bolt_article_file_upload', name: 'bolt_article_file_upload', methods: [Request::METHOD_POST])]
    public function handleFileUpload(Request $request): JsonResponse
    {
        return $this->handleUpload($request, 'file');
    }

    private function handleUpload(Request $request, string $type = 'image'): JsonResponse
    {
        try {
            $this->validateCsrf($request, 'bolt_article');
        } catch (InvalidCsrfTokenException) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $locationName = $request->query->getString('location');
        $path = $request->query->getString('path');

        $target = $this->config->getPath($locationName, true, $path);

        $uploadHandler = new Handler($target, [
            Handler::OPTION_AUTOCONFIRM => true,
            Handler::OPTION_OVERWRITE => false,
        ]);

        if ($type === 'image') {
            $acceptedFileTypes = $this->config->getMediaTypes()->toArray();
            $filenamePrefix = '/thumbs/' . $this->articleConfig->getConfig()['image']['thumbnail'] . '/';
        } else {
            $acceptedFileTypes = array_merge($this->config->getMediaTypes()->toArray(), $this->config->getFileTypes()->toArray());
            $filenamePrefix = '/files/';
        }

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

        $uploadHandler->setSanitizerCallback($this->sanitiseFilename(...));

        try {
            /** @var File $result */
            $result = $uploadHandler->process($request->files->get('file'));

            // Clear the 'files' from the superglobals. We do this, so that we prevent breakage
            // later on, should we do a `Request::createFromGlobals();`
            // @see: https://github.com/bolt/core/issues/2027
            $_FILES = [];
        } catch (Throwable) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Ensure the upload does NOT exceed the maximum filesize of ' . $this->textExtension->formatBytes($maxSize) . ', and that the destination folder (on the webserver) is writable.',
            ], Response::HTTP_OK);
        }

        if ($result->isValid()) {
            $resultMessage = [
                'filekey' => [
                    'url' => $filenamePrefix . $result->name,
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

<?php

declare(strict_types=1);

namespace Bolt\Article;

use Bolt\Extension\BaseExtension;
use Symfony\Component\Filesystem\Filesystem;

class Extension extends BaseExtension
{
    public function getName(): string
    {
        return 'Bolt Extension to add the Article FieldType';
    }

    public function initialize(): void
    {
        $this->addTwigNamespace('article');
        $this->addWidget(new ArticleInjectorWidget());
    }

    public function install(): void
    {
        /** @var string $projectDir */
        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        /** @var string $public */
        $public = $this->getContainer()->getParameter('bolt.public_folder');

        $source = dirname(__DIR__) . '/assets/';
        $destination = $projectDir . '/' . $public . '/assets/';

        $filesystem = new Filesystem();
        $filesystem->mirror($source, $destination);
    }
}

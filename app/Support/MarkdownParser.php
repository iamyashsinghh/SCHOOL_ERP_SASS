<?php

namespace App\Support;

use App\Support\CommonMarkExtensions\MathExpressionExtension;
use App\Support\CommonMarkExtensions\SkipDomainsLinkRenderer;
use Illuminate\Support\Arr;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\DefaultAttributes\DefaultAttributesExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

trait MarkdownParser
{
    public function parse(?string $markdown = null, array $options = []): string
    {
        $skipEmbeddedLinks = Arr::get($options, 'skip_embedded_links', false);

        $config = [
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'external_link' => [
                'internal_hosts' => config('app.url'),
                'open_in_new_window' => true,
                'html_class' => 'external-link',
                'nofollow' => '',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ];

        $environment = new Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new ExternalLinkExtension);
        $environment->addExtension(new AttributesExtension);
        $environment->addExtension(new DefaultAttributesExtension);
        $environment->addExtension(new MathExpressionExtension);

        if ($skipEmbeddedLinks) {
            $environment->addRenderer(Link::class, new SkipDomainsLinkRenderer([
                'youtube.com', 'youtu.be',
                'x.com', 'twitter.com',
                'facebook.com', 'fb.watch',
            ]));
        }

        $converter = new MarkdownConverter($environment);

        $markdown = $this->normalizeMarkdownExceptTables($markdown ?? '');

        return $converter->convert($markdown);
    }

    private function normalizeMarkdownExceptTables(string $markdown): string
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        $placeholders = [];

        $stash = function (string $block) use (&$placeholders) {
            $token = '__TABLE_BLOCK_'.count($placeholders).'__';
            $placeholders[$token] = $block;

            return $token;
        };

        $markdown = preg_replace_callback('/```.*?```/s', function ($m) use ($stash) {
            return $stash($m[0]);
        }, $markdown);

        $tablePattern = '/(?m)
            ^\|.*\n                     # header line
            ^\|\s*[:\- ]+\|\s*.*\n      # separator
            (?:^\|.*\n)*                # body lines
        /x';

        $markdown = preg_replace_callback($tablePattern, function ($m) use ($stash) {
            return $stash($m[0]);
        }, $markdown);

        $markdown = preg_replace('/([^\n])\n([^\n])/m', "$1\n\n$2", $markdown);

        if (! empty($placeholders)) {
            $markdown = strtr($markdown, $placeholders);
        }

        return $markdown;
    }
}

<?php

namespace App\Support\CommonMarkExtensions;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\CommonMark\Renderer\Inline\LinkRenderer as CoreLinkRenderer;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;

final class SkipDomainsLinkRenderer implements ConfigurationAwareInterface, NodeRendererInterface
{
    /** @var string[] */
    private array $blocked;

    private CoreLinkRenderer $fallback;

    public function __construct(array $blocked)
    {
        $this->blocked = array_map('strtolower', $blocked);
        $this->fallback = new CoreLinkRenderer;
    }

    // ⬇️ environment will call this; forward to fallback so its $config is set
    public function setConfiguration(ConfigurationInterface $configuration): void
    {
        $this->fallback->setConfiguration($configuration);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (! $node instanceof Link) {
            throw new \InvalidArgumentException('Incompatible node type: '.get_class($node));
        }

        $url = $node->getUrl() ?? '';
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        $host = strtolower($host);
        $host = preg_replace('/^www\./', '', $host); // strip leading www.

        // match exact host or any subdomain of a blocked host
        foreach ($this->blocked as $blockedHost) {
            if ($host === $blockedHost || str_ends_with($host, '.'.$blockedHost)) {
                // render as plain text (escaped)
                return htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        }

        // otherwise, render the normal link
        return $this->fallback->render($node, $childRenderer);
    }
}

<?php

namespace App\Twig;

use App\Service\MessageContentRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MessageContentExtension extends AbstractExtension
{
    public function __construct(private readonly MessageContentRenderer $messageContentRenderer)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('render_message_content', $this->renderMessageContent(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderMessageContent(?string $content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        return $this->messageContentRenderer->render($content);
    }
}

<?php

namespace LukaLtaApi\Value\Blog;

use Fig\Http\Message\StatusCodeInterface;
use League\CommonMark\CommonMarkConverter;
use LukaLtaApi\Exception\ApiValidationException;

class BlogContent
{
    public function __construct(
        private readonly string $content,
    ) {
        if (empty($content)) {
            throw new ApiValidationException(
                'Content cannot be empty.',
                StatusCodeInterface::STATUS_BAD_REQUEST
            );
        }
    }

    public static function fromRaw(string $raw): self
    {
        return new self($raw);
    }

    public function toHtml(): string
    {
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($this->content);
    }

    public function getContent(): string
    {
        return $this->content;
    }
}

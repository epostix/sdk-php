<?php

declare(strict_types=1);

namespace Epostix\Tests\Support;

use Nyholm\Psr7\Stream;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class UnseekableStreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        return new UnseekableStream(Stream::create($content));
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        return new UnseekableStream(Stream::create(file_get_contents($filename)));
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        return new UnseekableStream(Stream::create($resource));
    }
}

<?php

declare(strict_types=1);

namespace Epostix;

use Psr\Http\Message\StreamInterface;

final readonly class RawContent
{
    public function __construct(
        public StreamInterface $body,
        public string $contentType,
        public ResponseMetadata $metadata,
    ) {}

    public function contents(int $maximumBytes): string
    {
        if ($maximumBytes <= 0) {
            throw new EpostixConfigurationError('contents requires a positive maximumBytes');
        }

        $buffer = '';

        while (!$this->body->eof()) {
            $chunk = $this->body->read($maximumBytes + 1 - strlen($buffer));

            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;

            if (strlen($buffer) > $maximumBytes) {
                throw new EpostixConfigurationError(
                    'the raw message is larger than the ' . $maximumBytes . ' byte cap you set',
                );
            }
        }

        return $buffer;
    }
}

<?php

declare(strict_types=1);

namespace Epostix;

use Epostix\Generated\Operations;

final class InboundResource extends Generated\Resource\InboundResource
{
    public function getInboundEmailRaw(string $inbound_id, ?RequestOptions $options = null): RawContent
    {
        $raw = $this->transport->sendOnce(
            Operations::get('getInboundEmailRaw'),
            new ExecuteInput(path: [$inbound_id]),
            $options,
        );

        return new RawContent(
            $raw->response->getBody(),
            $raw->response->getHeaderLine('Content-Type'),
            $raw->metadata,
        );
    }
}

<?php

declare(strict_types=1);

namespace Epostix;

use Epostix\Generated\AttachmentResponse;
use Epostix\Generated\AttachmentUpload;
use Epostix\Generated\Operations;

final class AttachmentsResource extends Generated\Resource\AttachmentsResource
{
    public function uploadAttachment(
        string $filename,
        string $contents,
        string $contentType,
        ?RequestOptions $options = null,
    ): AttachmentResponse {
        AttachmentLimits::assertWithinLimits([$filename => strlen($contents)]);

        $body = new AttachmentUpload(
            content: base64_encode($contents),
            content_type: $contentType,
            filename: $filename,
        );

        $result = $this->transport->execute(
            Operations::get('uploadAttachment'),
            new ExecuteInput(body: $body->toWire()),
            $options ?? new RequestOptions(retryPolicy: RetryPolicy::upload()),
        );

        return AttachmentResponse::fromWire(Wire::asArray($result->body), $result->metadata);
    }
}

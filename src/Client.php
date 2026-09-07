<?php

declare(strict_types=1);

namespace Epostix;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;

final class Client
{
    public const VERSION = '1.0.0';
    public const PRODUCTION_BASE_URL = 'https://api.epostix.com/v1';
    public const STAGING_BASE_URL = 'https://api.staging.epostix.com/v1';
    public const SANDBOX_DOMAIN = 'epostix.dev';
    public const DEDUPLICATION_SCOPE = 'api_key + environment + method + path + key, 24h';

    public readonly Generated\Resource\AnalyticsResource $analytics;
    public readonly Generated\Resource\ApiKeysResource $apiKeys;
    public readonly AttachmentsResource $attachments;
    public readonly Generated\Resource\BroadcastsResource $broadcasts;
    public readonly Generated\Resource\ContactsResource $contacts;
    public readonly Generated\Resource\DomainsResource $domains;
    public readonly Generated\Resource\EmailsResource $emails;
    public readonly Generated\Resource\EventsResource $events;
    public readonly InboundResource $inbound;
    public readonly Generated\Resource\SuppressionsResource $suppressions;
    public readonly Generated\Resource\TagsResource $tags;
    public readonly Generated\Resource\TemplatesResource $templates;
    public readonly Generated\Resource\WebhooksResource $webhooks;

    public readonly KeyEnvironment $keyEnvironment;

    private readonly Transport $transportInstance;

    public function __construct(
        private readonly string $apiKey,
        private readonly ?ClientOptions $options = null,
    ) {
        $options ??= new ClientOptions();

        $suffix = $options->userAgentSuffix === null || $options->userAgentSuffix === ''
            ? ''
            : ' ' . $options->userAgentSuffix;

        $this->transportInstance = new Transport(
            $options->httpClient ?? Psr18ClientDiscovery::find(),
            $options->requestFactory ?? Psr17FactoryDiscovery::findRequestFactory(),
            $options->streamFactory ?? Psr17FactoryDiscovery::findStreamFactory(),
            $apiKey,
            rtrim($options->baseUrl ?? self::PRODUCTION_BASE_URL, '/'),
            $options->retryPolicy ?? RetryPolicy::default(),
            $options->defaultHeaders,
            'epostix-php/' . self::VERSION . $suffix,
            $options->automaticIdempotencyKeys,
            $options->clock ?? new SystemClock(),
            $options->random ?? new SystemRandomSource(),
            $options->sleeper ?? new SliceSleeper(),
            self::validate($apiKey, $options),
        );

        $this->keyEnvironment = KeyEnvironment::of($apiKey);

        $this->analytics = new Generated\Resource\AnalyticsResource($this->transportInstance);
        $this->apiKeys = new Generated\Resource\ApiKeysResource($this->transportInstance);
        $this->attachments = new AttachmentsResource($this->transportInstance);
        $this->broadcasts = new Generated\Resource\BroadcastsResource($this->transportInstance);
        $this->contacts = new Generated\Resource\ContactsResource($this->transportInstance);
        $this->domains = new Generated\Resource\DomainsResource($this->transportInstance);
        $this->emails = new Generated\Resource\EmailsResource($this->transportInstance);
        $this->events = new Generated\Resource\EventsResource($this->transportInstance);
        $this->inbound = new InboundResource($this->transportInstance);
        $this->suppressions = new Generated\Resource\SuppressionsResource($this->transportInstance);
        $this->tags = new Generated\Resource\TagsResource($this->transportInstance);
        $this->templates = new Generated\Resource\TemplatesResource($this->transportInstance);
        $this->webhooks = new Generated\Resource\WebhooksResource($this->transportInstance);
    }

    public function transport(): Transport
    {
        return $this->transportInstance;
    }

    public function withApiKey(string $apiKey): self
    {
        return new self($apiKey, $this->options);
    }

    private static function validate(string $apiKey, ClientOptions $options): ?EpostixConfigurationError
    {
        if (trim($apiKey) === '') {
            return new EpostixConfigurationError('an API key is required');
        }

        if (str_starts_with($apiKey, 'Bearer ')) {
            return new EpostixConfigurationError(
                'pass the API key alone; the client adds the Bearer prefix itself',
            );
        }

        $baseUrl = $options->baseUrl;

        if ($baseUrl !== null && !str_starts_with($baseUrl, 'http://') && !str_starts_with($baseUrl, 'https://')) {
            return new EpostixConfigurationError('baseUrl must be an absolute http or https URL');
        }

        return null;
    }
}

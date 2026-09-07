<?php

declare(strict_types=1);

namespace Epostix\Tests;

use Epostix\Attachment;
use Epostix\Generated\AttachmentContentDisposition;
use Epostix\Generated\EmailCreate;
use Epostix\Generated\EmailUpdate;
use Epostix\InlineAttachment;
use Epostix\Mailbox;
use Epostix\Optional;
use Epostix\Tests\Support\Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SerializationTest extends TestCase
{
    private const CLASSES = [
        'EmailCreate' => EmailCreate::class,
        'EmailUpdate' => EmailUpdate::class,
        'Attachment' => InlineAttachment::class,
    ];

    public static function cases(): array
    {
        $provided = [];

        foreach (Fixtures::load('serialization')['cases'] as $case) {
            $provided[$case['name']] = [$case];
        }

        return $provided;
    }

    #[DataProvider('cases')]
    public function testTheWireBodyMatchesTheSharedCorpus(array $case): void
    {
        $class = self::CLASSES[$case['type']] ?? null;
        self::assertNotNull($class, 'unmapped fixture type ' . $case['type']);

        $actual = self::plain($class::fromWire($case['input'])->toWire());

        foreach ($case['absentKeys'] ?? [] as $key) {
            self::assertArrayNotHasKey($key, $actual, $key . ' must be absent from the wire body');
        }

        foreach ($case['expected'] as $key => $expected) {
            self::assertArrayHasKey($key, $actual, $key . ' is missing from the wire body');

            if (self::sameInstant($key, $expected, $actual[$key])) {
                continue;
            }

            self::assertSame(
                json_encode($expected, JSON_THROW_ON_ERROR),
                json_encode($actual[$key], JSON_THROW_ON_ERROR),
                'field ' . $key,
            );
        }
    }

    public function testAnUnsetOptionalOmitsTheKeyEntirely(): void
    {
        self::assertArrayNotHasKey('scheduled_at', (new EmailUpdate())->toWire());
    }

    public function testAnExplicitNullSendsTheEmailNow(): void
    {
        $wire = (new EmailUpdate(scheduled_at: Optional::null()))->toWire();

        self::assertArrayHasKey('scheduled_at', $wire);
        self::assertNull($wire['scheduled_at']);
    }

    public function testAValueReschedules(): void
    {
        $at = new \DateTimeImmutable('2026-09-07T10:00:00+00:00');
        $wire = (new EmailUpdate(scheduled_at: Optional::of($at)))->toWire();

        self::assertSame('2026-09-07T10:00:00+00:00', $wire['scheduled_at']);
    }

    public function testFalseAndTheEmptyStringReachTheWire(): void
    {
        $wire = (new EmailCreate(
            from: Mailbox::address('a@example.com'),
            subject: 's',
            to: 'b@example.com',
            allow_duplicate: false,
            text: '',
        ))->toWire();

        self::assertArrayHasKey('allow_duplicate', $wire);
        self::assertFalse($wire['allow_duplicate']);
        self::assertArrayHasKey('text', $wire);
        self::assertSame('', $wire['text']);
    }

    public function testAMailboxEmitsTheNarrowestForm(): void
    {
        $bare = (new EmailCreate(from: Mailbox::address('a@example.com'), subject: 's', to: 'b@example.com'))
            ->toWire();

        self::assertSame('a@example.com', $bare['from']);

        $named = (new EmailCreate(from: Mailbox::named('Billing', 'a@example.com'), subject: 's', to: 'b@example.com'))
            ->toWire();

        self::assertSame(['email' => 'a@example.com', 'name' => 'Billing'], $named['from']);
    }

    public function testRecipientsAreAlwaysAnArrayOfPlainStrings(): void
    {
        $single = (new EmailCreate(from: 'a@example.com', subject: 's', to: 'b@example.com'))->toWire();
        self::assertSame(['b@example.com'], $single['to']);

        $many = (new EmailCreate(
            from: 'a@example.com',
            subject: 's',
            to: ['one@example.com', 'two@example.com'],
        ))->toWire();

        self::assertSame(['one@example.com', 'two@example.com'], $many['to']);
    }

    public function testADefaultTheCallerDidNotSetIsNeverSent(): void
    {
        $wire = Attachment::fromContents('r.pdf', "\x00\x01\x02", 'application/pdf')->toWire();

        self::assertArrayNotHasKey('content_disposition', $wire);
        self::assertSame(['filename', 'content', 'content_type'], array_keys($wire));
    }

    public function testAttachmentVariantsSerializeDistinctly(): void
    {
        self::assertSame(
            ['attachment_id' => 'att_9'],
            Attachment::fromUploadedId('att_9')->toWire(),
        );

        $remote = Attachment::fromRemoteUrl('r.pdf', 'https://example.com/r.pdf')->toWire();

        self::assertSame('https://example.com/r.pdf', $remote['content_url']);
        self::assertArrayNotHasKey('content', $remote);

        $inline = Attachment::fromContents(
            'logo.png',
            'bytes',
            'image/png',
            AttachmentContentDisposition::INLINE,
            'logo',
        )->toWire();

        self::assertSame('inline', $inline['content_disposition']);
        self::assertSame('logo', $inline['content_id']);
    }

    public function testAnEmptyBodyEncodesAsAJsonObject(): void
    {
        self::assertSame('{}', \Epostix\Wire::encodeBody((new EmailUpdate())->toWire()));
    }

    private static function sameInstant(string $key, mixed $expected, mixed $actual): bool
    {
        if (!str_ends_with($key, '_at') || !is_string($expected) || !is_string($actual)) {
            return false;
        }

        $left = strtotime($expected);
        $right = strtotime($actual);

        return $left !== false && $right !== false && $left === $right;
    }

    private static function plain(array $wire): array
    {
        return json_decode(json_encode($wire, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }
}

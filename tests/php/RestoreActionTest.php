<?php

namespace SilverStripe\Versioned\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\RestoreAction;
use SilverStripe\Versioned\Tests\VersionedTest\TestObject;

class RestoreActionTest extends SapphireTest
{
    protected $usesDatabase = true;

    public static $extra_dataobjects = [
        TestObject::class,
    ];

    /**
     * The restore message is rendered as HTML, so any user-supplied Title must be
     * escaped to prevent stored XSS (e.g. an <iframe srcdoc> or formaction payload).
     */
    public function testRestoreMessageEscapesTitle(): void
    {
        $payload = '<iframe srcdoc="<script>alert(\'xss\')</script>"></iframe>';

        $original = new TestObject();
        $original->Title = $payload;
        $original->URLSegment = 'safe-segment';

        $restored = new TestObject();
        $restored->Title = $payload;
        $restored->URLSegment = 'safe-segment';

        $message = RestoreAction::getRestoreMessage($original, $restored);

        $this->assertStringNotContainsString('<iframe', $message['text']);
        $this->assertStringNotContainsString('<script>', $message['text']);
        $this->assertStringContainsString('&lt;iframe', $message['text']);
    }

    /**
     * When a restored record's Title differs from the original, the message reports the
     * new title in a second spot ("...restored with a new Name (<title>)"). That value
     * comes from a different code path to the one above, so it needs escaping too.
     */
    public function testRestoreMessageEscapesChangedTitleValue(): void
    {
        $payload = '<button formaction="javascript:alert(1)">Click</button>';

        $original = new TestObject();
        $original->Title = 'Original Title';
        $original->URLSegment = 'same-segment';

        $restored = new TestObject();
        $restored->Title = $payload;
        $restored->URLSegment = 'same-segment';

        $message = RestoreAction::getRestoreMessage($original, $restored);

        $this->assertStringNotContainsString('<button', $message['text']);
        $this->assertStringNotContainsString('formaction="', $message['text']);
        $this->assertStringContainsString('&lt;button', $message['text']);
    }
}

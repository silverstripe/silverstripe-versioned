<?php


namespace SilverStripe\Versioned\GraphQL\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\GraphQL\Operations\AbstractPublishOperationCreator;
use SilverStripe\Versioned\Tests\GraphQL\Fake\Fake;
use SilverStripe\Versioned\Tests\GraphQL\Fake\FakeResolveInfo;
use SilverStripe\Versioned\Versioned;
use InvalidArgumentException;
use Exception;

class VersionedResolverTest extends SapphireTest
{
    public function testCopyToStage()
    {
        /* @var Fake|Versioned $record */
        $record = new Fake();
        $record->Name = 'First';
        $record->write(); // v1

        $this->logInWithPermission('ADMIN');
        $member = Security::getCurrentUser();
        $resolve = VersionedResolver::resolveCopyToStage(['dataClass' => Fake::class]);
        $resolve(
            null,
            [
                'input' => [
                    'fromStage' => Versioned::DRAFT,
                    'toStage' => Versioned::LIVE,
                    'id' => $record->ID,
                ],
            ],
            [ 'currentUser' => $member ],
            new FakeResolveInfo()
        );
        $recordLive = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);
        $this->assertNotNull($recordLive);
        $this->assertEquals($record->ID, $recordLive->ID);

        $record->Name = 'Second';
        $record->write();
        $newVersion = $record->Version;

        $recordLive = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);
        $this->assertEquals('First', $recordLive->Title);

        // Invoke publish
        $resolve(
            null,
            [
                'input' => [
                    'fromVersion' => $newVersion,
                    'toStage' => Versioned::LIVE,
                    'id' => $record->ID,
                ],
            ],
            [ 'currentUser' => $member ],
            new FakeResolveInfo()
        );
        $recordLive = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);
        $this->assertEquals('Second', $recordLive->Title);

        // Test error
        $this->expectException(InvalidArgumentException::class);
        $resolve(
            null,
            [
                'input' => [
                    'toStage' => Versioned::DRAFT,
                    'id' => $record->ID,
                ],
            ],
            [ 'currentUser' => new Member() ],
            new FakeResolveInfo()
        );
    }

    public function testPublish()
    {
        $record = new Fake();
        $record->Name = 'First';
        $record->write();

        $result = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);

        $this->assertNull($result);
        $this->logInWithPermission('ADMIN');
        $member = Security::getCurrentUser();
        $resolve = VersionedResolver::resolvePublishOperation([
            'dataClass' => Fake::class,
            'action' => AbstractPublishOperationCreator::ACTION_PUBLISH
        ]);
        $resolve(
            null,
            [
                'id' => $record->ID
            ],
            [ 'currentUser' => $member ],
            new FakeResolveInfo()
        );
        $result = Versioned::get_by_stage(Fake::class, Versioned::LIVE)
            ->byID($record->ID);

        $this->assertNotNull($result);
        $this->assertInstanceOf(Fake::class, $result);
        $this->assertEquals('First', $result->Name);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageRegExp('/^Not allowed/');
        $resolve(
            null,
            [
                'id' => $record->ID
            ],
            [ 'currentUser' => new Member() ],
            new FakeResolveInfo()
        );
    }
}

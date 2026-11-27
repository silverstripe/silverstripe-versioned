<?php

namespace SilverStripe\Versioned\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Validation\ValidationException;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\Versioned\Tests\RecursivePublishablePermissionTest\PublishOwner;
use SilverStripe\Versioned\Tests\RecursivePublishablePermissionTest\RestrictedOwned;
use SilverStripe\Versioned\Tests\VersionedGridFieldTest\TestController;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Versioned\VersionedGridFieldItemRequest;

class RecursivePublishablePermissionTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        PublishOwner::class,
        RestrictedOwned::class,
    ];

    public static function provideCanPublishRecursive(): array
    {
        return [
            'member wrote the owned draft but cannot publish it' => [
                'permission' => 'OWNER_PUBLISH',
                'publishOwned' => false,
                'expected' => false,
            ],
            'member can publish the owned record' => [
                'permission' => 'OWNED_PUBLISH',
                'publishOwned' => false,
                'expected' => true,
            ],
            'owned record already published' => [
                'permission' => 'OWNER_PUBLISH',
                'publishOwned' => true,
                'expected' => true,
            ],
            'owner not publishable by the member' => [
                'permission' => 'NO_PUBLISH',
                'publishOwned' => false,
                'expected' => false,
            ],
        ];
    }

    #[DataProvider('provideCanPublishRecursive')]
    public function testCanPublishRecursive(string $permission, bool $publishOwned, bool $expected): void
    {
        $member = $this->createMemberWithPermission($permission);
        $owned = RestrictedOwned::create(['Title' => 'Owned']);
        $this->writeAs($owned, $member);
        $owner = PublishOwner::create(['Title' => 'Owner', 'OwnedID' => $owned->ID]);
        $owner->write();
        if ($publishOwned) {
            $owned->publishRecursive();
        }
        $this->assertSame($expected, $owner->canPublishRecursive($member));
    }

    public function testValidatePublishRecursive(): void
    {
        $owned = RestrictedOwned::create(['Title' => 'Blocked image']);
        $owned->write();
        $owner = PublishOwner::create(['Title' => 'My page', 'OwnedID' => $owned->ID]);
        $owner->write();
        $allowed = $this->createMemberWithPermission('OWNED_PUBLISH');
        $this->assertTrue($owner->validatePublishRecursive($allowed)->isValid());
        $blocked = $this->createMemberWithPermission('OWNER_PUBLISH');
        $result = $owner->validatePublishRecursive($blocked);
        $this->assertFalse($result->isValid());
        $this->assertSame($result->isValid(), $owner->canPublishRecursive($blocked));
        $messages = array_column($result->getMessages(), 'message');
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('"My page"', $messages[0]);
        $this->assertStringContainsString('"Blocked image"', $messages[0]);
        $denied = $this->createMemberWithPermission('NO_PUBLISH');
        $result = $owner->validatePublishRecursive($denied);
        $this->assertFalse($result->isValid());
        $this->assertSame(
            ['You do not have permission to publish "My page".'],
            array_column($result->getMessages(), 'message')
        );
    }

    public function testValidatePublishRecursiveCapsBlockedItems(): void
    {
        $owner = PublishOwner::create(['Title' => 'My page']);
        $owner->write();
        for ($i = 1; $i <= 12; $i++) {
            RestrictedOwned::create(['Title' => "Item $i", 'OwnerID' => $owner->ID])->write();
        }
        $member = $this->createMemberWithPermission('OWNER_PUBLISH');
        $message = array_column($owner->validatePublishRecursive($member)->getMessages(), 'message')[0];
        $this->assertStringContainsString('"Item 10"', $message);
        $this->assertStringNotContainsString('"Item 11"', $message);
        $this->assertStringContainsString('and 2 more', $message);
    }

    public function testPublishRecursiveDoesNotCheckPermissions(): void
    {
        $owned = RestrictedOwned::create(['Title' => 'Owned']);
        $owned->write();
        $owner = PublishOwner::create(['Title' => 'Owner', 'OwnedID' => $owned->ID]);
        $owner->write();
        $owner->publishRecursive();
        $this->assertNotNull(Versioned::get_by_stage(RestrictedOwned::class, Versioned::LIVE)->byID($owned->ID));
    }

    public function testChangeSetCanPublishChecksImplicitItems(): void
    {
        $member = $this->createMemberWithPermission('OWNER_PUBLISH');
        $owned = RestrictedOwned::create(['Title' => 'Owned']);
        $owned->write();
        $owner = PublishOwner::create(['Title' => 'Owner', 'OwnedID' => $owned->ID]);
        $owner->write();
        $changeSet = ChangeSet::create();
        $changeSet->write();
        $changeSet->addObject($owner);
        $changeSet->sync();
        $implicitChanges = $changeSet->Changes()->filter(['Added' => ChangeSetItem::IMPLICITLY]);
        $this->assertSame([$owned->ID], $implicitChanges->column('ObjectID'));
        $this->assertFalse($changeSet->canPublish($member));
        $this->assertTrue($changeSet->canPublish($this->createMemberWithPermission('OWNED_PUBLISH')));
    }

    public function testDoPublishChecksAgainAfterSavingForm(): void
    {
        $member = $this->createMemberWithPermission('OWNER_PUBLISH');
        $otherOwned = RestrictedOwned::create(['Title' => 'Other']);
        $otherOwned->write();
        $this->logInAs($member);
        $exception = $this->publishThroughGridField($otherOwned);
        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertStringContainsString('Saved as a draft, but not published.', $exception->getMessage());
        $this->assertStringContainsString('"Other"', $exception->getMessage());
        $savedOwner = Versioned::get_by_stage(PublishOwner::class, Versioned::DRAFT)->sort('ID DESC')->first();
        $this->assertSame((string) $otherOwned->ID, (string) $savedOwner->OwnedID);
        $this->assertFalse($savedOwner->isPublished());
        $this->assertFalse($otherOwned->isPublished());
        $permittedMember = $this->createMemberWithPermission('OWNED_PUBLISH');
        $allowedOwned = RestrictedOwned::create(['Title' => 'Allowed']);
        $allowedOwned->write();
        $this->logInAs($permittedMember);
        $this->assertNull($this->publishThroughGridField($allowedOwned));
        $savedOwner = Versioned::get_by_stage(PublishOwner::class, Versioned::DRAFT)->sort('ID DESC')->first();
        $this->assertTrue($savedOwner->isPublished());
        $this->assertTrue($allowedOwned->isPublished());
    }

    private function writeAs(RestrictedOwned $record, Member $member): void
    {
        Security::setCurrentUser($member);
        $record->write();
        Security::setCurrentUser(null);
    }

    /**
     * Publish a new PublishOwner that links to the owned record, returning the validation exception or null
     */
    private function publishThroughGridField(RestrictedOwned $owned): ?ValidationException
    {
        $controller = TestController::create();
        $parentForm = Form::create($controller, 'Form', FieldList::create(), FieldList::create());
        $itemRequest = VersionedGridFieldItemRequest::create(
            GridField::create('test', 'test', ArrayList::create())->setForm($parentForm),
            new GridFieldDetailForm(),
            PublishOwner::create(),
            $controller,
            'test'
        );
        $data = ['Title' => 'Owner', 'OwnedID' => $owned->ID];
        $form = $itemRequest->ItemEditForm();
        $form->loadDataFrom($data);
        try {
            $itemRequest->doPublish($data, $form);
        } catch (ValidationException $exception) {
            return $exception;
        }
        return null;
    }
}

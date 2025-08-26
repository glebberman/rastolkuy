<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DocumentProcessing;
use App\Models\User;
use App\Policies\DocumentProcessingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentOwnershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function userCanViewOwnDocument(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $document = DocumentProcessing::factory()->create(['user_id' => $user->id]);
        $policy = new DocumentProcessingPolicy();

        $this->assertTrue($policy->view($user, $document));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function userCannotViewOtherUserDocument(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('customer');

        $otherUser = User::factory()->create();
        $otherUser->assignRole('customer');

        $document = DocumentProcessing::factory()->create(['user_id' => $owner->id]);
        $policy = new DocumentProcessingPolicy();

        $this->assertFalse($policy->view($otherUser, $document));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function adminCanViewAnyDocument(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $user = User::factory()->create();
        $user->assignRole('customer');

        $document = DocumentProcessing::factory()->create(['user_id' => $user->id]);
        $policy = new DocumentProcessingPolicy();

        $this->assertTrue($policy->view($admin, $document));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function userCanUpdateOwnPendingDocument(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $document = DocumentProcessing::factory()->create([
            'user_id' => $user->id,
            'status' => DocumentProcessing::STATUS_PENDING,
        ]);
        $policy = new DocumentProcessingPolicy();

        $this->assertTrue($policy->update($user, $document));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function userCannotUpdateCompletedDocument(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $document = DocumentProcessing::factory()->create([
            'user_id' => $user->id,
            'status' => DocumentProcessing::STATUS_COMPLETED,
        ]);
        $policy = new DocumentProcessingPolicy();

        $this->assertFalse($policy->update($user, $document));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function userCanDeleteOwnDocument(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $document = DocumentProcessing::factory()->create(['user_id' => $user->id]);
        $policy = new DocumentProcessingPolicy();

        $this->assertTrue($policy->delete($user, $document));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function userCanProcessOwnDocument(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $document = DocumentProcessing::factory()->create(['user_id' => $user->id]);
        $policy = new DocumentProcessingPolicy();

        $this->assertTrue($policy->process($user, $document));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function userCanCancelOwnProcessingDocument(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $document = DocumentProcessing::factory()->create([
            'user_id' => $user->id,
            'status' => DocumentProcessing::STATUS_PROCESSING,
        ]);
        $policy = new DocumentProcessingPolicy();

        $this->assertTrue($policy->cancel($user, $document));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function userCannotCancelCompletedDocument(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $document = DocumentProcessing::factory()->create([
            'user_id' => $user->id,
            'status' => DocumentProcessing::STATUS_COMPLETED,
        ]);
        $policy = new DocumentProcessingPolicy();

        $this->assertFalse($policy->cancel($user, $document));
    }
}

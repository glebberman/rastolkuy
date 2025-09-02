<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentProcessing;
use App\Models\User;
use App\Models\UserCredit;
use App\Services\AuditService;
use App\Services\LLM\CostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\MockObject\Exception;
use Tests\TestCase;

class DocumentProcessingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Mock services to avoid configuration issues
        $auditServiceMock = $this->createMock(AuditService::class);
        $this->app->instance(AuditService::class, $auditServiceMock);

        $costCalculatorMock = $this->createMock(CostCalculator::class);
        $costCalculatorMock->method('estimateTokensFromFileSize')->willReturn(1000);
        $costCalculatorMock->method('calculateCost')->willReturn(0.15);
        $costCalculatorMock->method('getPricingInfo')->willReturn(['model' => 'test']);
        $this->app->instance(CostCalculator::class, $costCalculatorMock);

        // Seed permissions first
        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder', '--force' => true]);

        $this->user = User::factory()->create();
        $this->user->assignRole('customer'); // Assign customer role with permissions

        // Create initial credit balance
        UserCredit::create([
            'user_id' => $this->user->id,
            'balance' => 1000.0,
        ]);

        Sanctum::actingAs($this->user);
        Storage::fake('local');
        Queue::fake();
    }

    public function testCanUploadDocumentWithoutProcessing(): void
    {
        $file = UploadedFile::fake()->create('test-document.pdf', 100);

        $response = $this->postJson(route('api.v1.documents.upload'), [
            'file' => $file,
            'task_type' => DocumentProcessing::TASK_TRANSLATION,
            'anchor_at_start' => true,
            'options' => ['preserve_formatting' => true],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'filename',
                    'file_type',
                    'file_size',
                    'task_type',
                    'task_description',
                    'anchor_at_start',
                    'status',
                    'status_description',
                    'progress_percentage',
                    'timestamps' => [
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'filename' => 'test-document.pdf',
                    'task_type' => DocumentProcessing::TASK_TRANSLATION,
                    'anchor_at_start' => true,
                    'status' => DocumentProcessing::STATUS_PENDING,
                    'progress_percentage' => 25,
                ],
            ]);

        $this->assertDatabaseHas('document_processings', [
            'user_id' => $this->user->id,
            'original_filename' => 'test-document.pdf',
            'task_type' => DocumentProcessing::TASK_TRANSLATION,
            'anchor_at_start' => true,
            'status' => DocumentProcessing::STATUS_PENDING,
        ]);

        // Verify file was stored
        $documentProcessing = DocumentProcessing::where('user_id', $this->user->id)->first();
        $this->assertNotNull($documentProcessing);
        Storage::disk('local')->assertExists($documentProcessing->file_path);
    }

    public function testUploadValidatesRequiredFields(): void
    {
        $response = $this->postJson(route('api.v1.documents.upload'), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file', 'task_type']);
    }

    public function testUploadValidatesFileType(): void
    {
        $file = UploadedFile::fake()->create('test.exe', 100);

        $response = $this->postJson(route('api.v1.documents.upload'), [
            'file' => $file,
            'task_type' => DocumentProcessing::TASK_TRANSLATION,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function testCanEstimateDocumentCost(): void
    {
        // First upload a document
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_UPLOADED,
            'file_size' => 1024,
        ]);

        $response = $this->postJson(route('api.v1.documents.estimate', $document->uuid), [
            'model' => 'claude-3-5-haiku-20241022',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'status',
                    'estimation' => [
                        'estimated_input_tokens',
                        'estimated_output_tokens',
                        'estimated_total_tokens',
                        'estimated_cost_usd',
                        'credits_needed',
                        'model_selected',
                        'has_sufficient_balance',
                        'user_balance',
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $document->uuid,
                    'status' => DocumentProcessing::STATUS_ESTIMATED,
                ],
            ]);

        $this->assertDatabaseHas('document_processings', [
            'uuid' => $document->uuid,
            'status' => DocumentProcessing::STATUS_ESTIMATED,
        ]);
    }

    public function testEstimateRequiresUploadedStatus(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_PENDING,
        ]);

        $response = $this->postJson(route('api.v1.documents.estimate', $document->uuid));

        $response->assertStatus(409)
            ->assertJson([
                'error' => 'Invalid document status',
            ]);
    }

    public function testEstimateReturns404ForNonExistentDocument(): void
    {
        $nonExistentUuid = '550e8400-e29b-41d4-a716-446655440000';
        $response = $this->postJson(route('api.v1.documents.estimate', $nonExistentUuid));

        $response->assertStatus(404)
            ->assertJson([
                'error' => 'Document not found',
            ]);
    }

    public function testCanProcessEstimatedDocument(): void
    {
        // Create estimated document with sufficient balance
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_ESTIMATED,
            'processing_metadata' => [
                'estimation' => [
                    'estimated_cost_usd' => 0.50,
                    'credits_needed' => 50.0,
                    'has_sufficient_balance' => true,
                ],
            ],
        ]);

        $response = $this->postJson(route('api.v1.documents.process', $document->uuid));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'status',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $document->uuid,
                    'status' => DocumentProcessing::STATUS_PENDING,
                ],
            ]);

        // Verify credits were debited
        $this->assertDatabaseHas('credit_transactions', [
            'user_id' => $this->user->id,
            'type' => 'debit',
            'amount' => -50.0,
            'reference_type' => 'document_processing',
            'reference_id' => $document->uuid,
        ]);
    }

    public function testProcessRequiresEstimatedStatus(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_UPLOADED,
        ]);

        $response = $this->postJson(route('api.v1.documents.process', $document->uuid));

        $response->assertStatus(409)
            ->assertJson([
                'error' => 'Cannot process document',
            ]);
    }

    public function testProcessFailsWithInsufficientBalance(): void
    {
        // Set low balance
        $userCredit = UserCredit::where('user_id', $this->user->id)->first();
        $this->assertNotNull($userCredit);
        $userCredit->update(['balance' => 10.0]);

        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_ESTIMATED,
            'processing_metadata' => [
                'estimation' => [
                    'credits_needed' => 100.0,
                ],
            ],
        ]);

        $response = $this->postJson(route('api.v1.documents.process', $document->uuid));

        $response->assertStatus(409)
            ->assertJson([
                'error' => 'Cannot process document',
            ]);
    }

    public function testCanGetDocumentStatus(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_PROCESSING,
        ]);

        $response = $this->getJson(route('api.v1.documents.status', $document->uuid));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'filename',
                    'status',
                    'status_description',
                    'progress_percentage',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $document->uuid,
                    'status' => DocumentProcessing::STATUS_PROCESSING,
                ],
            ]);
    }

    public function testCanGetCompletedDocumentResult(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_COMPLETED,
            'result' => ['translated_text' => 'Test translation result'],
            'processing_time_seconds' => 30,
            'cost_usd' => 0.25,
        ]);

        $response = $this->getJson(route('api.v1.documents.result', $document->uuid));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'filename',
                    'task_type',
                    'result',
                    'processing_time_seconds',
                    'cost_usd',
                    'completed_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $document->uuid,
                    'result' => ['translated_text' => 'Test translation result'],
                    'processing_time_seconds' => 30,
                    'cost_usd' => 0.25,
                ],
            ]);
    }

    public function testCannotGetResultForIncompleteDocument(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_PROCESSING,
        ]);

        $response = $this->getJson(route('api.v1.documents.result', $document->uuid));

        $response->assertStatus(202)
            ->assertJson([
                'error' => 'Processing not completed',
                'status' => DocumentProcessing::STATUS_PROCESSING,
            ]);
    }

    public function testCanCancelPendingDocument(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_PENDING,
        ]);

        $response = $this->postJson(route('api.v1.documents.cancel', $document->uuid));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Обработка документа отменена',
            ]);

        $this->assertDatabaseHas('document_processings', [
            'uuid' => $document->uuid,
            'status' => DocumentProcessing::STATUS_FAILED,
        ]);
    }

    public function testCannotCancelProcessingDocument(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_PROCESSING,
        ]);

        $response = $this->postJson(route('api.v1.documents.cancel', $document->uuid));

        $response->assertStatus(409);
    }

    public function testCanDeleteDocument(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'file_path' => 'documents/test-file.pdf',
        ]);

        // Create a fake file
        Storage::disk('local')->put($document->file_path, 'fake content');

        $response = $this->deleteJson(route('api.v1.documents.destroy', $document->uuid));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Запись об обработке документа удалена',
            ]);

        $this->assertSoftDeleted('document_processings', [
            'uuid' => $document->uuid,
        ]);

        // Verify file was deleted
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function testBackwardCompatibilityWithOldStoreEndpoint(): void
    {
        $file = UploadedFile::fake()->create('test-document.pdf', 100);

        $response = $this->postJson(route('api.v1.documents.store'), [
            'file' => $file,
            'task_type' => DocumentProcessing::TASK_TRANSLATION,
            'anchor_at_start' => false,
            'options' => [],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'status',
                ],
            ])
            ->assertJson([
                'data' => [
                    'status' => DocumentProcessing::STATUS_PENDING,
                ],
            ]);

        $this->assertDatabaseHas('document_processings', [
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_PENDING,
        ]);
    }

    public function testCanUploadDocumentWithFormDataStringBooleans(): void
    {
        $file = UploadedFile::fake()->create('test-document.pdf', 100);

        $response = $this->post(route('api.v1.documents.upload'), [
            'file' => $file,
            'task_type' => DocumentProcessing::TASK_TRANSLATION,
            'anchor_at_start' => 'false', // Строковое boolean значение как в FormData
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'anchor_at_start',
                ],
            ])
            ->assertJson([
                'data' => [
                    'anchor_at_start' => false,
                ],
            ]);

        $this->assertDatabaseHas('document_processings', [
            'user_id' => $this->user->id,
            'anchor_at_start' => false,
        ]);
    }

    public function testUnauthenticatedUserCannotAccessEndpoints(): void
    {
        // Clear authentication
        $this->app['auth']->forgetGuards();

        $endpoints = [
            ['POST', route('api.v1.documents.upload')],
            ['POST', route('api.v1.documents.estimate', 'uuid')],
            ['POST', route('api.v1.documents.process', 'uuid')],
            ['GET', route('api.v1.documents.status', 'uuid')],
            ['GET', route('api.v1.documents.result', 'uuid')],
            ['POST', route('api.v1.documents.cancel', 'uuid')],
            ['DELETE', route('api.v1.documents.destroy', 'uuid')],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401);
        }
    }

    public function testUserCanOnlyAccessOwnDocuments(): void
    {
        $otherUser = User::factory()->create();
        $otherDocument = DocumentProcessing::factory()->create([
            'user_id' => $otherUser->id,
            'status' => DocumentProcessing::STATUS_UPLOADED,
        ]);

        $endpoints = [
            ['POST', route('api.v1.documents.estimate', $otherDocument->uuid)],
            ['POST', route('api.v1.documents.process', $otherDocument->uuid)],
            ['GET', route('api.v1.documents.status', $otherDocument->uuid)],
            ['GET', route('api.v1.documents.result', $otherDocument->uuid)],
            ['POST', route('api.v1.documents.cancel', $otherDocument->uuid)],
            ['DELETE', route('api.v1.documents.destroy', $otherDocument->uuid)],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(403);
        }
    }

    public function testDocumentProcessingResourceIncludesEstimationData(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_ESTIMATED,
            'processing_metadata' => [
                'estimation' => [
                    'estimated_cost_usd' => 0.15,
                    'credits_needed' => 15.0,
                    'model_selected' => 'claude-3-5-haiku-20241022',
                    'has_sufficient_balance' => true,
                ],
            ],
        ]);

        $response = $this->getJson(route('api.v1.documents.status', $document->uuid));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'estimation' => [
                        'estimated_cost_usd',
                        'credits_needed',
                        'model_selected',
                        'has_sufficient_balance',
                    ],
                ],
            ]);
    }
}

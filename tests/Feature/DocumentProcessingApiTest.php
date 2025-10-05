<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\AnalyzeDocumentStructureJob;
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

        // Create simplified mocks for new dependencies
        $this->mockStructureAnalysisServices();

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
        Storage::fake('local'); // Still fake local for backward compatibility
        Storage::fake('minio'); // Also fake the minio disk
        Storage::fake('s3'); // And s3 disk
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
                    'status' => DocumentProcessing::STATUS_UPLOADED,
                    'progress_percentage' => 10,
                ],
            ]);

        $this->assertDatabaseHas('document_processings', [
            'user_id' => $this->user->id,
            'original_filename' => 'test-document.pdf',
            'task_type' => DocumentProcessing::TASK_TRANSLATION,
            'anchor_at_start' => true,
            'status' => DocumentProcessing::STATUS_UPLOADED,
        ]);

        // Verify file was stored
        $documentProcessing = DocumentProcessing::where('user_id', $this->user->id)->first();
        $this->assertNotNull($documentProcessing);
        // Use the default filesystem disk (minio in this case)
        $defaultDisk = config('filesystems.default');
        Storage::disk(is_string($defaultDisk) ? $defaultDisk : 'local')->assertExists($documentProcessing->file_path);
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
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $document->uuid,
                    'status' => DocumentProcessing::STATUS_ANALYZING,
                ],
            ]);

        // Verify document status is now "analyzing"
        $this->assertDatabaseHas('document_processings', [
            'uuid' => $document->uuid,
            'status' => DocumentProcessing::STATUS_ANALYZING,
        ]);

        // Verify that AnalyzeDocumentStructureJob was dispatched
        Queue::assertPushed(AnalyzeDocumentStructureJob::class);
    }

    public function testCanEstimateDocumentCostWithFileSizeFallback(): void
    {
        // Create document with file size larger than the limit
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_UPLOADED,
            'file_size' => 1024 * 1024 * 60, // 60MB - larger than default 50MB limit
        ]);

        $response = $this->postJson(route('api.v1.documents.estimate', $document->uuid));

        $response->assertStatus(200)
            ->assertJsonPath('data.status', DocumentProcessing::STATUS_ANALYZING);

        // Verify that AnalyzeDocumentStructureJob was still dispatched (fallback will happen in the job)
        Queue::assertPushed(AnalyzeDocumentStructureJob::class);

        // Verify document status is now "analyzing"
        $this->assertDatabaseHas('document_processings', [
            'uuid' => $document->uuid,
            'status' => DocumentProcessing::STATUS_ANALYZING,
        ]);
    }

    public function testEstimateRequiresUploadedStatus(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_PENDING, // Документ в неправильном статусе
        ]);

        $response = $this->postJson(route('api.v1.documents.estimate', $document->uuid));

        $response->assertStatus(409)
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'type',
                    'code',
                    'details'
                ],
                'meta'
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function testEstimateReturns404ForNonExistentDocument(): void
    {
        $nonExistentUuid = '550e8400-e29b-41d4-a716-446655440000';
        $response = $this->postJson(route('api.v1.documents.estimate', $nonExistentUuid));

        $response->assertStatus(404)
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'type',
                    'code',
                    'details'
                ],
                'meta'
            ])
            ->assertJson([
                'success' => false,
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
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'type',
                    'code',
                    'details'
                ],
                'meta'
            ])
            ->assertJson([
                'success' => false,
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
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'type',
                    'code',
                    'details'
                ],
                'meta'
            ])
            ->assertJson([
                'success' => false,
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
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'type',
                    'code',
                    'details'
                ],
                'meta'
            ])
            ->assertJson([
                'success' => false,
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
        $defaultDisk = config('filesystems.default');
        Storage::disk(is_string($defaultDisk) ? $defaultDisk : 'local')->put($document->file_path, 'fake content');

        $response = $this->deleteJson(route('api.v1.documents.destroy', $document->uuid));

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Документ успешно удален',
            ]);

        $this->assertSoftDeleted('document_processings', [
            'uuid' => $document->uuid,
        ]);

        // Verify file was deleted
        $defaultDisk = config('filesystems.default');
        Storage::disk(is_string($defaultDisk) ? $defaultDisk : 'local')->assertMissing($document->file_path);
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
                    'status' => DocumentProcessing::STATUS_UPLOADED,
                ],
            ]);

        $this->assertDatabaseHas('document_processings', [
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_UPLOADED,
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

    public function testUserCanGetTheirDocumentList(): void
    {
        // Create 3 documents for user
        DocumentProcessing::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Create 2 documents for another user
        $otherUser = User::factory()->create();
        DocumentProcessing::factory()->count(2)->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson(route('api.v1.documents.index'));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.documents') // Should only see their own documents
            ->assertJsonStructure([
                'message',
                'data' => [
                    'documents' => [
                        '*' => [
                            'id',
                            'filename',
                            'status',
                            'task_type',
                        ],
                    ],
                ],
                'meta',
            ]);

        // Verify we only see our own documents
        /** @var array<int, array<string, mixed>> $documents */
        $documents = $response->json('data.documents');

        foreach ($documents as $doc) {
            $this->assertNotNull($doc['id']);
        }
    }

    public function testProgressPercentageIncludesAnalyzingStatus(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_ANALYZING,
        ]);

        $this->assertEquals(15, $document->getProgressPercentage());
        $this->assertEquals('Анализ структуры', $document->getStatusDescription());
    }

    public function testCannotProcessAnalyzingDocument(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_ANALYZING,
        ]);

        $response = $this->postJson(route('api.v1.documents.process', $document->uuid));

        $response->assertStatus(409)
            ->assertJsonStructure([
                'success',
                'message',
                'error' => [
                    'type',
                    'code',
                    'details'
                ],
                'meta'
            ])
            ->assertJson([
                'success' => false,
            ]);
    }

    public function testAnalyzingDocumentCannotBeCancelled(): void
    {
        $document = DocumentProcessing::factory()->create([
            'user_id' => $this->user->id,
            'status' => DocumentProcessing::STATUS_ANALYZING,
        ]);

        // Should not be able to cancel analyzing document since it's not in pending status
        $response = $this->postJson(route('api.v1.documents.cancel', $document->uuid));

        // Policy denies cancel action for analyzing documents, so we get 403
        $response->assertStatus(403);
    }

    private function mockStructureAnalysisServices(): void
    {
        // Create mock for SectionDetectorInterface
        $sectionDetectorMock = $this->createMock(\App\Services\Structure\Contracts\SectionDetectorInterface::class);
        $sectionDetectorMock->method('detectSections')->willReturn([]);
        $this->app->instance(\App\Services\Structure\Contracts\SectionDetectorInterface::class, $sectionDetectorMock);

        // Create mock for AnchorGeneratorInterface
        $anchorGeneratorMock = $this->createMock(\App\Services\Structure\Contracts\AnchorGeneratorInterface::class);
        $anchorGeneratorMock->method('generate')->willReturn('<!-- SECTION_ANCHOR_test_123 -->');
        $anchorGeneratorMock->method('resetUsedAnchors');
        $this->app->instance(\App\Services\Structure\Contracts\AnchorGeneratorInterface::class, $anchorGeneratorMock);

        // Mock ExtractorManager
        $extractorManagerMock = $this->createMock(\App\Services\Parser\Extractors\ExtractorManager::class);
        $extractedDocumentMock = new \App\Services\Parser\Extractors\DTOs\ExtractedDocument(
            originalPath: '/test/path.pdf',
            mimeType: 'application/pdf',
            elements: [],
            metadata: [],
            totalPages: 1,
            extractionTime: 0.1,
            metrics: [],
            errors: null,
        );
        $extractorManagerMock->method('extract')->willReturn($extractedDocumentMock);
        $this->app->instance(\App\Services\Parser\Extractors\ExtractorManager::class, $extractorManagerMock);
    }
}

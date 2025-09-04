<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentProcessing;
use App\Models\User;
use App\Services\AuthService;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly CreditService $creditService,
    ) {
    }

    /**
     * Display the dashboard with user statistics.
     */
    public function index(Request $request): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        // Default stats for unauthenticated users
        $stats = [
            'credits_balance' => 0,
            'total_documents' => 0,
            'processed_today' => 0,
        ];

        $recentDocuments = [];

        // Get real data for authenticated users
        if ($user) {
            $userStats = $this->authService->getUserStats($user);

            // Map API stats to dashboard format
            $stats = [
                'credits_balance' => $this->creditService->getBalance($user),
                'total_documents' => $userStats['total_documents'],
                'processed_today' => $userStats['processed_today'],
            ];

            // Get recent documents
            $recentDocuments = $this->getRecentDocuments($user);
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentDocuments' => $recentDocuments,
        ]);
    }

    /**
     * Get recent documents for dashboard display.
     *
     * @return array<int, array{id: int, title: string, status: string, created_at: string, pages_count: int|null}>
     */
    private function getRecentDocuments(User $user): array
    {
        $documents = $user->documentProcessings()
            ->latest('created_at')
            ->limit(5)
            ->get();

        /** @var array<int, array{id: int, title: string, status: string, created_at: string, pages_count: int|null}> $result */
        $result = $documents->map(function (DocumentProcessing $document): array {
            return [
                'id' => $document->id,
                'title' => $document->original_filename ?? "Документ #{$document->id}",
                'status' => $document->status,
                'created_at' => $document->created_at !== null ? $document->created_at->toISOString() : '',
                'pages_count' => $this->estimatePages($document->file_size ?? 0),
            ];
        })->toArray();

        return $result;
    }

    /**
     * Estimate number of pages based on file size.
     */
    private function estimatePages(int $fileSize): int
    {
        if ($fileSize === 0) {
            return 1;
        }

        // Rough estimate: 2KB per page for text documents
        return max(1, (int) round($fileSize / 2048));
    }
}

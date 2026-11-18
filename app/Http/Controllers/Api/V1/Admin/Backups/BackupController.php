<?php

namespace App\Http\Controllers\Api\V1\Admin\Backups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Backups\ConfirmBackupActionRequest;
use App\Http\Requests\Api\V1\Admin\Backups\CreateBackupRequest;
use App\Http\Requests\Api\V1\Admin\Backups\IndexBackupsRequest;
use App\Http\Resources\Api\V1\Admin\Backups\BackupResource;
use App\Services\Backups\BackupManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BackupController extends Controller
{
    public function index(IndexBackupsRequest $request, BackupManager $backups): JsonResponse
    {
        $page = $backups->paginate($request->validated());
        $summary = $backups->summary();
        $summary['latest_successful'] = $summary['latest_successful'] ? BackupResource::make($summary['latest_successful'])->resolve($request) : null;
        $summary['latest_failed'] = $summary['latest_failed'] ? BackupResource::make($summary['latest_failed'])->resolve($request) : null;
        return response()->json([
            'data' => BackupResource::collection(collect($page['items']))->resolve($request),
            'meta' => $page['meta'],
            'summary' => $summary,
        ]);
    }

    public function show(string $backup, BackupManager $backups): JsonResponse
    {
        try {
            return response()->json(['data' => BackupResource::make($backups->find($backup))->resolve()]);
        } catch (RuntimeException) {
            return response()->json(['message' => 'Backup was not found.'], 404);
        }
    }

    public function store(CreateBackupRequest $request, BackupManager $backups): JsonResponse
    {
        try {
            $record = $backups->create($request->validated('type'), $request->user());
            return response()->json(['data' => BackupResource::make($record)->resolve(), 'message' => 'Backup completed and verified.'], 201);
        } catch (RuntimeException $exception) {
            Log::warning('Backup request could not be completed.', ['exception' => get_class($exception)]);
            return response()->json(['message' => $exception->getPrevious() ? 'Backup failed. Check server logs for diagnostics.' : $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Log::error('Backup request failed unexpectedly.', ['exception' => get_class($exception)]);
            return response()->json(['message' => 'Backup failed. Check server logs for diagnostics.'], 500);
        }
    }

    public function download(string $backup, BackupManager $backups): StreamedResponse|JsonResponse
    {
        try {
            $result = $backups->downloadPath($backup);
            $backups->recordDownload($result['record'], request()->user());
            $path = $result['path'];
            $filename = $result['record']['filename'] ?? ('planora-backup-'.$result['record']['type'].'-'.$result['record']['id'].'.zip');

            return response()->streamDownload(function () use ($path): void {
                $stream = fopen($path, 'rb');
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
                @unlink($path);
                @rmdir(dirname($path));
            }, $filename, ['Content-Type' => 'application/zip']);
        } catch (RuntimeException) {
            return response()->json(['message' => 'The backup could not be verified or downloaded.'], 422);
        }
    }

    public function destroy(ConfirmBackupActionRequest $request, string $backup, BackupManager $backups): JsonResponse
    {
        try {
            if ($request->validated('confirmation') !== 'DELETE '.$backup) {
                return response()->json(['message' => 'Confirm deletion using the backup identifier.'], 422);
            }
            $backups->delete($backup, $request->user());
            return response()->json(['message' => 'Backup deleted.']);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage() === 'The only verified backup cannot be deleted.'
                ? $exception->getMessage()
                : 'The backup could not be deleted.'], 422);
        }
    }

    public function restore(ConfirmBackupActionRequest $request, string $backup, BackupManager $backups): JsonResponse
    {
        if ($request->validated('confirmation') !== 'RESTORE '.$backup) {
            return response()->json(['message' => 'Confirmation must match RESTORE and the backup identifier.'], 422);
        }
        try {
            return response()->json(['data' => $backups->restore($backup, $request->user()), 'message' => 'Recovery completed and verified.']);
        } catch (RuntimeException $exception) {
            Log::warning('Backup recovery request did not complete.', ['exception' => get_class($exception)]);
            return response()->json(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Log::critical('Backup recovery request failed unexpectedly.', ['exception' => get_class($exception)]);
            return response()->json(['message' => 'Recovery failed. Preserve the safety backup and review the server logs.'], 500);
        }
    }
}

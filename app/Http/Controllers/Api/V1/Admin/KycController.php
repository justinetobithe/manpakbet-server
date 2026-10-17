<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycController extends Controller
{
    private const RELATIONS = ['user:id,first_name,last_name,email,phone,kyc_status', 'reviewer:id,first_name,last_name,email'];

    public function index(Request $request): JsonResponse
    {
        $query = KycSubmission::query()->with(self::RELATIONS);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('id_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")));
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return $this->paginated($query->latest('id')->paginate($perPage), 'Verification requests retrieved');
    }

    public function show(int $id): JsonResponse
    {
        $submission = KycSubmission::query()->with(self::RELATIONS)->findOrFail($id);

        return $this->success(array_merge($submission->toArray(), ['id_number' => $submission->id_number]), 'Verification request retrieved');
    }

    public function file(int $id, string $type): StreamedResponse|JsonResponse
    {
        $submission = KycSubmission::query()->findOrFail($id);
        $column = KycSubmission::FILE_FIELDS[$type] ?? null;

        if (!$column || !$submission->{$column} || !Storage::disk('local')->exists($submission->{$column})) {
            return $this->error('File not found', 404);
        }

        return Storage::disk('local')->response($submission->{$column}, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required_if:decision,reject', 'nullable', 'string', 'max:500'],
        ]);

        $submission = DB::transaction(function () use ($request, $id, $data) {
            $submission = KycSubmission::query()->lockForUpdate()->findOrFail($id);

            if ($submission->status !== KycSubmission::STATUS_PENDING) {
                $this->fail('This request has already been reviewed');
            }

            $approved = $data['decision'] === 'approve';

            $submission->update([
                'status' => $approved ? KycSubmission::STATUS_APPROVED : KycSubmission::STATUS_REJECTED,
                'rejection_reason' => $approved ? null : $data['reason'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $submission->user->forceFill([
                'kyc_status' => $approved ? 'verified' : 'rejected',
                'first_name' => $approved ? $submission->first_name : $submission->user->first_name,
                'last_name' => $approved ? $submission->last_name : $submission->user->last_name,
            ])->save();

            return $submission->load(self::RELATIONS);
        });

        return $this->success($submission, $data['decision'] === 'approve' ? 'Player verified' : 'Verification rejected');
    }
}

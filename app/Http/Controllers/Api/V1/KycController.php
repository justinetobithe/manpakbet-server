<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KycSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class KycController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'status' => $user->kyc_status,
            'submission' => $user->kyc,
            'id_types' => collect(KycSubmission::ID_TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ], 'Verification status retrieved');
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (in_array($user->kyc_status, ['pending', 'verified'], true)) {
            return $this->error($user->kyc_status === 'verified' ? 'Your account is already verified' : 'Your documents are already under review', 422);
        }

        $image = ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144'];

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birthdate' => ['required', 'date', 'before_or_equal:' . now()->subYears(21)->toDateString(), 'after:1900-01-01'],
            'nationality' => ['required', 'string', 'max:60'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'source_of_funds' => ['nullable', Rule::in(['salary', 'business', 'savings', 'remittance', 'pension', 'other'])],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'id_type' => ['required', Rule::in(array_keys(KycSubmission::ID_TYPES))],
            'id_number' => ['required', 'string', 'max:50'],
            'id_front' => array_merge(['required'], $image),
            'id_back' => array_merge(['nullable'], $image),
            'selfie' => array_merge(['required'], $image),
        ], [
            'birthdate.before_or_equal' => 'You must be at least 21 years old to play.',
        ]);

        $folder = "kyc/{$user->id}";
        $paths = [];

        foreach (KycSubmission::FILE_FIELDS as $field => $column) {
            if ($request->hasFile($field)) {
                $paths[$column] = $request->file($field)->store($folder, 'local');
            }
        }

        $submission = DB::transaction(function () use ($user, $data, $paths) {
            $submission = $user->kycSubmissions()->create(array_merge(
                collect($data)->except(array_keys(KycSubmission::FILE_FIELDS))->all(),
                $paths,
                ['status' => KycSubmission::STATUS_PENDING]
            ));

            $user->forceFill(['kyc_status' => 'pending', 'birthdate' => $data['birthdate']])->save();

            return $submission;
        });

        return $this->created(['status' => 'pending', 'submission' => $submission], 'Documents submitted for review');
    }
}

<?php

namespace App\Support;

use Illuminate\Http\Request;

// Stores an uploaded receipt (photo from the phone camera or a picked file)
// on the public disk and returns the path/name columns the ledger, claims
// etc. keep. Empty array when nothing was uploaded.
class ReceiptUpload
{
    public const RULES = ['nullable', 'file', 'mimes:jpg,jpeg,png,heic,heif,webp,pdf', 'max:20480'];

    /** @return array{receipt_path?: string, receipt_name?: string} */
    public static function store(Request $request, string $folder, string $field = 'receipt'): array
    {
        if (! $request->hasFile($field)) {
            return [];
        }

        $file = $request->file($field);

        return [
            'receipt_path' => $file->storeAs($folder, time().'_'.$file->getClientOriginalName(), 'public'),
            'receipt_name' => $file->getClientOriginalName(),
        ];
    }
}

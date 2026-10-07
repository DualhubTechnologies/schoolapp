<?php

namespace App\Http\Controllers;

use App\Models\DocumentVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public check page a document's QR code opens: whether the code is
 * one SchoolHub issued, still current or replaced, and what the document
 * said. No login; only the headline result is shown, never full marks,
 * fees or contacts.
 */
class VerifyDocumentController extends Controller
{
    /** The form to type a code, or a typed code sent on to its page. */
    public function form(Request $request): View|RedirectResponse
    {
        $typed = trim((string) $request->query('code'));

        if ($typed !== '') {
            return redirect()->route('verify.show', DocumentVerification::normalise($typed));
        }

        return view('verify', ['document' => null, 'code' => null, 'adminNote' => null]);
    }

    public function show(string $code): View
    {
        $code = DocumentVerification::normalise($code);
        $document = DocumentVerification::with('school')->where('code', $code)->first();

        // Everyone sees a card the school issued as genuine. Only the
        // school's own admin, signed in, is told when it was later
        // reissued with changed results, and the current card's code.
        $user = auth()->user();
        $isSchoolAdmin = $document && $user && $user->school_id === $document->school_id && $user->hasRole('School Admin');
        $current = $isSchoolAdmin && $document->replaced_at
            ? DocumentVerification::where('school_id', $document->school_id)
                ->where('subject_key', $document->subject_key)
                ->whereNull('replaced_at')
                ->latest('id')
                ->first()
            : null;

        return view('verify', [
            'document' => $document,
            'code' => $code,
            'adminNote' => $isSchoolAdmin && $document->replaced_at ? ['replaced_at' => $document->replaced_at, 'current' => $current?->code] : null,
        ]);
    }
}

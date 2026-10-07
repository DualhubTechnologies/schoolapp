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

        return view('verify', ['document' => null, 'code' => null]);
    }

    public function show(string $code): View
    {
        $code = DocumentVerification::normalise($code);

        return view('verify', [
            'document' => DocumentVerification::with('school')->where('code', $code)->first(),
            'code' => $code,
        ]);
    }
}

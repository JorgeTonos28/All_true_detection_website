<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;

class PasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    public function toResponse($request)
    {
        $message = 'Si existe una cuenta con ese correo, recibirá un enlace para recuperar el acceso.';

        return $request->wantsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }
}

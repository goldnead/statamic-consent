<?php

namespace Goldnead\StatamicConsent\Http\Controllers;

use Goldnead\StatamicConsent\Records\Recorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RecordController
{
    /**
     * Takes no input and returns no content.
     *
     * The browser pings; the server reads its own cookie and writes that. There
     * is nothing in the request for anyone to forge, and a cross-site post
     * arrives without the cookie because SameSite=Lax does not send it — so it
     * writes nothing and still answers 204. Answering the same either way is
     * deliberate: the endpoint should not tell a stranger whether a cookie was
     * present.
     */
    public function __invoke(Request $request, Recorder $recorder): Response
    {
        $recorder->record($request);

        return response()->noContent();
    }
}

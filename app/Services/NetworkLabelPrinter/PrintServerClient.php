<?php

namespace App\Services\NetworkLabelPrinter;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the external network print-server daemon (custom fork feature).
 *
 * Wire format — the daemon depends on it verbatim:
 *
 *     POST <base-url>/print?&data=<base64("<tag>|<name>|<subtitle>")>
 *
 * The stray `&` and the un-encoded base64 are deliberate: this is the exact
 * byte sequence the daemon expects. Do not "clean it up".
 */
class PrintServerClient
{
    /**
     * Seconds to wait before giving up on the print server.
     */
    public const TIMEOUT = 10;

    /**
     * Send a label and return the HTTP status code (0 when unreachable).
     */
    public function send(string $baseUrl, string $tag, string $name, string $subtitle): int
    {
        $payload = base64_encode($tag.'|'.$name.'|'.$subtitle);
        $url = rtrim($baseUrl, '/').'/print?&data='.$payload;

        try {
            return Http::timeout(self::TIMEOUT)->asForm()->post($url)->status();
        } catch (ConnectionException $e) {
            Log::warning('Network label print failed: '.$e->getMessage());

            return 0;
        }
    }
}

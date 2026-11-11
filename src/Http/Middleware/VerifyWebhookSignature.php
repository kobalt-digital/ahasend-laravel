<?php

declare(strict_types=1);

namespace KobaltDigital\AhaSend\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use KobaltDigital\AhaSend\Exceptions\WebhookVerificationFailed;
use KobaltDigital\AhaSend\Webhooks\SignatureVerifier;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class VerifyWebhookSignature
{
    public function __construct(private readonly SignatureVerifier $verifier) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->verifier->verify(
                $request->getContent(),
                $request->headers->get('webhook-id'),
                $request->headers->get('webhook-timestamp'),
                $request->headers->get('webhook-signature'),
            );
        } catch (WebhookVerificationFailed $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return $next($request);
    }
}

<?php

declare(strict_types=1);

namespace Moonweft\Preference\Sms;

use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Overtrue\EasySms\Contracts\MessageInterface;
use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\Exceptions\GatewayErrorException;
use Overtrue\EasySms\Exceptions\InvalidArgumentException;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;
use Throwable;
use UnexpectedValueException;

/** Easy SMS transport with one backup attempt only after a verified rejection. */
final class SmsClient extends EasySms
{
    /**
     * Uses the native message callbacks for provider-specific templates and variables.
     * Callers own recipient authorisation, idempotency and delivery reconciliation.
     * Unknown outcomes must not be blindly retried by the caller or its queue.
     *
     * @throws NoGatewayAvailableException Results include every attempted provider.
     */
    public function send(array|string $to, MessageInterface|array $message, array $gateways = []): array
    {
        $message = $this->formatMessage($message);
        $gateways = $this->formatGateways($gateways ?: ($message->getGateways() ?: $this->config->get('default.gateways', [])));
        if (count($gateways) > 2 || array_diff(array_keys($gateways), array_keys(SmsSettings::GATEWAYS))) {
            throw new InvalidArgumentException('短信只支持一个主服务商和一个已配置的备用服务商。');
        }

        $sendId = (string) Str::uuid();
        $results = [];
        foreach ($gateways as $gateway => $config) {
            $rejection = null;
            try {
                $results += parent::send($to, $message, [$gateway => $config]);
                if (! $this->accepted($gateway, $results[$gateway]['result'])) {
                    throw new UnexpectedValueException('服务商响应未能确认受理，请核对发送记录后再处理。');
                }
            } catch (Throwable $exception) {
                if ($exception instanceof NoGatewayAvailableException) {
                    $results += $exception->getResults();
                    $exception = $exception->getException($gateway) ?? $exception;
                }
                $rejection = $this->rejectionCode($gateway, $exception);
                $results[$gateway] = array_replace($results[$gateway] ?? [], ['gateway' => $gateway, 'status' => 'failure', 'exception' => $exception]);
            }

            $accepted = $results[$gateway]['status'] === 'success';
            $results[$gateway]['send_id'] = $sendId;
            $results[$gateway]['outcome'] = $accepted ? 'accepted' : ($rejection === null ? 'unknown' : 'rejected');
            $fallback = ! $accepted && $rejection !== null && count($results) < count($gateways);
            Log::info('preference.sms.attempt', [
                'send_id' => $sendId, 'scope' => 'host', 'gateway' => $gateway, 'attempt' => count($results),
                'outcome' => $results[$gateway]['outcome'],
                'rejection_code' => $rejection, 'fallback' => $fallback,
            ]);
            if ($accepted) {
                return $results;
            }
            if (! $fallback) {
                break;
            }
        }

        throw new NoGatewayAvailableException($results);
    }

    private function accepted(string $gateway, mixed $result): bool
    {
        if (! is_array($result)) {
            return false;
        }

        return match ($gateway) {
            'aliyun' => ($result['Code'] ?? null) === 'OK',
            'qcloud' => empty($result['Response']['Error']) && is_array($result['Response']['SendStatusSet'] ?? null) && count($result['Response']['SendStatusSet'] ?? []) === 1
                && ($result['Response']['SendStatusSet'][0]['Code'] ?? null) === 'Ok',
            'qiniu' => ! isset($result['error']) && ! empty($result['job_id']),
            'yunpian' => in_array($result['code'] ?? null, [0, '0'], true),
            default => false,
        };
    }

    /** Only documented account/authentication rejections qualify; unknown codes fail closed. */
    private function rejectionCode(string $gateway, Throwable $exception): ?string
    {
        if ($exception instanceof ClientException && in_array($exception->getResponse()->getStatusCode(), [401, 403], true)) {
            return 'HTTP_'.$exception->getResponse()->getStatusCode();
        }
        if (! $exception instanceof GatewayErrorException || $this->accepted($gateway, $exception->raw)) {
            return null;
        }
        $code = match ($gateway) {
            'aliyun' => $exception->raw['Code'] ?? null,
            'qcloud' => empty($exception->raw['Response']['SendStatusSet'])
                ? ($exception->raw['Response']['Error']['Code'] ?? null)
                : (is_array($exception->raw['Response']['SendStatusSet']) && count($exception->raw['Response']['SendStatusSet']) === 1 ? ($exception->raw['Response']['SendStatusSet'][0]['Code'] ?? null) : null),
            'yunpian' => $exception->raw['code'] ?? null,
            default => null,
        };

        return in_array($code, match ($gateway) {
            'aliyun' => ['isv.AMOUNT_NOT_ENOUGH', 'isv.OUT_OF_SERVICE', 'isv.PRODUCT_UN_SUBSCRIPT', 'isv.PRODUCT_UNSUBSCRIBE', 'isv.ACCOUNT_NOT_EXISTS', 'isp.RAM_PERMISSION_DENY'],
            'qcloud' => ['FailedOperation.InsufficientBalanceInSmsPackage', 'AuthFailure.SecretIdNotFound', 'AuthFailure.SignatureFailure', 'UnauthorizedOperation'],
            'yunpian' => [3, -1, -2, -3, -11, '3', '-1', '-2', '-3', '-11'],
            default => [],
        }, true) ? (string) $code : null;
    }
}

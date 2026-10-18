<?php

declare(strict_types=1);

namespace Moonweft\Preference\Tests\Feature;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;
use Moonweft\Preference\Sms\SmsClient;
use Overtrue\EasySms\Contracts\GatewayInterface;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;
use Overtrue\EasySms\Gateways\AliyunGateway;
use Overtrue\EasySms\Gateways\QcloudGateway;
use Overtrue\EasySms\Gateways\QiniuGateway;
use Overtrue\EasySms\Gateways\YunpianGateway;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SmsFailoverTest extends TestCase
{
    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logs = new TestHandler;
        Log::swap(new Logger(new Monolog('sms-test', [$this->logs])));
    }

    #[DataProvider('rejections')]
    public function test_definite_rejection_uses_backup_with_its_own_template(string $primary, int $status, array $body): void
    {
        $client = new SmsClient(['gateways' => [], 'default' => ['gateways' => [$primary, $primary === 'yunpian' ? 'aliyun' : 'yunpian']]]);
        $this->gateway($client, $primary, new Response($status, ['Content-Type' => 'application/json'], json_encode($body)));
        $backup = $primary === 'yunpian' ? 'aliyun' : 'yunpian';
        $this->gateway($client, $backup, new Response(200, ['Content-Type' => 'application/json'], json_encode($backup === 'aliyun' ? ['Code' => 'OK', 'BizId' => 'backup-id'] : ['code' => 0, 'sid' => 'backup-id'])));
        $result = $client->send('13800138000', [
            'template' => static fn (GatewayInterface $gateway): string => $gateway->getName().'-template',
            'data' => ['code' => 'private-code'],
        ]);
        self::assertSame([$primary, $backup], array_keys($result));
        self::assertSame($backup.'-template', $result[$backup]['template']);
        self::assertSame('success', $result[$backup]['status']);
        $records = $this->logs->getRecords();
        self::assertCount(2, $records);
        self::assertSame('rejected', $records[0]->context['outcome']);
        self::assertTrue($records[0]->context['fallback']);
        self::assertSame('accepted', $records[1]->context['outcome']);
        self::assertSame($records[0]->context['send_id'], $records[1]->context['send_id']);
        foreach (['13800138000', 'private-code', 'secret', '-template'] as $private) {
            self::assertStringNotContainsString($private, json_encode($records));
        }
    }

    public static function rejections(): array
    {
        return [
            'aliyun balance' => ['aliyun', 200, ['Code' => 'isv.AMOUNT_NOT_ENOUGH', 'Message' => 'no balance']],
            'tencent balance' => ['qcloud', 200, ['Response' => ['SendStatusSet' => [['Code' => 'FailedOperation.InsufficientBalanceInSmsPackage', 'Message' => 'no balance']]]]],
            'tencent authentication' => ['qcloud', 200, ['Response' => ['Error' => ['Code' => 'AuthFailure.SecretIdNotFound', 'Message' => 'no key']]]],
            'qiniu authentication' => ['qiniu', 401, ['error' => 'bad token']],
            'yunpian balance' => ['yunpian', 200, ['code' => 3, 'msg' => 'no balance']],
        ];
    }

    #[DataProvider('uncertainResponses')]
    public function test_unknown_or_ineligible_results_do_not_use_backup(string $primary, int $status, array $body): void
    {
        $client = new SmsClient(['gateways' => [], 'default' => ['gateways' => [$primary, $primary === 'yunpian' ? 'aliyun' : 'yunpian']]]);
        $this->gateway($client, $primary, new Response($status, ['Content-Type' => 'application/json'], json_encode($body)));
        $backup = $this->createMock(GatewayInterface::class);
        $backup->expects(self::never())->method('send');
        $client->extend($primary === 'yunpian' ? 'aliyun' : 'yunpian', static fn () => $backup);
        try {
            $client->send('13800138000', ['template' => 'template', 'data' => []]);
            self::fail('An uncertain or rejected response must not be reported as accepted.');
        } catch (NoGatewayAvailableException $exception) {
            self::assertSame([$primary], array_keys($exception->getResults()));
            self::assertFalse($this->logs->getRecords()[0]->context['fallback']);
        }
    }

    public static function uncertainResponses(): array
    {
        return [
            'http server error' => ['aliyun', 503, []],
            'missing acceptance' => ['aliyun', 200, []],
            'provider internal error' => ['aliyun', 200, ['Code' => 'isp.SYSTEM_ERROR', 'Message' => 'unknown']],
            'invalid recipient' => ['aliyun', 200, ['Code' => 'isv.MOBILE_NUMBER_ILLEGAL', 'Message' => 'bad phone']],
            'rate limit' => ['aliyun', 200, ['Code' => 'isv.BUSINESS_LIMIT_CONTROL', 'Message' => 'limit']],
            'unknown new code' => ['aliyun', 200, ['Code' => 'new.error', 'Message' => 'unknown']],
            'tencent no status' => ['qcloud', 200, ['Response' => ['RequestId' => 'id']]],
            'tencent mixed acceptance' => ['qcloud', 200, ['Response' => ['SendStatusSet' => [['Code' => 'Ok'], ['Code' => 'FailedOperation.InsufficientBalanceInSmsPackage', 'Message' => 'no balance']]]]],
            'qiniu unknown code' => ['qiniu', 200, ['error' => 500, 'message' => 'unknown']],
            'yunpian unknown' => ['yunpian', 200, ['code' => -50, 'msg' => 'unknown']],
        ];
    }

    #[DataProvider('acceptedResponses')]
    public function test_acceptance_stops_before_backup(string $primary, array $body): void
    {
        $client = new SmsClient(['gateways' => [], 'default' => ['gateways' => [$primary, $primary === 'yunpian' ? 'aliyun' : 'yunpian']]]);
        $this->gateway($client, $primary, new Response(200, ['Content-Type' => 'application/json'], json_encode($body)));
        $backup = $this->createMock(GatewayInterface::class);
        $backup->expects(self::never())->method('send');
        $client->extend($primary === 'yunpian' ? 'aliyun' : 'yunpian', static fn () => $backup);
        $result = $client->send('13800138000', ['template' => 'template']);
        self::assertSame([$primary], array_keys($result));
        self::assertSame('accepted', $result[$primary]['outcome']);
        self::assertSame($result[$primary]['send_id'], $this->logs->getRecords()[0]->context['send_id']);
    }

    public static function acceptedResponses(): array
    {
        return [
            ['aliyun', ['Code' => 'OK', 'BizId' => 'id']],
            ['qcloud', ['Response' => ['SendStatusSet' => [['Code' => 'Ok', 'SerialNo' => 'id']]]]],
            ['qiniu', ['job_id' => 'id']],
            ['yunpian', ['code' => 0, 'sid' => 'id']],
        ];
    }

    public function test_backup_timeout_preserves_both_attempts_and_stops(): void
    {
        $client = new SmsClient(['gateways' => [], 'default' => ['gateways' => ['aliyun', 'yunpian']]]);
        $this->gateway($client, 'aliyun', new Response(200, ['Content-Type' => 'application/json'], '{"Code":"isv.OUT_OF_SERVICE","Message":"no balance"}'));
        $this->gateway($client, 'yunpian', new ConnectException('timeout', new Request('POST', 'https://example.test')));
        try {
            $client->send('13800138000', ['template' => 'template']);
            self::fail('Backup timeout must surface to the caller.');
        } catch (NoGatewayAvailableException $exception) {
            self::assertSame(['rejected', 'unknown'], array_column($exception->getResults(), 'outcome'));
            self::assertSame($exception->getResults()['aliyun']['send_id'], $exception->getResults()['yunpian']['send_id']);
            self::assertCount(2, $this->logs->getRecords());
        }
    }

    public function test_timeout_does_not_send_to_backup_and_next_call_starts_with_primary(): void
    {
        $client = new SmsClient(['gateways' => [], 'default' => ['gateways' => ['aliyun', 'yunpian']]]);
        $this->gateway($client, 'aliyun', new ConnectException('timeout', new Request('POST', 'https://example.test')), new Response(200, ['Content-Type' => 'application/json'], '{"Code":"OK"}'));
        $backup = $this->createMock(GatewayInterface::class);
        $backup->expects(self::never())->method('send');
        $client->extend('yunpian', static fn () => $backup);
        try {
            $client->send('13800138000', ['template' => 'template']);
            self::fail('Timeout must surface to the caller.');
        } catch (NoGatewayAvailableException $exception) {
            self::assertInstanceOf(ConnectException::class, $exception->getException('aliyun'), $exception->getException('aliyun')->getMessage());
        }
        self::assertSame(['aliyun'], array_keys($client->send('13800138000', ['template' => 'template'])));
        self::assertSame(['unknown', 'accepted'], array_map(static fn ($record) => $record->context['outcome'], $this->logs->getRecords()));
        self::assertNotSame($this->logs->getRecords()[0]->context['send_id'], $this->logs->getRecords()[1]->context['send_id']);
    }

    public function test_both_rejected_attempts_are_reported_without_retry_loop(): void
    {
        $client = new SmsClient(['gateways' => [], 'default' => ['gateways' => ['aliyun', 'yunpian']]]);
        $this->gateway($client, 'aliyun', new Response(200, ['Content-Type' => 'application/json'], '{"Code":"isv.OUT_OF_SERVICE","Message":"no balance"}'));
        $this->gateway($client, 'yunpian', new Response(200, ['Content-Type' => 'application/json'], '{"code":3,"msg":"no balance"}'));
        try {
            $client->send('13800138000', ['template' => 'template']);
            self::fail('Both rejections must surface to the caller.');
        } catch (NoGatewayAvailableException $exception) {
            self::assertSame(['aliyun', 'yunpian'], array_keys($exception->getResults()));
            self::assertCount(2, $this->logs->getRecords());
            self::assertFalse($this->logs->getRecords()[1]->context['fallback']);
        }
    }

    private function gateway(SmsClient $client, string $name, Response|ConnectException ...$responses): void
    {
        $gateway = $this->getMockBuilder(match ($name) {
            'aliyun' => AliyunGateway::class, 'qcloud' => QcloudGateway::class,
            'qiniu' => QiniuGateway::class, 'yunpian' => YunpianGateway::class,
        })->setConstructorArgs([['access_key_secret' => 'secret', 'secret_key' => 'secret', 'secret_id' => 'id', 'sdk_app_id' => 'app']])
            ->onlyMethods(['getHttpClient', 'getName'])->getMock();
        $gateway->method('getName')->willReturn($name);
        $client->getConfig()['gateways'] = array_merge($client->getConfig()->get('gateways', []), [$name => ['sign_name' => 'MoonWeft', 'secret_key' => 'secret', 'access_key' => 'id']]);
        $gateway->expects(self::exactly(count($responses)))->method('getHttpClient')->willReturn(new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
        $client->extend($name, static fn () => $gateway);
    }
}

<?php

declare(strict_types=1);

namespace BuxferApi\Test;

use BuxferApi\Client;
use BuxferApi\Exception as BuxferApiException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ClientTest extends TestCase
{
    private const string TOKEN = 'testtoken';

    private const string ERROR = '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}';

    /**
     * @var list<RequestInterface>
     */
    private array $requests = [];

    /**
     * @param list<Response|\Throwable> $responses
     */
    private function client(array $responses): Client
    {
        $this->requests = [];
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::tap(function (RequestInterface $request): void {
            $this->requests[] = $request;
        }));

        return new Client(['handler' => $stack]);
    }

    /**
     * @param list<Response|\Throwable> $responses Responses after the login
     */
    private function loggedInClient(array $responses): Client
    {
        $client = $this->client([$this->json('{"response":{"status":"OK","token":"' . self::TOKEN . '"}}'), ...$responses]);
        $client->login('testlogin', 'testpass');

        return $client;
    }

    private function json(string $body, int $status = 200): Response
    {
        return new Response($status, [], $body);
    }

    private function lastRequest(): RequestInterface
    {
        return $this->requestAt(count($this->requests) - 1);
    }

    private function requestAt(int $index): RequestInterface
    {
        return $this->requests[$index];
    }

    public function testSetConfig(): void
    {
        $client = new Client();

        $this->assertSame(10, $client->getConfig()['timeout']);

        $client->setConfig(['timeout' => 20]);

        $this->assertSame(20, $client->getConfig()['timeout']);
    }

    public function testSetConfigRebuildsHttpClient(): void
    {
        $client = new Client();
        $stack = HandlerStack::create(new MockHandler([$this->json('{"response":{"token":"abc"}}')]));

        $client->setConfig(['handler' => $stack]);

        $this->assertSame('abc', $client->login('testlogin', 'testpass'));
    }

    public function testSetConfigRejectsUnknownKey(): void
    {
        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('Unknown configuration "verify"');

        new Client(['verify' => false]);
    }

    public function testSetConfigRejectsWrongType(): void
    {
        $client = new Client();

        $this->expectException(BuxferApiException::class);
        $client->setConfig(['timeout' => 'soon']);
    }

    public function testLoginSuccess(): void
    {
        $client = $this->loggedInClient([]);

        $this->assertSame(self::TOKEN, $client->getToken());

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/login', $request->getUri()->getPath());
        $this->assertSame('', $request->getUri()->getQuery(), 'credentials must not be sent in the URL');
        $this->assertSame('email=testlogin&password=testpass', (string) $request->getBody());
        $this->assertStringStartsWith('PHP BuxferApi', $request->getHeaderLine('User-Agent'));
    }

    public function testLoginFail(): void
    {
        $client = $this->client([$this->json(self::ERROR)]);

        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('Email or username does not match an existing account.');
        $client->login('testlogin', 'testpass');
    }

    public function testLoginWithoutTokenFails(): void
    {
        $client = $this->client([$this->json('{"response":{"status":"OK"}}')]);

        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('Token not received');
        $client->login('testlogin', 'testpass');
    }

    public function testLoginRequiresCredentials(): void
    {
        $client = $this->client([]);

        try {
            $client->login('', 'testpass');
            $this->fail('Empty username accepted');
        } catch (BuxferApiException $exception) {
            $this->assertStringContainsString('username', $exception->getMessage());
        }

        $this->expectExceptionMessage('password');
        $client->login('testlogin', '');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function listProvider(): array
    {
        return [
            'accounts' => ['listAccounts', 'accounts'],
            'loans' => ['listLoans', 'loans'],
            'tags' => ['listTags', 'tags'],
            'budgets' => ['listBudgets', 'budgets'],
            'reminders' => ['listReminders', 'reminders'],
            'groups' => ['listGroups', 'groups'],
            'contacts' => ['listContacts', 'contacts'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function methodProvider(): array
    {
        return array_map(static fn (array $case): array => [$case[0]], self::listProvider());
    }

    #[DataProvider('listProvider')]
    public function testListSuccess(string $method, string $endpoint): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{"status":"OK","' . $endpoint . '":[{"id":"a1"},{"id":"b2"}]}}')]);

        $items = $client->$method();

        $this->assertIsArray($items);
        $this->assertCount(2, $items);
        $this->assertSame(['id' => 'a1'], $items[0]);

        $request = $this->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/api/' . $endpoint, $request->getUri()->getPath());
        $this->assertSame('token=' . self::TOKEN, $request->getUri()->getQuery());
    }

    #[DataProvider('methodProvider')]
    public function testListFail(string $method): void
    {
        $client = $this->loggedInClient([$this->json(self::ERROR)]);

        $this->expectException(BuxferApiException::class);
        $client->$method();
    }

    #[DataProvider('methodProvider')]
    public function testListRequiresLogin(string $method): void
    {
        $client = $this->client([]);

        try {
            $client->$method();
            $this->fail('Request sent without a token');
        } catch (BuxferApiException $exception) {
            $this->assertStringContainsString('login()', $exception->getMessage());
        }

        $this->assertCount(0, $this->requests);
    }

    public function testMissingListKeyFails(): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{"status":"OK"}}')]);

        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('Missing "accounts"');
        $client->listAccounts();
    }

    public function testUploadStatement(): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{"status":"OK","uploaded":3,"balance":10.5}}')]);

        $result = $client->uploadStatement(['accountId' => '1000', 'statement' => 'a,b', 'dateFormat' => 'DD/MM/YYYY']);

        $this->assertSame(3, $result['uploaded']);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/upload_statement', $request->getUri()->getPath());
        $this->assertSame('accountId=1000&statement=a%2Cb&dateFormat=DD%2FMM%2FYYYY', (string) $request->getBody());
    }

    public function testUploadStatementFail(): void
    {
        $client = $this->loggedInClient([$this->json(self::ERROR)]);

        $this->expectException(BuxferApiException::class);
        $client->uploadStatement(['accountId' => '1000', 'statement' => '']);
    }

    public function testListTransactions(): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{"status":"OK","numTransactions":2,"transactions":[{"id":"t1"},{"id":"t2"}]}}')]);

        $transactions = $client->listTransactions(['accountName' => 'My Acc&1', 'page' => 2]);

        $this->assertCount(2, $transactions);
        $this->assertSame(
            'token=' . self::TOKEN . '&accountName=My+Acc%261&page=2',
            $this->lastRequest()->getUri()->getQuery()
        );
    }

    public function testListTransactionsFail(): void
    {
        $client = $this->loggedInClient([$this->json(self::ERROR)]);

        $this->expectException(BuxferApiException::class);
        $client->listTransactions();
    }

    public function testListAllTransactionsFollowsPages(): void
    {
        $page1 = array_fill(0, Client::PAGE_SIZE, ['id' => 'x']);
        $client = $this->loggedInClient([
            $this->json((string) json_encode(['response' => ['numTransactions' => 101, 'transactions' => $page1]])),
            $this->json((string) json_encode(['response' => ['numTransactions' => 101, 'transactions' => [['id' => 'y']]]])),
        ]);

        $transactions = $client->listAllTransactions(['accountId' => '7', 'page' => 5]);

        $this->assertCount(101, $transactions);
        $this->assertCount(3, $this->requests);
        $this->assertSame('token=' . self::TOKEN . '&accountId=7&page=1', $this->requestAt(1)->getUri()->getQuery());
        $this->assertSame('token=' . self::TOKEN . '&accountId=7&page=2', $this->requestAt(2)->getUri()->getQuery());
    }

    public function testListAllTransactionsStopsOnShortPage(): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{"transactions":[{"id":"t1"}]}}')]);

        $this->assertCount(1, $client->listAllTransactions());
        $this->assertCount(2, $this->requests);
    }

    public function testAddTransaction(): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{"id":"t9","description":"Paycheck"}}')]);

        $created = $client->addTransaction(['description' => 'Paycheck', 'amount' => '4000', 'type' => 'income']);

        $this->assertSame('t9', $created['id']);

        $request = $this->lastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/transaction_add', $request->getUri()->getPath());
        $this->assertSame('description=Paycheck&amount=4000&type=income', (string) $request->getBody());
    }

    public function testAddTransactionFail(): void
    {
        $client = $this->loggedInClient([$this->json(self::ERROR)]);

        $this->expectException(BuxferApiException::class);
        $client->addTransaction([]);
    }

    public function testEditTransaction(): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{"id":"t9","amount":5}}')]);

        $updated = $client->editTransaction(['id' => 't9', 'amount' => '5']);

        $this->assertSame(5, $updated['amount']);
        $this->assertSame('/api/transaction_edit', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('id=t9&amount=5', (string) $this->lastRequest()->getBody());
    }

    public function testDeleteTransaction(): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{}}')]);

        $client->deleteTransaction('t9');

        $this->assertSame('/api/transaction_delete', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('id=t9', (string) $this->lastRequest()->getBody());
    }

    public function testTransportErrorDoesNotLeakToken(): void
    {
        $url = Client::BUXFER_URL . 'accounts?token=' . self::TOKEN;
        $client = $this->loggedInClient([new ConnectException('cURL error 28 for ' . $url, new Request('GET', $url))]);

        try {
            $client->listAccounts();
            $this->fail('Exception expected');
        } catch (BuxferApiException $exception) {
            $this->assertStringNotContainsString(self::TOKEN, $exception->getMessage());
            $this->assertNull($exception->getPrevious(), 'the Guzzle exception carries the token in its message');
        }
    }

    public function testHttpErrorWithBuxferMessage(): void
    {
        $client = $this->loggedInClient([$this->json(self::ERROR, 401)]);

        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('Email or username does not match an existing account.');
        $client->listAccounts();
    }

    public function testHttpErrorWithoutJson(): void
    {
        $client = $this->loggedInClient([$this->json('<html>Bad gateway</html>', 502)]);

        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('HTTP status 502');
        $client->listAccounts();
    }

    public function testMalformedJson(): void
    {
        $client = $this->loggedInClient([$this->json('<html>maintenance</html>')]);

        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('Invalid JSON received');
        $client->listAccounts();
    }

    public function testMissingResponseKey(): void
    {
        $client = $this->loggedInClient([$this->json('{"status":"OK"}')]);

        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('Invalid response received');
        $client->listAccounts();
    }

    public function testLastDuration(): void
    {
        $client = $this->loggedInClient([]);

        $this->assertGreaterThanOrEqual(0.0, $client->getLastDuration());
        $this->assertLessThan(5.0, $client->getLastDuration());
    }
}

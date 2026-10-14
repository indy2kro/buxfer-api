<?php

declare(strict_types=1);

namespace BuxferApi\Test;

use BuxferApi\Client;
use BuxferApi\Exception as BuxferApiException;
use Closure;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Throwable;

final class ClientTest extends TestCase
{
    private const string TOKEN = 'testtoken';

    private const string ERROR = '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}';

    /**
     * @var list<RequestInterface>
     */
    private array $requests = [];

    /**
     * @param list<Response|Throwable|Closure> $responses
     */
    private function client(array $responses): Client
    {
        $this->requests = [];
        $handlerStack = HandlerStack::create(new MockHandler($responses));
        $handlerStack->push(Middleware::tap(function (RequestInterface $request): void {
            $this->requests[] = $request;
        }));

        return new Client(['handler' => $handlerStack]);
    }

    /**
     * @param list<Response|Throwable|Closure> $responses Responses after the login
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

        self::assertSame(10, $client->getConfig()['timeout']);

        $client->setConfig(['timeout' => 20]);

        self::assertSame(20, $client->getConfig()['timeout']);
    }

    public function testSetConfigRebuildsHttpClient(): void
    {
        $client = new Client();
        $handlerStack = HandlerStack::create(new MockHandler([$this->json('{"response":{"token":"abc"}}')]));

        $client->setConfig(['handler' => $handlerStack]);

        self::assertSame('abc', $client->login('testlogin', 'testpass'));
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

        self::assertSame(self::TOKEN, $client->getToken());

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/api/login', $request->getUri()->getPath());
        self::assertSame('', $request->getUri()->getQuery(), 'credentials must not be sent in the URL');
        self::assertSame('email=testlogin&password=testpass', (string) $request->getBody());
        self::assertStringStartsWith('PHP BuxferApi', $request->getHeaderLine('User-Agent'));
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
            self::fail('Empty username accepted');
        } catch (BuxferApiException $exception) {
            self::assertStringContainsString('username', $exception->getMessage());
        }

        $this->expectExceptionMessage('password');
        $client->login('testlogin', '');
    }

    /**
     * @return array<string, array{Closure(Client): array<mixed>, string}>
     */
    public static function listProvider(): array
    {
        return [
            'accounts' => [static fn(Client $client): array => $client->listAccounts(), 'accounts'],
            'loans' => [static fn(Client $client): array => $client->listLoans(), 'loans'],
            'tags' => [static fn(Client $client): array => $client->listTags(), 'tags'],
            'budgets' => [static fn(Client $client): array => $client->listBudgets(), 'budgets'],
            'reminders' => [static fn(Client $client): array => $client->listReminders(), 'reminders'],
            'groups' => [static fn(Client $client): array => $client->listGroups(), 'groups'],
            'contacts' => [static fn(Client $client): array => $client->listContacts(), 'contacts'],
        ];
    }

    /**
     * @return array<string, array{Closure(Client): array<mixed>}>
     */
    public static function listCallProvider(): array
    {
        return array_map(static fn(array $case): array => [$case[0]], self::listProvider());
    }

    /**
     * @param Closure(Client): array<mixed> $list
     */
    #[DataProvider('listProvider')]
    public function testListSuccess(Closure $list, string $endpoint): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{"status":"OK","' . $endpoint . '":[{"id":"a1"},{"id":"b2"}]}}')]);

        $items = $list($client);

        self::assertCount(2, $items);
        self::assertSame(['id' => 'a1'], $items[0]);

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/api/' . $endpoint, $request->getUri()->getPath());
        self::assertSame('token=' . self::TOKEN, $request->getUri()->getQuery());
    }

    /**
     * @param Closure(Client): array<mixed> $list
     */
    #[DataProvider('listCallProvider')]
    public function testListFail(Closure $list): void
    {
        $client = $this->loggedInClient([$this->json(self::ERROR)]);

        $this->expectException(BuxferApiException::class);
        $list($client);
    }

    /**
     * @param Closure(Client): array<mixed> $list
     */
    #[DataProvider('listCallProvider')]
    public function testListRequiresLogin(Closure $list): void
    {
        $client = $this->client([]);

        try {
            $list($client);
            self::fail('Request sent without a token');
        } catch (BuxferApiException $exception) {
            self::assertStringContainsString('login()', $exception->getMessage());
        }

        self::assertCount(0, $this->requests);
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

        self::assertSame(3, $result['uploaded']);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/api/upload_statement', $request->getUri()->getPath());
        self::assertSame('accountId=1000&statement=a%2Cb&dateFormat=DD%2FMM%2FYYYY', (string) $request->getBody());
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

        self::assertCount(2, $transactions);
        self::assertSame(
            'token=' . self::TOKEN . '&accountName=My+Acc%261&page=2',
            $this->lastRequest()->getUri()->getQuery(),
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

        self::assertCount(101, $transactions);
        self::assertCount(3, $this->requests);
        self::assertSame('token=' . self::TOKEN . '&accountId=7&page=1', $this->requestAt(1)->getUri()->getQuery());
        self::assertSame('token=' . self::TOKEN . '&accountId=7&page=2', $this->requestAt(2)->getUri()->getQuery());
    }

    public function testListAllTransactionsStopsOnShortPage(): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{"transactions":[{"id":"t1"}]}}')]);

        self::assertCount(1, $client->listAllTransactions());
        self::assertCount(2, $this->requests);
    }

    public function testAddTransaction(): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{"id":"t9","description":"Paycheck"}}')]);

        $created = $client->addTransaction(['description' => 'Paycheck', 'amount' => '4000', 'type' => 'income']);

        self::assertSame('t9', $created['id']);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/api/transaction_add', $request->getUri()->getPath());
        self::assertSame('description=Paycheck&amount=4000&type=income', (string) $request->getBody());
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

        self::assertSame(5, $updated['amount']);
        self::assertSame('/api/transaction_edit', $this->lastRequest()->getUri()->getPath());
        self::assertSame('id=t9&amount=5', (string) $this->lastRequest()->getBody());
    }

    public function testDeleteTransaction(): void
    {
        $client = $this->loggedInClient([$this->json('{"response":{}}')]);

        $client->deleteTransaction('t9');

        self::assertSame('/api/transaction_delete', $this->lastRequest()->getUri()->getPath());
        self::assertSame('id=t9', (string) $this->lastRequest()->getBody());
    }

    public function testTransportErrorDoesNotLeakToken(): void
    {
        $url = Client::BUXFER_URL . 'accounts?token=' . self::TOKEN;
        $client = $this->loggedInClient([new ConnectException('cURL error 28 for ' . $url, new Request('GET', $url))]);

        try {
            $client->listAccounts();
            self::fail('Exception expected');
        } catch (BuxferApiException $exception) {
            self::assertStringNotContainsString(self::TOKEN, $exception->getMessage());
            self::assertNull($exception->getPrevious(), 'the Guzzle exception carries the token in its message');
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

    public function testLastDurationMeasuresTheRequest(): void
    {
        $client = $this->loggedInClient([
            static function (): Response {
                usleep(60000);

                return new Response(200, [], '{"response":{"accounts":[]}}');
            },
        ]);

        $client->listAccounts();

        self::assertGreaterThanOrEqual(0.04, $client->getLastDuration());
        self::assertLessThan(5.0, $client->getLastDuration());
    }

    public function testLastDurationIsUpdatedWhenTheRequestFails(): void
    {
        $client = $this->loggedInClient([
            static function (): ConnectException {
                usleep(60000);

                return new ConnectException('timed out', new Request('GET', 'https://example.invalid/'));
            },
        ]);

        try {
            $client->listAccounts();
            self::fail('Exception expected');
        } catch (BuxferApiException) {
            self::assertGreaterThanOrEqual(0.04, $client->getLastDuration());
        }
    }

    public function testRedirectIsNotFollowed(): void
    {
        $client = $this->loggedInClient([
            new Response(302, ['Location' => 'http://example.invalid/api/accounts?token=' . self::TOKEN]),
            $this->json('{"response":{"accounts":[]}}'),
        ]);

        try {
            $client->listAccounts();
            self::fail('Exception expected');
        } catch (BuxferApiException $exception) {
            self::assertStringContainsString('HTTP status 302', $exception->getMessage());
        }

        self::assertCount(2, $this->requests, 'login and one request, the redirect target is not requested');
    }

    public function testPasswordIsRedactedInTraces(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        $client = $this->client([$this->json(self::ERROR)]);

        try {
            $client->login('testlogin', 'verysecretpassword');
            self::fail('Exception expected');
        } catch (BuxferApiException $exception) {
            self::assertStringNotContainsString('verysecret', $exception->getTraceAsString());
        } finally {
            ini_set('zend.exception_ignore_args', $previous === false ? '1' : $previous);
        }
    }

    public function testTokenIsNotInDumps(): void
    {
        $client = $this->loggedInClient([]);

        ob_start();
        var_dump($client);
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString(self::TOKEN, $dump);
        self::assertStringContainsString('loggedIn', $dump);
    }

    public function testInvalidConfigLeavesConfigUnchanged(): void
    {
        $client = new Client();

        try {
            $client->setConfig(['timeout' => 30, 'user_agent' => 5]);
            self::fail('Exception expected');
        } catch (BuxferApiException) {
            self::assertSame(10, $client->getConfig()['timeout']);
        }
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidTimeoutProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'not a number' => [NAN],
            'infinite' => [INF],
            'string' => ['10'],
        ];
    }

    #[DataProvider('invalidTimeoutProvider')]
    public function testInvalidTimeoutIsRejected(mixed $timeout): void
    {
        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('"timeout" must be a positive number');

        new Client(['timeout' => $timeout]);
    }

    public function testListAllTransactionsRejectsRepeatedPage(): void
    {
        $response = $this->json((string) json_encode(['response' => ['transactions' => array_fill(0, Client::PAGE_SIZE, ['id' => 'x'])]]));
        $client = $this->loggedInClient([$response, $response]);

        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('same page of transactions twice');
        $client->listAllTransactions();
    }

    public function testListAllTransactionsStopsAtMaxPages(): void
    {
        $limited = new class extends Client {
            public const int MAX_PAGES = 2;
        };
        $responses = [$this->json('{"response":{"token":"t"}}')];

        foreach (['a', 'b', 'c'] as $id) {
            $responses[] = $this->json((string) json_encode(['response' => ['transactions' => array_fill(0, Client::PAGE_SIZE, ['id' => $id])]]));
        }

        $limited->setConfig(['handler' => HandlerStack::create(new MockHandler($responses))]);
        $limited->login('testlogin', 'testpass');

        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('More than 2 pages');
        $limited->listAllTransactions();
    }

    public function testUploadStatementRequiresAccountAndStatement(): void
    {
        $client = $this->loggedInClient([]);

        try {
            $client->uploadStatement(['accountId' => '1000', 'statement' => '']);
            self::fail('Exception expected');
        } catch (BuxferApiException $exception) {
            self::assertStringContainsString('"statement"', $exception->getMessage());
        }

        self::assertCount(1, $this->requests, 'only the login was sent');
    }

    public function testEditTransactionRequiresId(): void
    {
        $client = $this->loggedInClient([]);

        $this->expectException(BuxferApiException::class);
        $this->expectExceptionMessage('"id"');
        $client->editTransaction(['amount' => '5']);
    }

    public function testDeleteTransactionRequiresId(): void
    {
        $client = $this->loggedInClient([]);

        $this->expectException(BuxferApiException::class);
        $client->deleteTransaction('');
    }
}

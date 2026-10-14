<?php

declare(strict_types=1);

namespace BuxferApi\Test;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\HandlerStack;
use BuxferApi\Client;
use BuxferApi\Exception as BuxferApiException;

final class ClientTest extends TestCase
{
    public function testSetConfig(): void
    {
        $client = new Client();

        $initialConfig = $client->getConfig();
        $this->assertNotEquals(20, $initialConfig['timeout']);

        $config = ['timeout' => 20];

        $client->setConfig($config);
        $readConfig = $client->getConfig();

        $this->assertEquals($config['timeout'], $readConfig['timeout']);
    }

    public function testLoginSuccess(): void
    {
        $token = 'testtoken';

        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","token":"' . $token . '"}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $client->login('testlogin', 'testpass');

        $this->assertSame($token, $client->getToken());
    }

    public function testLoginFail(): void
    {
        $token = 'testtoken';

        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $client->login('badlogin', 'badpass');

        $this->assertNotSame($token, $client->getToken());
    }

    public function testListAccountsSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","accounts":[{"id":"e7b9d4d7c6974ad1e9ee320ad302874d","name":"Wallet","bank":"Your Wallet","balance":-9543.16},{"id":"96586dff416564f56baed7711ac3e61a","name":"PersonalChk","bank":"Bank of America - Banking","balance":8391.28,"lastSynced":"2008-02-13 11:37:54"},{"id":"7b48a3c070d96d768e0de3ee3ed0b0b0","name":"Amex","bank":"American Express - Credit Cards","balance":0,"lastSynced":"2007-11-22 10:51:52"},{"id":"57bd3b47d5ef2665ee1c96d88419f737","name":"CitiMastercard","bank":"Citibank - Credit Cards","balance":-473.15,"lastSynced":"2008-02-18 12:03:06"}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $accounts = $client->listAccounts();

        $this->assertCount(4, $accounts);
        $item = $accounts[0];
        $this->assertIsArray($item);
        $this->assertEquals('e7b9d4d7c6974ad1e9ee320ad302874d', $item['id']);
    }

    public function testListAccountsFail(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $client->listAccounts();
    }

    public function testListLoansSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","loans":[{"entity":"Housemates","type":"group","balance":-54.99,"description":"you owe"},{"entity":"Jeff","type":"contact","balance":-4.5,"description":"you owe"},{"entity":"harshi","type":"contact","balance":444.24,"description":"you receive"},{"entity":"Buxfer Inc","type":"contact","balance":959,"description":"you receive"}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $loans = $client->listLoans();

        $this->assertCount(4, $loans);
        $item = $loans[0];
        $this->assertIsArray($item);
        $this->assertEquals('-54.99', $item['balance']);
    }

    public function testListLoansFail(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $client->listLoans();
    }

    public function testListTagsSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","tags":[{"id":"080ed5cb2aa1aaf7986d2cc58c5d7f1f","name":"Home","parentId":-1},{"id":"7e4008367454d64628775faf78287b9b","name":"Rent","parentId":"080ed5cb2aa1aaf7986d2cc58c5d7f1f"}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $tags = $client->listTags();

        $this->assertCount(2, $tags);
        $item = $tags[0];
        $this->assertIsArray($item);
        $this->assertEquals('080ed5cb2aa1aaf7986d2cc58c5d7f1f', $item['id']);
    }

    public function testListTagsFail(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $client->listTags();
    }

    public function testListBudgetsSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","budgets":[{"id":"0e51dc6749e3a8a7f724c131b30cf712","name":"Food","limit":"205","remaining":-52.85,"period":"1 month","currentPeriod":"Feb 08","tags":"eatingOut","keywords":[]}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $tags = $client->listBudgets();

        $this->assertCount(1, $tags);
        $item = $tags[0];
        $this->assertIsArray($item);
        $this->assertEquals('0e51dc6749e3a8a7f724c131b30cf712', $item['id']);
    }

    public function testListBudgetsFail(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $client->listBudgets();
    }

    public function testListRemindersSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","reminders":[{"id":"ab6e5ef4c574eaac1b7c64223434713e","name":"AT&T bill","startDate":"2008-11-08","period":"1 month","amount":50,"accountId":"8a0776e351983cd74bc014fde2f54935"}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $tags = $client->listReminders();

        $this->assertCount(1, $tags);
        $item = $tags[0];
        $this->assertIsArray($item);
        $this->assertEquals('ab6e5ef4c574eaac1b7c64223434713e', $item['id']);
    }

    public function testListRemindersFail(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $client->listReminders();
    }

    public function testListGroupsSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","groups":[{"id":"74a31314a31ab59c938d6d18679ce773","name":"Housemates","consolidated":true,"members":[{"member":{"id":"92fc6740165e7a1a7c578a916dc60444","name":"John","email":"...","balance":30.00},"member":{"id":"41cd07723def2a26c6a51882bf8e31d3","name":"Pete","email":"...","balance":-45.00},"member":{"id":"fb69cc3d5295ad73f3f397e1a4e94e05","name":"Emily","email":"...","balance":20.00},"member":{"id":"3d85d5f681ee2d80764dfc647393e802","name":"Lisa","email":"...","balance":-5.00}}]}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $groups = $client->listGroups();

        $this->assertCount(1, $groups);
        $item = $groups[0];
        $this->assertIsArray($item);
        $this->assertEquals('74a31314a31ab59c938d6d18679ce773', $item['id']);
    }

    public function testListGroupsFail(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $client->listGroups();
    }

    public function testListContactsSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","contacts":[{"id":"3d85d5f681ee2d80764dfc647393e802","name":"Pete","email":"...","balance":959},{"id":"15aa85c42ba084f0c183b1d35f6b9c1e","name":"Lisa","email":"...","balance":444.24}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $contacts = $client->listContacts();

        $this->assertCount(2, $contacts);
        $item = $contacts[0];
        $this->assertIsArray($item);
        $this->assertEquals('3d85d5f681ee2d80764dfc647393e802', $item['id']);
    }

    public function testListContactsFail(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $client->listContacts();
    }

    public function testUploadStatementSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","uploaded":true,"balance":1015.17}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $client->uploadStatement();
        $this->assertCount(0, $mock);
    }

    public function testUploadStatementFail(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $client->uploadStatement();
    }

    public function testListTransactionsSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","numTransactions":1334,"transactions":[{"id":"2d510c2696ec50d19a4e122129c455df","description":"RECURRING TRANSFER REF #OPEQG7BT","date":"21 Feb","type":"income","amount":25,"accountId":"eca68525d89d2385dda040c3b5c571c2","tags":"transfer"},{"id":"45fd72247572d026f165aa5256ffea6b","description":"RECURRING TRANSFER REF #OPE7XXPW","date":"20 Feb","type":"expense","amount":25,"accountId":"eca68525d89d2385dda040c3b5c571c2","tags":"transfer"},{"id":"bb4871250c00d240de54ed522e9adaf6","description":"other world computing: universal hd adapter (ide/sata : usb 2)","date":"17 Feb","type":"sharedbill","amount":67.85,"accountId":"e7b9d4d7c6974ad1e9ee320ad302874d","tags":"","extraInfo":"This transaction is a shared expense of $67.85, paid by John, split equally between me and John."}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $transactions = $client->listTransactions();

        $this->assertCount(3, $transactions);
        $item = $transactions[0];
        $this->assertIsArray($item);
        $this->assertEquals('2d510c2696ec50d19a4e122129c455df', $item['id']);

        $this->assertGreaterThanOrEqual(0.0, $client->getLastDuration());
    }

    public function testListTransactionsFail(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $client->listTransactions();
    }

    public function testAddTransactionSuccess(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","transactionAdded":true,"parseStatus":"success"}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $params = [];
        $client->addTransaction($params);
        $this->assertCount(0, $mock);
    }

    public function testAddTransactionFail(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);

        $client = new Client(['handler' => $handlerStack]);
        $this->expectException(BuxferApiException::class);
        $params = [];
        $client->addTransaction($params);
    }
}

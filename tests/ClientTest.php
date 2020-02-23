<?php
namespace BuxferApi\Test;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\HandlerStack;
use BuxferApi\Client;
use BuxferApi\Exception as BuxferApiException;

class ClientTest extends TestCase
{
    public function testSetConfig()
    {
        $client = new Client();
        
        $initialConfig = $client->getConfig();
        $this->assertNotEquals($initialConfig['timeout'], 20);
        
        $config = array('timeout' => 20);
        
        $client->setConfig($config);
        $readConfig = $client->getConfig();
        
        $this->assertEquals($config['timeout'], $readConfig['timeout']);
    }

    public function testLoginSuccess()
    {
        $token = 'testtoken';
        
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","token":"' . $token . '"}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $client->login('testlogin', 'testpass');
        
        $this->assertEquals($token, $client->getToken());
    }
    
    public function testLoginFail()
    {
        $token = 'testtoken';
        
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $client->login('badlogin', 'badpass');
        
        $this->assertNotEquals($token, $client->getToken());
    }
    
    public function testListAccountsSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","accounts":[{"id":"e7b9d4d7c6974ad1e9ee320ad302874d","name":"Wallet","bank":"Your Wallet","balance":-9543.16},{"id":"96586dff416564f56baed7711ac3e61a","name":"PersonalChk","bank":"Bank of America - Banking","balance":8391.28,"lastSynced":"2008-02-13 11:37:54"},{"id":"7b48a3c070d96d768e0de3ee3ed0b0b0","name":"Amex","bank":"American Express - Credit Cards","balance":0,"lastSynced":"2007-11-22 10:51:52"},{"id":"57bd3b47d5ef2665ee1c96d88419f737","name":"CitiMastercard","bank":"Citibank - Credit Cards","balance":-473.15,"lastSynced":"2008-02-18 12:03:06"}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $accounts = $client->listAccounts();
        
        $this->assertEquals(4, count($accounts));
        $this->assertEquals($accounts[0]['id'], 'e7b9d4d7c6974ad1e9ee320ad302874d');
    }
    
    public function testListAccountsFail()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $client->listAccounts();
    }
    
    public function testListLoansSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","loans":[{"entity":"Housemates","type":"group","balance":-54.99,"description":"you owe"},{"entity":"Jeff","type":"contact","balance":-4.5,"description":"you owe"},{"entity":"harshi","type":"contact","balance":444.24,"description":"you receive"},{"entity":"Buxfer Inc","type":"contact","balance":959,"description":"you receive"}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $loans = $client->listLoans();
        
        $this->assertEquals(4, count($loans));
        $this->assertEquals($loans[0]['balance'], '-54.99');
    }
    
    public function testListLoansFail()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $client->listLoans();
    }
    
    public function testListTagsSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","tags":[{"id":"080ed5cb2aa1aaf7986d2cc58c5d7f1f","name":"Home","parentId":-1},{"id":"7e4008367454d64628775faf78287b9b","name":"Rent","parentId":"080ed5cb2aa1aaf7986d2cc58c5d7f1f"}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $tags = $client->listTags();
        
        $this->assertEquals(2, count($tags));
        $this->assertEquals($tags[0]['id'], '080ed5cb2aa1aaf7986d2cc58c5d7f1f');
    }
    
    public function testListTagsFail()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $client->listTags();
    }
    
    public function testListBudgetsSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","budgets":[{"id":"0e51dc6749e3a8a7f724c131b30cf712","name":"Food","limit":"205","remaining":-52.85,"period":"1 month","currentPeriod":"Feb 08","tags":"eatingOut","keywords":[]}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $tags = $client->listBudgets();
        
        $this->assertEquals(1, count($tags));
        $this->assertEquals($tags[0]['id'], '0e51dc6749e3a8a7f724c131b30cf712');
    }
    
    public function testListBudgetsFail()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $client->listBudgets();
    }
    
    public function testListRemindersSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","reminders":[{"id":"ab6e5ef4c574eaac1b7c64223434713e","name":"AT&T bill","startDate":"2008-11-08","period":"1 month","amount":50,"accountId":"8a0776e351983cd74bc014fde2f54935"}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $tags = $client->listReminders();
        
        $this->assertEquals(1, count($tags));
        $this->assertEquals($tags[0]['id'], 'ab6e5ef4c574eaac1b7c64223434713e');
    }
    
    public function testListRemindersFail()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $client->listReminders();
    }
    
    public function testListGroupsSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","groups":[{"id":"74a31314a31ab59c938d6d18679ce773","name":"Housemates","consolidated":true,"members":[{"member":{"id":"92fc6740165e7a1a7c578a916dc60444","name":"John","email":"...","balance":30.00},"member":{"id":"41cd07723def2a26c6a51882bf8e31d3","name":"Pete","email":"...","balance":-45.00},"member":{"id":"fb69cc3d5295ad73f3f397e1a4e94e05","name":"Emily","email":"...","balance":20.00},"member":{"id":"3d85d5f681ee2d80764dfc647393e802","name":"Lisa","email":"...","balance":-5.00}}]}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $groups = $client->listGroups();
        
        $this->assertEquals(1, count($groups));
        $this->assertEquals($groups[0]['id'], '74a31314a31ab59c938d6d18679ce773');
    }
    
    public function testListGroupsFail()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $client->listGroups();
    }
    
    public function testListContactsSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","contacts":[{"id":"3d85d5f681ee2d80764dfc647393e802","name":"Pete","email":"...","balance":959},{"id":"15aa85c42ba084f0c183b1d35f6b9c1e","name":"Lisa","email":"...","balance":444.24}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $contacts = $client->listContacts();
        
        $this->assertEquals(2, count($contacts));
        $this->assertEquals($contacts[0]['id'], '3d85d5f681ee2d80764dfc647393e802');
    }
    
    public function testListContactsFail()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $client->listContacts();
    }
    
    public function testUploadStatementSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","uploaded":true,"balance":1015.17}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->assertEmpty($client->uploadStatement());
    }
    
    public function testUploadStatementFail()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $client->uploadStatement();
    }
    
    public function testListTransactionsSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","numTransactions":1334,"transactions":[{"id":"2d510c2696ec50d19a4e122129c455df","description":"RECURRING TRANSFER REF #OPEQG7BT","date":"21 Feb","type":"income","amount":25,"accountId":"eca68525d89d2385dda040c3b5c571c2","tags":"transfer"},{"id":"45fd72247572d026f165aa5256ffea6b","description":"RECURRING TRANSFER REF #OPE7XXPW","date":"20 Feb","type":"expense","amount":25,"accountId":"eca68525d89d2385dda040c3b5c571c2","tags":"transfer"},{"id":"bb4871250c00d240de54ed522e9adaf6","description":"other world computing: universal hd adapter (ide/sata : usb 2)","date":"17 Feb","type":"sharedbill","amount":67.85,"accountId":"e7b9d4d7c6974ad1e9ee320ad302874d","tags":"","extraInfo":"This transaction is a shared expense of $67.85, paid by John, split equally between me and John."}]}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $transactions = $client->listTransactions();
        
        $this->assertEquals(3, count($transactions));
        $this->assertEquals($transactions[0]['id'], '2d510c2696ec50d19a4e122129c455df');
        
        $this->assertNotEmpty($client->getLastDuration());
    }
    
    public function testListTransactionsFail()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $client->listTransactions();
    }
    
    public function testAddTransactionSuccess()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"response":{"status":"OK","transactionAdded":true,"parseStatus":"success"}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $params = array();
        $this->assertEmpty($client->addTransaction($params));
    }
    
    public function testAddTransactionFail()
    {
        $mock = new MockHandler([
            new Response(200, [], '{"error":{"request_id":1582470326,"type":"client","message":"Email or username does not match an existing account."}}')
        ]);
        $handlerStack = HandlerStack::create($mock);
        
        $client = new Client(array('handler' => $handlerStack));
        $this->expectException(BuxferApiException::class);
        $params = array();
        $client->addTransaction($params);
    }
    
}

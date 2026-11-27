<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Core\Env;
use App\Core\Lang;
use App\Core\Upload;
use App\Core\View;
use App\Llm\GeminiClient;
use App\Llm\LlmClient;
use App\Repository\AssistantRepo;
use App\Repository\BatchRepo;
use App\Repository\ProductRepo;
use App\Service\AssistantService;
use App\Service\BatchService;
use App\Service\CheckoutService;
use App\Service\OrderService;
use App\Service\RetrievalService;
use PDO;
use RuntimeException;

class FakeLlm implements LlmClient
{
    public int $calls = 0;
    public string $system = '';
    public array $messages = [];

    public function __construct(private ?string $answer)
    {
    }

    public function generate(string $system, array $messages): string
    {
        $this->calls++;
        $this->system = $system;
        $this->messages = $messages;
        if ($this->answer === null) {
            throw new RuntimeException('Simulated timeout');
        }
        return $this->answer;
    }
}

class Phase5TraceabilityAndAssistantTest extends TestCase
{
    private const SLEEP_QUESTION = 'I feel stressed and cannot sleep, what do you have?';
    private const GOTUKOLA_TEA = 17;

    private PDO $pdo;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        Lang::setLocale('en');
        $this->pdo = Database::getConnection();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        Lang::setLocale('en');
    }

    /** Services open their own transactions, so rows are removed explicitly. */
    private function cleanup(): void
    {
        $users = "SELECT user_id FROM `user` WHERE email = 'p5@test.lk'";
        $this->pdo->exec("DELETE FROM order_item WHERE order_id IN (SELECT order_id FROM orders WHERE user_id IN ({$users}))");
        $this->pdo->exec("DELETE FROM orders WHERE user_id IN ({$users})");
        $this->pdo->exec("DELETE FROM `user` WHERE email = 'p5@test.lk'");
        $this->pdo->exec("DELETE FROM product_batch WHERE batch_code LIKE 'P5-%'");
        $this->pdo->exec("DELETE FROM product WHERE product_name LIKE 'P5 %'");
        $this->pdo->exec("DELETE FROM chat_session");
        $this->pdo->exec("DELETE FROM assistant_cache");
        $this->pdo->exec("DELETE FROM concern_keyword WHERE keyword LIKE 'p5%'");
    }

    private function day(string $offset): string
    {
        return BatchRepo::today()->modify($offset)->format('Y-m-d');
    }

    private function product(int $stock = 20): int
    {
        $category = (int)$this->pdo->query("SELECT category_id FROM category LIMIT 1")->fetchColumn();
        $this->pdo->exec("INSERT INTO product (product_name, price, quantity, category_id) VALUES ('P5 Balm', 100, {$stock}, {$category})");
        return (int)$this->pdo->lastInsertId();
    }

    private function batch(int $productId, string $code, string $expires, int $units = 10): array
    {
        $res = BatchRepo::save(0, [
            'product_id' => $productId, 'batch_code' => $code, 'manufactured_on' => $this->day('-1 year'),
            'expires_on' => $expires, 'units' => $units, 'source_location' => 'Kandy', 'notes' => 'secret note',
        ]);
        $this->assertTrue($res['ok'], $res['error'] ?? '');
        return BatchRepo::find($res['batch_id']);
    }

    private function chat(string $ip = 'p5-ip'): int
    {
        return AssistantRepo::ensureSession(null, null, hash('sha256', $ip));
    }

    private function ask(string $question, ?LlmClient $client, ?int $session = null, string $ip = 'p5-ip'): array
    {
        $res = AssistantService::ask($question, $session ?? $this->chat($ip), hash('sha256', $ip), $client);
        $this->assertTrue($res['ok'], $res['error'] ?? '');
        return $res['data'];
    }

    // ------------------------------------------------------------ batches

    public function testTokensAreRandom128BitValues(): void
    {
        $pid = $this->product();
        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $tokens[] = $this->batch($pid, "P5-T{$i}", $this->day('+1 year'))['public_token'];
        }
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $token);
        }
        $this->assertCount(5, array_unique($tokens));
        $this->assertNull(BatchRepo::findByToken('1'));
        $this->assertNull(BatchRepo::findByToken(str_repeat('0', 32)));
    }

    public function testStatusIsGenuineExpiredOrRecalled(): void
    {
        $pid = $this->product();
        $today = $this->batch($pid, 'P5-TODAY', $this->day('+0 days'));
        $old = $this->batch($pid, 'P5-OLD', $this->day('-1 day'));

        $this->assertSame('genuine', BatchService::status($today), 'Usable on its expiry date');
        $this->assertSame('expired', BatchService::status($old));

        BatchRepo::setRecalled((int)$old['batch_id'], true);
        $this->assertSame('recalled', BatchService::status(BatchRepo::find((int)$old['batch_id'])));
    }

    public function testVerifyPageShowsStatusButNotInternalNotes(): void
    {
        $batch = ProductRepo::enrichLocale($this->batch($this->product(), 'P5-PAGE', $this->day('-3 days')));
        $html = View::render('shop/verify', ['batch' => $batch, 'status' => 'expired', 'duplicated' => false], false);

        $this->assertStringContainsString('Expired', $html);
        $this->assertStringContainsString('P5-PAGE', $html);
        $this->assertStringContainsString('Kandy', $html);
        $this->assertStringNotContainsString('secret note', $html);
    }

    public function testDuplicateWarningNeedsMoreThanFiftySources(): void
    {
        $batch = $this->batch($this->product(), 'P5-DUP', $this->day('+1 year'));
        $id = (int)$batch['batch_id'];

        for ($i = 1; $i <= BatchService::DUPLICATE_THRESHOLD; $i++) {
            BatchService::recordScan($id, "10.0.0.{$i}", 'phone');
        }
        BatchService::recordScan($id, '10.0.0.1', 'phone'); // same place again
        $this->assertFalse(BatchService::looksDuplicated(BatchRepo::find($id)));

        BatchService::recordScan($id, '10.0.1.1', 'phone');
        $after = BatchRepo::find($id);
        $this->assertTrue(BatchService::looksDuplicated($after));
        $this->assertSame(52, (int)$after['scan_count']);

        $stored = $this->pdo->query("SELECT ip_hash, ua_hash FROM batch_scan WHERE batch_id = {$id} LIMIT 1")->fetch();
        $this->assertSame(64, strlen($stored['ip_hash']));
        $this->assertStringNotContainsString('phone', $stored['ua_hash']);
    }

    public function testBatchValidation(): void
    {
        $pid = $this->product();
        $this->batch($pid, 'P5-ONE', $this->day('+1 year'));
        $good = ['product_id' => $pid, 'batch_code' => 'P5-TWO', 'manufactured_on' => '2026-01-10', 'expires_on' => '2027-01-10', 'units' => 1];

        $this->assertFalse(BatchRepo::save(0, ['batch_code' => 'P5-ONE'] + $good)['ok'], 'Duplicate code');
        $this->assertFalse(BatchRepo::save(0, ['expires_on' => '2025-01-01'] + $good)['ok']);
        $this->assertFalse(BatchRepo::save(0, ['expires_on' => '2027-02-30'] + $good)['ok']);
        $this->assertFalse(BatchRepo::save(0, ['product_id' => 999999] + $good)['ok']);
        $this->assertFalse(BatchRepo::save(0, ['units' => -1] + $good)['ok']);
        $this->assertTrue(BatchRepo::save(0, $good)['ok']);
    }

    public function testExpiringListCoversThirtyDaysOnly(): void
    {
        $pid = $this->product();
        $this->batch($pid, 'P5-SOON', $this->day('+30 days'));
        $this->batch($pid, 'P5-LATER', $this->day('+31 days'));
        $this->batch($pid, 'P5-GONE', $this->day('-1 day'));

        $codes = array_column(BatchRepo::expiringWithin(30), 'batch_code');
        $this->assertSame(['P5-SOON'], array_values(array_filter($codes, fn($c) => str_starts_with($c, 'P5-'))));
    }

    public function testLabelSheetEncodesTheVerifyAddress(): void
    {
        $batch = $this->batch($this->product(), 'P5-LABEL', $this->day('+1 year'));

        $this->assertStringContainsString('/verify?t=' . $batch['public_token'], BatchService::verifyUrl($batch));
        $html = BatchService::labelHtml($batch, 500);
        $this->assertSame(BatchService::MAX_LABELS, substr_count($html, "class='label'"));
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringStartsWith('%PDF-', BatchService::labelPdf($batch, 4));
    }

    public function testOrdersTakeTheEarliestExpiringBatchAndCancellingReturnsIt(): void
    {
        $pid = $this->product();
        $late = $this->batch($pid, 'P5-LATE', $this->day('+2 years'));
        $early = $this->batch($pid, 'P5-EARLY', $this->day('+2 months'));
        $this->batch($pid, 'P5-EXPIRED', $this->day('-1 day'));
        $small = $this->batch($pid, 'P5-SMALL', $this->day('+1 month'), 1);
        $recalled = $this->batch($pid, 'P5-BAD', $this->day('+1 month'));
        BatchRepo::setRecalled((int)$recalled['batch_id'], true);

        $this->pdo->exec("INSERT INTO `user` (name, email, password, status) VALUES ('P5', 'p5@test.lk', 'x', 'active')");
        $user = (int)$this->pdo->lastInsertId();
        $order = CheckoutService::createOrder($user, [['product_id' => $pid, 'quantity' => 3]], 'cod');
        $orderId = (int)$order['order_id'];
        CheckoutService::getGateway('cod')->initiatePayment($orderId, (float)$order['total_price']);

        $used = fn(array $b) => (int)BatchRepo::find((int)$b['batch_id'])['units_allocated'];
        $this->assertNull(OrderService::getOrderDetails($orderId)['items'][0]['batch_code'], 'Not before confirmation');

        $this->assertTrue(OrderService::updateStatus($orderId, 'Confirmed', null, null, false)['ok']);
        OrderService::updateStatus($orderId, 'Packed', null, null, false);
        $this->assertSame('P5-EARLY', OrderService::getOrderDetails($orderId)['items'][0]['batch_code']);
        $this->assertSame([3, 0, 0], [$used($early), $used($late), $used($small)]);
        $this->assertFalse(BatchRepo::save((int)$early['batch_id'], ['units' => 2] + $early)['ok'], 'Fewer than assigned');

        OrderService::updateStatus($orderId, 'Cancelled', null, null, false);
        $this->assertSame(0, $used($early));
        $this->assertNull(OrderService::getOrderDetails($orderId)['items'][0]['batch_code']);
    }

    public function testLabReportMustBeAPdf(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'p5');
        file_put_contents($tmp, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $file = ['name' => 'x.exe', 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK];

        $ok = Upload::processPdf($file, 'test_uploads');
        $this->assertTrue($ok['ok'], $ok['error'] ?? '');
        $this->assertStringEndsWith('.pdf', $ok['path']);
        unlink(dirname(__DIR__) . '/public' . $ok['path']);

        $this->assertFalse(Upload::process($file, 'test_uploads')['ok'], 'Images only');
        file_put_contents($tmp, '<?php echo 1;');
        $this->assertFalse(Upload::processPdf($file, 'test_uploads')['ok']);
        unlink($tmp);
    }

    // ------------------------------------------------------------ retrieval

    public function testQuestionsFindTopicsInThreeLanguages(): void
    {
        foreach ([self::SLEEP_QUESTION, 'mata ninda yanne naha', 'මට නින්ද යන්නේ නැහැ'] as $question) {
            $found = RetrievalService::find($question);
            $this->assertSame(['stress-sleep'], array_column($found['concerns'], 'slug'), $question);
            $this->assertContains(self::GOTUKOLA_TEA, array_map('intval', array_column($found['products'], 'product_id')), $question);
        }
        $named = RetrievalService::find('Do you sell neem oil?');
        $this->assertSame(1, (int)$named['products'][0]['product_id']);
    }

    public function testKeywordsMatchWholeWords(): void
    {
        $this->assertTrue(RetrievalService::mentions('my joints ache', 'joint'));
        $this->assertFalse(RetrievalService::mentions('I was scolded', 'cold'));
        $this->assertFalse(RetrievalService::mentions('the weather', 'the w x'));
        $this->assertTrue(RetrievalService::mentions('කොණ්ඩයට තෙලක්', 'කොණ්ඩ'));
    }

    // ------------------------------------------------------------ assistant

    public function testNoKeyFallsBackToCatalogueResultsWithDisclaimer(): void
    {
        $data = $this->ask(self::SLEEP_QUESTION, null);

        $this->assertTrue($data['fallback']);
        $this->assertSame('Educational only, not medical advice.', $data['disclaimer']);
        $this->assertContains(self::GOTUKOLA_TEA, array_column($data['products'], 'id'));
        $this->assertSame('/concern/stress-sleep', $data['concerns'][0]['url']);
        foreach ($data['products'] as $card) {
            $this->assertNotNull(ProductRepo::findById($card['id']), 'Catalogue products only');
        }
    }

    public function testFailedModelCallFallsBack(): void
    {
        $fake = new FakeLlm(null);
        $this->assertTrue($this->ask(self::SLEEP_QUESTION, $fake)['fallback']);
        $this->assertSame(1, $fake->calls);

        $this->assertTrue($this->ask('another sleep question', new FakeLlm('not json'))['fallback']);
    }

    public function testInventedAndOutOfStockProductIdsAreDropped(): void
    {
        $fake = new FakeLlm('```json
{"reply": "Try our tea.", "product_ids": [999999, 17, 1, "17", -1, "abc"]}
```');
        $data = $this->ask(self::SLEEP_QUESTION, $fake);

        $this->assertFalse($data['fallback']);
        $this->assertSame('Try our tea.', $data['reply']);
        // 999999 does not exist; product 1 was not part of the retrieved context
        $this->assertSame([self::GOTUKOLA_TEA], array_column($data['products'], 'id'));

        $this->pdo->exec('UPDATE product SET quantity = 0 WHERE product_id = ' . self::GOTUKOLA_TEA);
        try {
            $this->assertSame([], AssistantService::cards([self::GOTUKOLA_TEA, 999999], 'en'));
        } finally {
            $this->pdo->exec('UPDATE product SET quantity = 96 WHERE product_id = ' . self::GOTUKOLA_TEA);
        }
    }

    public function testSystemPromptHasTheRulesAndOnlyPublishedSafetyNotes(): void
    {
        $herb = (int)$this->pdo->query("SELECT herb_id FROM herb WHERE scientific_name = 'Centella asiatica'")->fetchColumn();
        $flag = (int)$this->pdo->query("SELECT flag_id FROM safety_flag WHERE code = 'surgery_soon'")->fetchColumn();
        $this->pdo->exec("INSERT INTO herb_safety (herb_id, flag_id, severity, note_en, source_note, published) VALUES ({$herb}, {$flag}, 'info', 'P5 PUBLISHED NOTE', 's', 1)");

        try {
            $fake = new FakeLlm('{"reply":"ok","product_ids":[]}');
            $this->ask(self::SLEEP_QUESTION, $fake);

            foreach (['Answer only from the CONTEXT', 'Singlish', 'Never diagnose', 'consult a doctor', 'disclaimer', 'product_ids'] as $rule) {
                $this->assertStringContainsString($rule, $fake->system);
            }
            $this->assertStringContainsString('P5 PUBLISHED NOTE', $fake->system);
            $this->assertStringNotContainsString('SAMPLE - not medical information', $fake->system);
        } finally {
            $this->pdo->exec("DELETE FROM herb_safety WHERE note_en = 'P5 PUBLISHED NOTE'");
        }
    }

    public function testPregnancyAndPrescriptionQuestionsAlwaysGetTheDoctorLine(): void
    {
        $questions = [
            'Can I use this while pregnant?', 'I take prescription tablets for blood pressure', 'is it safe for my child',
            'mama gabini, meka bonna puluwanda', 'මම ගර්භණී වෙමි, මේක හොඳද?', 'දියවැඩියා බෙහෙත් ගන්නවා',
        ];
        foreach ($questions as $i => $question) {
            // Added by the server even when the model says nothing about it
            $data = $this->ask($question, new FakeLlm('{"reply":"Sure.","product_ids":[]}'), null, "ip{$i}");
            $this->assertNotNull($data['doctor_note'], $question);
        }
        $this->assertNull($this->ask('Do you sell neem oil?', null)['doctor_note']);
    }

    public function testSinhalaQuestionGetsSinhalaAnswerText(): void
    {
        $data = $this->ask('මට නින්ද යන්නේ නැහැ', null);

        $this->assertMatchesRegularExpression('/[\x{0D80}-\x{0DFF}]/u', $data['reply']);
        $this->assertMatchesRegularExpression('/[\x{0D80}-\x{0DFF}]/u', $data['disclaimer']);
        $this->assertSame('en', Lang::getLocale(), 'The site language is unchanged');
    }

    public function testInputIsLimited(): void
    {
        $session = $this->chat();
        $ip = hash('sha256', 'p5-ip');

        $this->assertSame(422, AssistantService::ask('   ', $session, $ip, null)['status']);
        $this->assertSame(422, AssistantService::ask(str_repeat('a', 501), $session, $ip, null)['status']);
        $this->assertTrue(AssistantService::ask(str_repeat('අ', 500), $session, $ip, null)['ok']);
    }

    public function testTwentyQuestionsPerHourPerAddress(): void
    {
        $session = $this->chat();
        for ($i = 0; $i < AssistantService::MAX_PER_HOUR; $i++) {
            AssistantRepo::addMessage($session, 'user', "q{$i}");
        }
        $ip = hash('sha256', 'p5-ip');

        // A new chat from the same address is still limited
        $this->assertSame(429, AssistantService::ask('hello', $this->chat(), $ip, null)['status']);
        $this->assertTrue(AssistantService::ask('hello', $this->chat('other'), hash('sha256', 'other'), null)['ok']);

        $this->pdo->exec('UPDATE chat_message SET created_at = NOW() - INTERVAL 2 HOUR');
        $this->assertTrue(AssistantService::ask('hello', $session, $ip, null)['ok']);
    }

    public function testRepeatedFirstQuestionsAreCachedAndHistoryIsLimitedToSixTurns(): void
    {
        $fake = new FakeLlm('{"reply":"cached","product_ids":[17]}');
        $this->ask(self::SLEEP_QUESTION, $fake, null, 'a');
        $this->assertSame('cached', $this->ask('  ' . mb_strtoupper(self::SLEEP_QUESTION), $fake, null, 'b')['reply']);
        $this->assertSame(1, $fake->calls);

        $session = $this->chat('c');
        for ($i = 0; $i < 10; $i++) {
            AssistantRepo::addMessage($session, 'user', "old question {$i}");
            AssistantRepo::addMessage($session, 'assistant', "old answer {$i}");
        }
        $this->ask(self::SLEEP_QUESTION, $fake, $session, 'c');
        $this->assertSame(2, $fake->calls, 'A follow-up is not served from the cache');
        $this->assertCount(13, $fake->messages);
        $this->assertSame('old question 4', $fake->messages[0]['content']);
        $this->assertSame(self::SLEEP_QUESTION, end($fake->messages)['content']);
    }

    public function testUnmatchedQuestionsAreListedForTheAdmin(): void
    {
        $this->ask('qwertyuiop zxcvbnm', null);
        $this->ask(self::SLEEP_QUESTION, null);

        $weak = AssistantRepo::weakQuestions();
        $this->assertSame(['qwertyuiop zxcvbnm'], array_column($weak, 'content'));

        AssistantRepo::dismiss((int)$weak[0]['message_id']);
        $this->assertSame([], AssistantRepo::weakQuestions());
    }

    public function testChatOlderThanThirtyDaysIsDeleted(): void
    {
        $old = $this->chat('old');
        AssistantRepo::addMessage($old, 'user', 'old');
        $this->pdo->exec('UPDATE chat_message SET created_at = NOW() - INTERVAL 31 DAY');
        $this->pdo->exec('UPDATE chat_session SET created_at = NOW() - INTERVAL 31 DAY');
        $new = $this->chat('new');
        AssistantRepo::addMessage($new, 'user', 'new');

        AssistantRepo::purge();

        $this->assertSame(['new'], $this->pdo->query('SELECT content FROM chat_message')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertSame([$new], array_map('intval', $this->pdo->query('SELECT session_id FROM chat_session')->fetchAll(PDO::FETCH_COLUMN)));
    }

    public function testKeywordsCanBeAddedByAdmin(): void
    {
        $concern = (int)$this->pdo->query("SELECT concern_id FROM health_concern WHERE slug = 'hair-care'")->fetchColumn();

        $this->assertTrue(AssistantRepo::addKeyword($concern, ' P5Word '));
        $this->assertFalse(AssistantRepo::addKeyword($concern, 'p5word'), 'Duplicate');
        $this->assertFalse(AssistantRepo::addKeyword(999999, 'p5other'));
        $this->assertSame(['hair-care'], array_column(RetrievalService::find('anything for p5word?')['concerns'], 'slug'));
    }

    public function testGeminiRequestAndResponseHandling(): void
    {
        $client = new GeminiClient('SECRET-KEY', 'gemini-3.8-flash');

        $this->assertStringEndsWith('/models/gemini-3.8-flash:generateContent', $client->url());
        $this->assertStringNotContainsString('SECRET-KEY', $client->url());
        $body = $client->requestBody('rules', [['role' => 'user', 'content' => 'hi'], ['role' => 'assistant', 'content' => 'yo']]);
        $this->assertSame(['user', 'model'], array_column($body['contents'], 'role'));
        $this->assertStringNotContainsString('SECRET-KEY', json_encode($body));

        $text = GeminiClient::extractText(['candidates' => [['content' => ['parts' => [
            ['text' => 'thinking', 'thought' => true], ['text' => '{"reply":'], ['text' => '"x"}'],
        ]]]]]);
        $this->assertSame('{"reply":"x"}', $text);

        $this->expectException(RuntimeException::class);
        GeminiClient::extractText(['candidates' => []]);
    }

    public function testReplyLanguageAndProductIds(): void
    {
        $sinhala = new FakeLlm('{"reply":"හරි","product_ids":[]}');
        $this->ask('මට නින්ද යන්නේ නැහැ', $sinhala, null, 'si');
        $this->assertStringContainsString('write the whole reply in Sinhala script', $sinhala->system);

        $singlish = new FakeLlm('{"reply":"Hari","product_ids":[]}');
        $this->ask('mata ninda yanne naha', $singlish, null, 'sl');
        $this->assertStringNotContainsString('write the whole reply in Sinhala script', $singlish->system);

        $answer = AssistantService::parseAnswer('{"reply":"Try Gotukola Tea (id: 17) or Neem Oil (Product ID #1).","product_ids":[]}', []);
        $this->assertSame('Try Gotukola Tea or Neem Oil.', $answer['reply']);
    }

    public function testWidgetShowsPrivacyNotice(): void
    {
        $html = View::partial('assistant_widget');
        $this->assertStringContainsString('deleted after 30 days', $html);
        $this->assertStringNotContainsString('LLM_API_KEY', $html);
    }
}

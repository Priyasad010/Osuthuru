<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Database;
use App\Core\Env;
use App\Core\Lang;
use App\Core\View;
use App\Repository\QuizRepo;
use App\Repository\SafetyRepo;
use App\Service\DoshaService;
use App\Service\SafetyService;
use PDO;

class Phase4DoshaAndSafetyTest extends TestCase
{
    private PDO $pdo;
    private int $userId;
    /** @var int[] */
    private array $products = [];
    private int $herbId;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        Lang::setLocale('en');
        $_SESSION = [];
        $this->pdo = Database::getConnection();

        // Everything a test changes is rolled back
        $this->pdo->beginTransaction();

        $this->pdo->exec("INSERT INTO `user` (name, email, password, status) VALUES ('P4', 'p4@test.lk', 'x', 'active')");
        $this->userId = (int)$this->pdo->lastInsertId();

        $category = (int)$this->pdo->query("SELECT category_id FROM category LIMIT 1")->fetchColumn();
        foreach (['P4 Alpha', 'P4 Beta'] as $name) {
            $this->pdo->exec("INSERT INTO product (product_name, price, quantity, category_id) VALUES ('{$name}', 100, 5, {$category})");
            $this->products[] = (int)$this->pdo->lastInsertId();
        }

        $this->pdo->exec("INSERT INTO herb (name, scientific_name) VALUES ('P4 Herb', 'Testus quattuor')");
        $this->herbId = (int)$this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO product_herb (product_id, herb_id) VALUES ({$this->products[0]}, {$this->herbId})");
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $_SESSION = [];
        Lang::setLocale('en');
    }

    private function flagId(string $code): int
    {
        $stmt = $this->pdo->prepare("SELECT flag_id FROM safety_flag WHERE code = :code");
        $stmt->execute([':code' => $code]);
        return (int)$stmt->fetchColumn();
    }

    private function entry(string $severity, bool $published, string $flag = 'pregnant'): void
    {
        $res = SafetyRepo::saveEntry(0, [
            'herb_id' => $this->herbId, 'flag_id' => $this->flagId($flag), 'severity' => $severity,
            'note_en' => 'Test warning', 'source_note' => 'Test source', 'published' => $published,
        ]);
        $this->assertTrue($res['ok'], $res['error'] ?? '');
    }

    /** Answers choosing the option at $index for every question. */
    private function answers(array $questions, int $index): array
    {
        $answers = [];
        foreach ($questions as $q) {
            $answers[(int)$q['question_id']] = $q['options'][$index]['option_id'];
        }
        return $answers;
    }

    // ------------------------------------------------------------ quiz

    public function testDraftQuizHasTwelveQuestionsAndStartsHidden(): void
    {
        $questions = QuizRepo::questions();
        $this->assertCount(12, $questions);
        $this->assertSame([], QuizRepo::problems($questions));
        $this->assertFalse(QuizRepo::isPublished());
        $this->assertFalse(QuizRepo::isLive());

        $this->assertTrue(QuizRepo::setPublished(true)['ok']);
        $this->assertTrue(QuizRepo::isLive());
    }

    public function testIncompleteQuizCannotBePublishedAndGoesOffline(): void
    {
        QuizRepo::setPublished(true);
        QuizRepo::saveQuestion(0, 'Unfinished question', '', 13);

        $this->assertFalse(QuizRepo::isLive());
        $this->assertFalse(QuizRepo::setPublished(true)['ok']);
    }

    public function testSameAnswersGiveSameResultAndPercentagesAddTo100(): void
    {
        $questions = QuizRepo::questions();
        $answers = $this->answers($questions, 0);
        // Mix: 5 vata, 4 pitta, 3 kapha
        foreach ($questions as $i => $q) {
            $answers[(int)$q['question_id']] = $q['options'][$i < 5 ? 0 : ($i < 9 ? 1 : 2)]['option_id'];
        }

        $first = DoshaService::score($questions, $answers);
        $second = DoshaService::score($questions, $answers);

        $this->assertTrue($first['ok']);
        $this->assertSame($first, $second);
        $this->assertSame(['vata' => 42, 'pitta' => 33, 'kapha' => 25, 'dominant' => 'vata-pitta'], $first['result']);
    }

    public function testPercentagesAlwaysAddTo100(): void
    {
        for ($v = 0; $v <= 12; $v++) {
            for ($p = 0; $p <= 12 - $v; $p++) {
                $percent = DoshaService::percentages(['vata' => $v, 'pitta' => $p, 'kapha' => 12 - $v - $p]);
                $this->assertSame(100, array_sum($percent), "{$v}/{$p}");
            }
        }
        $this->assertSame(['vata' => 34, 'pitta' => 33, 'kapha' => 33], DoshaService::percentages(['vata' => 1, 'pitta' => 1, 'kapha' => 1]));
    }

    public function testDualTypeOnlyWhenTopTwoAreWithinTenPoints(): void
    {
        $this->assertSame('pitta-kapha', DoshaService::dominant(['vata' => 20, 'pitta' => 45, 'kapha' => 35]));
        $this->assertSame('pitta', DoshaService::dominant(['vata' => 20, 'pitta' => 46, 'kapha' => 34]));
        $this->assertSame('vata-pitta', DoshaService::dominant(['vata' => 34, 'pitta' => 33, 'kapha' => 33]));
    }

    public function testMissingOrForeignAnswersAreRejected(): void
    {
        $questions = QuizRepo::questions();
        $answers = $this->answers($questions, 0);

        $missing = $answers;
        array_pop($missing);
        $this->assertFalse(DoshaService::score($questions, $missing)['ok']);
        $this->assertFalse(DoshaService::score($questions, [])['ok']);

        $foreign = $answers;
        $foreign[(int)$questions[0]['question_id']] = $questions[1]['options'][0]['option_id'];
        $this->assertFalse(DoshaService::score($questions, $foreign)['ok']);

        $array = $answers;
        $array[(int)$questions[0]['question_id']] = ['x'];
        $this->assertFalse(DoshaService::score($questions, $array)['ok']);
    }

    public function testSavedResultKeepsHistoryAndOnlyScores(): void
    {
        $result = ['vata' => 50, 'pitta' => 30, 'kapha' => 20, 'dominant' => 'vata', 'token' => str_repeat('a', 32)];
        QuizRepo::saveResult($this->userId, $result);
        QuizRepo::saveResult($this->userId, $result); // double click
        QuizRepo::saveResult($this->userId, ['dominant' => 'kapha', 'token' => str_repeat('b', 32)] + $result);

        $history = QuizRepo::history($this->userId);
        $this->assertCount(2, $history);
        $this->assertSame('kapha', $history[0]['dominant']);
        $this->assertSame(
            ['result_id', 'user_id', 'session_token', 'vata', 'pitta', 'kapha', 'dominant', 'created_at'],
            array_keys($history[0])
        );
    }

    public function testResultChangesRecommendedProducts(): void
    {
        [$alpha, $beta] = $this->products;
        QuizRepo::saveProductStrengths($alpha, ['vata' => 3]);
        QuizRepo::saveProductStrengths($beta, ['kapha' => 3, 'vata' => 0]);

        $ids = fn(array $scores) => array_map('intval', array_column(QuizRepo::recommendedProducts($scores), 'product_id'));

        $this->assertSame([$alpha, $beta], $ids(['vata' => 70, 'pitta' => 10, 'kapha' => 20]));
        $this->assertSame([$beta, $alpha], $ids(['vata' => 10, 'pitta' => 10, 'kapha' => 80]));
        $this->assertSame([], $ids(['vata' => 0, 'pitta' => 100, 'kapha' => 0]));
    }

    public function testStrengthOutsideOneToThreeIsNotStored(): void
    {
        QuizRepo::saveProductStrengths($this->products[0], ['vata' => 9, 'pitta' => -1, 'kapha' => 2]);
        $this->assertSame(['kapha' => 2], QuizRepo::productStrengths()[$this->products[0]]);
    }

    public function testQuizPagesShowDisclaimer(): void
    {
        $quiz = View::render('quiz/quiz', ['open' => true, 'preview' => false, 'questions' => QuizRepo::questions(), 'answers' => []], false);
        $result = View::render('quiz/result', [
            'result' => ['vata' => 50, 'pitta' => 30, 'kapha' => 20, 'dominant' => 'vata'],
            'saved' => false, 'history' => [], 'profiles' => QuizRepo::profiles(), 'products' => [],
        ], false);

        $this->assertStringContainsString('not a medical diagnosis', $quiz);
        $this->assertStringContainsString('not a medical diagnosis', $result);
    }

    // ------------------------------------------------------------ safety

    public function testEightConditionsAreSeeded(): void
    {
        $this->assertCount(8, SafetyRepo::flags());
    }

    public function testAvoidMatchWarnsAndBlocksUntilAcknowledged(): void
    {
        $this->entry('avoid', true);
        SafetyService::setAnswers(['pregnant']);

        $result = SafetyService::currentResult([$this->products[0]]);
        $this->assertTrue($result['has_avoid']);
        $this->assertSame('Test warning', $result['warnings'][0]['note']);
        $this->assertTrue(SafetyService::needsAcknowledgement([$this->products[0]], false));
        $this->assertFalse(SafetyService::needsAcknowledgement([$this->products[0]], true));

        $panel = View::partial('safety_panel', ['productIds' => [$this->products[0]]]);
        $this->assertStringContainsString('alert-danger', $panel);
    }

    public function testNonMatchingAnswersDoNotBlock(): void
    {
        $this->entry('avoid', true);

        $this->assertFalse(SafetyService::needsAcknowledgement([$this->products[0]], false), 'Checker not used');

        SafetyService::setAnswers(['breastfeeding']);
        $this->assertFalse(SafetyService::needsAcknowledgement([$this->products[0]], false));
    }

    public function testCautionWarnsWithoutBlockingAndAvoidIsListedFirst(): void
    {
        $this->entry('caution', true);
        SafetyService::setAnswers(['pregnant', 'known_allergy']);
        $this->assertFalse(SafetyService::needsAcknowledgement([$this->products[0]], false));

        $this->entry('avoid', true, 'known_allergy');
        $warnings = SafetyService::currentResult([$this->products[0]])['warnings'];
        $this->assertSame(['avoid', 'caution'], array_column($warnings, 'severity'));
    }

    public function testUnpublishedEntriesNeverReachCustomers(): void
    {
        $this->entry('avoid', false);
        SafetyService::setAnswers(['pregnant']);

        $result = SafetyService::currentResult([$this->products[0]]);
        $this->assertSame([], $result['warnings']);
        $this->assertFalse($result['has_avoid']);
        $this->assertStringNotContainsString('Test warning', View::partial('safety_panel', ['productIds' => [$this->products[0]]]));
    }

    public function testSeededSampleEntriesAreMarkedAndUnpublished(): void
    {
        $samples = array_filter(SafetyRepo::entries(), fn($s) => str_starts_with((string)$s['note_en'], 'SAMPLE'));
        $this->assertCount(3, $samples);
        $this->assertSame([0], array_values(array_unique(array_map(fn($s) => (int)$s['published'], $samples))));
    }

    public function testMissingSafetyDataIsReportedNeverCalledSafe(): void
    {
        [$alpha, $beta] = $this->products;
        $unknown = fn(array $r) => array_column($r['unknown'], 'product_id');

        // No published entry for Alpha's herb; Beta has no herb at all
        $this->assertSame([$alpha, $beta], $unknown(SafetyService::check([$alpha, $beta], ['pregnant'])));

        $this->entry('info', true);
        $this->assertSame([$beta], $unknown(SafetyService::check([$alpha, $beta], ['pregnant'])));

        // A second herb without data makes Alpha incomplete again
        $this->pdo->exec("INSERT INTO product_herb (product_id, herb_id) SELECT {$alpha}, herb_id FROM herb WHERE herb_id <> {$this->herbId} LIMIT 1");
        $this->assertSame([$alpha, $beta], $unknown(SafetyService::check([$alpha, $beta], ['pregnant'])));

        SafetyService::setAnswers(['surgery_soon']);
        $panel = View::partial('safety_panel', ['productIds' => [$beta]]);
        $this->assertStringContainsString('does not mean the product is safe', $panel);
    }

    public function testAnswersStayInTheSessionOnly(): void
    {
        $this->entry('avoid', true);
        $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $snapshot = function () use ($tables): array {
            $data = [];
            foreach ($tables as $table) {
                $data[$table] = md5(serialize($this->pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC)));
            }
            return $data;
        };
        $before = $snapshot();

        $selected = SafetyService::setAnswers(['pregnant', 'not-a-flag', ['nested']]);
        SafetyService::check($this->products, $selected);
        SafetyService::needsAcknowledgement($this->products, false);

        $this->assertSame($before, $snapshot());
        $this->assertSame(['pregnant'], $_SESSION['safety_answers']);

        SafetyService::clearAnswers();
        $this->assertNull(SafetyService::answers());
    }

    public function testPublishingRequiresWarningAndSource(): void
    {
        $data = ['herb_id' => $this->herbId, 'flag_id' => $this->flagId('pregnant'), 'severity' => 'avoid', 'published' => true];

        $this->assertFalse(SafetyRepo::saveEntry(0, $data + ['note_en' => 'x'])['ok']);
        $this->assertFalse(SafetyRepo::saveEntry(0, $data + ['source_note' => 'x'])['ok']);
        $this->assertTrue(SafetyRepo::saveEntry(0, ['published' => false] + $data)['ok'], 'Drafts may be incomplete');
        $this->assertFalse(SafetyRepo::saveEntry(0, ['published' => false] + $data)['ok'], 'One entry per herb and flag');
    }

    public function testFlagCodesAreValidated(): void
    {
        $this->assertFalse(SafetyRepo::saveFlag(0, 'Bad Code!', 'x', '')['ok']);
        $this->assertFalse(SafetyRepo::saveFlag(0, 'pregnant', 'x', '')['ok']);
        $this->assertTrue(SafetyRepo::saveFlag(0, 'kidney_condition', 'Kidney condition', '')['ok']);
    }

    public function testNoNonStandardStatusCodes(): void
    {
        $root = dirname(__DIR__);
        foreach (['/public/index.php', '/routes/phase4.php', '/routes/phase5.php', '/routes/phase6.php', '/routes/admin.php'] as $file) {
            $this->assertStringNotContainsString('419', file_get_contents($root . $file), $file);
        }
    }

    public function testAdminPagesRender(): void
    {
        $questions = QuizRepo::questions('en');
        $html = View::render('admin/quiz', ['questions' => $questions, 'problems' => [], 'published' => false, 'profiles' => QuizRepo::profiles('en')], false)
            . View::render('admin/dosha', ['products' => [], 'strengths' => []], false)
            . View::render('admin/safety', ['flags' => SafetyRepo::flags(), 'entries' => SafetyRepo::entries(), 'herbs' => []], false);

        $this->assertStringContainsString('qualified practitioner', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
    }
}

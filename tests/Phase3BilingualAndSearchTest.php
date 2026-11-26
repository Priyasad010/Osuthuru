<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use App\Core\Env;
use App\Core\Database;
use App\Core\Lang;
use App\Repository\HerbRepo;
use App\Repository\ProductRepo;
use App\Service\SearchService;
use PDO;

class Phase3BilingualAndSearchTest extends TestCase
{
    private PDO $pdo;
    private int $productId;
    private int $herbId;
    private int $concernId;

    protected function setUp(): void
    {
        Env::load(dirname(__DIR__));
        $this->pdo = Database::getConnection();

        $this->cleanup();

        // 1. Insert test category
        $cat = $this->pdo->query("SELECT category_id FROM category LIMIT 1")->fetchColumn();
        if (!$cat) {
            $this->pdo->exec("INSERT INTO category (name, name_si) VALUES ('P3 Cat', 'P3 කාණ්ඩය')");
            $cat = (int)$this->pdo->lastInsertId();
        }

        // 2. Insert test product
        $stmt = $this->pdo->prepare("
            INSERT INTO product (product_name, product_name_si, description, description_si, price, quantity, category_id)
            VALUES ('P3 Test Soap', 'P3 පරීක්ෂණ සබන්', 'English desc', 'සිංහල විස්තරය', 350.00, 25, :cid)
        ");
        $stmt->execute([':cid' => (int)$cat]);
        $this->productId = (int)$this->pdo->lastInsertId();

        // 3. Find or ensure Neem herb exists
        $herb = $this->pdo->query("SELECT herb_id FROM herb WHERE name LIKE '%Neem%' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($herb) {
            $this->herbId = (int)$herb['herb_id'];
        } else {
            $hStmt = $this->pdo->prepare("
                INSERT INTO herb (name, name_si, scientific_name, uses, uses_si)
                VALUES ('Neem', 'කොහොඹ', 'Azadirachta indica', 'Purifies blood and skin', 'ලේ සහ සම පිරිසිදු කරයි')
            ");
            $hStmt->execute();
            $this->herbId = (int)$this->pdo->lastInsertId();
        }

        // 4. Ensure Immunity health concern exists
        $concern = $this->pdo->query("SELECT concern_id FROM health_concern WHERE slug = 'immunity' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($concern) {
            $this->concernId = (int)$concern['concern_id'];
        } else {
            $cStmt = $this->pdo->prepare("
                INSERT INTO health_concern (slug, name_en, name_si, icon)
                VALUES ('immunity', 'Immunity & Vitality', 'ප්‍රතිශක්තිය සහ ප්‍රාණවත් බව', 'bi-shield-check')
            ");
            $cStmt->execute();
            $this->concernId = (int)$this->pdo->lastInsertId();
        }

        // Link product to herb
        HerbRepo::syncProductHerbs($this->productId, [$this->herbId]);

        // Link herb to concern
        $this->pdo->exec("INSERT IGNORE INTO herb_concern (herb_id, concern_id) VALUES ({$this->herbId}, {$this->concernId})");
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        Lang::setLocale('en');
    }

    private function cleanup(): void
    {
        $this->pdo->exec("DELETE FROM product_herb WHERE product_id IN (SELECT product_id FROM product WHERE product_name LIKE 'P3 %')");
        $this->pdo->exec("DELETE FROM product WHERE product_name LIKE 'P3 %'");
    }

    public function testSinhalaTranslationFallbackToEnglish(): void
    {
        // 1. When Sinhala name exists and locale is 'si'
        $p = [
            'product_name' => 'English Name',
            'product_name_si' => 'සිංහල නම',
            'description' => 'English Desc',
            'description_si' => 'සිංහල විස්තරය'
        ];
        $enrichedSi = ProductRepo::enrichLocale($p, 'si');
        $this->assertSame('සිංහල නම', $enrichedSi['display_name']);
        $this->assertSame('සිංහල විස්තරය', $enrichedSi['display_description']);

        // 2. When Sinhala name is null/empty, fallback to English even when locale is 'si'
        $pNoSi = [
            'product_name' => 'Fallback English Name',
            'product_name_si' => null,
            'description' => 'Fallback English Desc',
            'description_si' => ''
        ];
        $enrichedFallback = ProductRepo::enrichLocale($pNoSi, 'si');
        $this->assertSame('Fallback English Name', $enrichedFallback['display_name']);
        $this->assertSame('Fallback English Desc', $enrichedFallback['display_description']);

        // 3. When locale is 'en', display English name even if Sinhala exists
        $enrichedEn = ProductRepo::enrichLocale($p, 'en');
        $this->assertSame('English Name', $enrichedEn['display_name']);
        $this->assertSame('English Desc', $enrichedEn['display_description']);
    }

    public function testLangSwitchingChangesLocaleAndTranslations(): void
    {
        Lang::setLocale('en');
        $this->assertSame('en', Lang::getLocale());
        $this->assertSame('Products', t('nav_products'));
        $this->assertSame('Herbal Dictionary', t('nav_dictionary'));

        Lang::setLocale('si');
        $this->assertSame('si', Lang::getLocale());
        $this->assertSame('නිෂ්පාදන', t('nav_products'));
        $this->assertSame('ඖෂධ ශබ්දකෝෂය', t('nav_dictionary'));
        $this->assertSame('දවසේ විශේෂිත ඖෂධය', t('herb_of_the_day'));
    }

    public function testProductListsItsHerbsAndHerbListsItsProducts(): void
    {
        // Product -> Herbs
        $product = ProductRepo::findById($this->productId);
        $this->assertNotNull($product);
        $this->assertNotEmpty($product['herbs']);
        $herbIds = array_column($product['herbs'], 'herb_id');
        $this->assertContains($this->herbId, $herbIds);

        // Herb -> Products
        $productsForHerb = HerbRepo::getProductsByHerb($this->herbId);
        $this->assertNotEmpty($productsForHerb);
        $pids = array_column($productsForHerb, 'product_id');
        $this->assertContains($this->productId, $pids);
    }

    public function testHerbDetailAndHealthConcernsMapping(): void
    {
        $concerns = HerbRepo::getConcernsByHerb($this->herbId);
        $this->assertNotEmpty($concerns);
        $cIds = array_column($concerns, 'concern_id');
        $this->assertContains($this->concernId, $cIds);

        $herbsForConcern = HerbRepo::getHerbsByConcern($this->concernId);
        $this->assertNotEmpty($herbsForConcern);
        $hIds = array_column($herbsForConcern, 'herb_id');
        $this->assertContains($this->herbId, $hIds);

        $concernBySlug = HerbRepo::getConcernBySlug('immunity');
        $this->assertNotNull($concernBySlug);
        $this->assertSame('Immunity & Vitality', $concernBySlug['name_en']);
    }

    public function testHerbOfTheDayIsDeterministic(): void
    {
        $herbDay1 = HerbRepo::getHerbOfTheDay();
        $herbDay2 = HerbRepo::getHerbOfTheDay();
        $this->assertNotNull($herbDay1);
        $this->assertNotNull($herbDay2);
        $this->assertSame($herbDay1['herb_id'], $herbDay2['herb_id']);
        $this->assertNotEmpty($herbDay1['display_name']);
        $this->assertNotEmpty($herbDay1['scientific_name']);
    }

    public function testSearchKohombaNeemAndAzadirachtaReturnNeem(): void
    {
        // 1. Search by Sinhala transliteration "kohomba"
        $resKohomba = SearchService::search('kohomba');
        $this->assertGreaterThan(0, $resKohomba['total_count']);
        $hNames = array_column($resKohomba['herbs'], 'name');
        $found = false;
        foreach ($hNames as $hn) {
            if (stripos($hn, 'Neem') !== false || stripos($hn, 'Kohomba') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Searching 'kohomba' must return Neem / Kohomba");

        // 2. Search by English "neem"
        $resNeem = SearchService::search('neem');
        $this->assertGreaterThan(0, $resNeem['total_count']);

        // 3. Search by scientific name "Azadirachta"
        $resSci = SearchService::search('Azadirachta');
        $this->assertGreaterThan(0, $resSci['total_count']);
        $this->assertSame($this->herbId, (int)$resSci['herbs'][0]['herb_id']);

        // 4. Search by Sinhala unicode "කොහොඹ"
        $resSi = SearchService::search('කොහොඹ');
        $this->assertGreaterThan(0, $resSi['total_count']);
    }

    public function testDidYouMeanForTypos(): void
    {
        $didYouMean = SearchService::computeDidYouMean('tumeric');
        $this->assertSame('turmeric', $didYouMean);

        $resTypo = SearchService::search('tumeric');
        $this->assertSame('turmeric', $resTypo['did_you_mean']);
    }

    public function testHealthConcernShortcutDetection(): void
    {
        $shortcut = SearchService::findConcernShortcut('immunity');
        $this->assertNotNull($shortcut);
        $this->assertSame('/concern/immunity', $shortcut['url']);

        // Search also returns shortcut banner
        $searchRes = SearchService::search('immunity');
        $this->assertNotNull($searchRes['concern_shortcut']);
        $this->assertSame('/concern/immunity', $searchRes['concern_shortcut']['url']);
    }

    public function testAutocompleteSuggestResponseStructure(): void
    {
        $suggest = SearchService::suggest('neem');
        $this->assertTrue($suggest['ok']);
        $this->assertArrayHasKey('herbs', $suggest['data']);
        $this->assertArrayHasKey('products', $suggest['data']);
        $this->assertArrayHasKey('concerns', $suggest['data']);
        $this->assertNotEmpty($suggest['data']['herbs']);
        $this->assertArrayHasKey('url', $suggest['data']['herbs'][0]);
        $this->assertStringStartsWith('/herb/', $suggest['data']['herbs'][0]['url']);
    }

    public function testAdminProductCanUpdateSinhalaAndSyncHerbs(): void
    {
        $updateOk = ProductRepo::updateDetails($this->productId, [
            'product_name_si' => 'අලුත් සිංහල නම',
            'description_si' => 'අලුත් සිංහල විස්තරය',
            'herb_ids' => [$this->herbId]
        ]);
        $this->assertTrue($updateOk);

        $p = ProductRepo::findById($this->productId, 'si');
        $this->assertSame('අලුත් සිංහල නම', $p['display_name']);
        $this->assertSame('අලුත් සිංහල විස්තරය', $p['display_description']);
        $this->assertCount(1, $p['herbs']);
        $this->assertSame($this->herbId, (int)$p['herbs'][0]['herb_id']);
    }
}

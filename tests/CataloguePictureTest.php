<?php

namespace Tests;

use App\Core\Database;
use App\Repository\ProductRepo;
use PHPUnit\Framework\TestCase;

class CataloguePictureTest extends TestCase
{
    public function testEveryCataloguePictureIsServed(): void
    {
        $pdo = Database::getConnection();
        foreach (['product WHERE product_id IN (1,2,12,15,16,17,18,19,20,101,102,103,104,105,106,107,108)',
                  'category WHERE category_id <= 11', 'herb WHERE herb_id <= 10', 'blog WHERE blog_id = 1'] as $source) {
            $images = $pdo->query("SELECT image FROM {$source}")->fetchAll(\PDO::FETCH_COLUMN);
            $this->assertNotEmpty($images, $source);
            foreach ($images as $image) {
                $this->assertNotNull(image_url($image), "{$source}: {$image}");
            }
        }
    }

    public function testOnlyPublicPictureFoldersAreAccepted(): void
    {
        $this->assertNull(image_url('/assets/css/style.css'));
        $this->assertNull(image_url('/assets/images/catalog/../../../.env'));
        $this->assertNotNull(image_url('/assets/images/catalog/banners/hero.jpg'));
    }

    public function testEveryDownloadedPhotoIsCredited(): void
    {
        $dir = dirname(__DIR__) . '/public/assets/images/catalog';
        $credits = json_decode((string)file_get_contents("{$dir}/credits.json"), true);
        $credited = array_column($credits, 'file');
        foreach (['herbs', 'banners', 'blog'] as $folder) {
            foreach (glob("{$dir}/{$folder}/*.jpg") as $file) {
                $this->assertContains("{$folder}/" . basename($file), $credited);
            }
        }
        foreach ($credits as $credit) {
            $this->assertNotSame('', $credit['licence']);
            $this->assertStringStartsWith('https://commons.wikimedia.org/', $credit['source']);
        }
    }

    public function testShoppersAreNotOfferedEmptyCategories(): void
    {
        $all = array_column(ProductRepo::getAllCategories('en'), 'name');
        $stocked = array_column(ProductRepo::getAllCategories('en', true), 'name');
        $this->assertContains('Wellness Syrups', $all);
        $this->assertNotContains('Wellness Syrups', $stocked);
        $this->assertContains('Herbal Powders', $stocked);
    }
}

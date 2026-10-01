<?php

namespace AppTests\Unit;

use App\Crud\Uploader;
use PHPUnit\Framework\TestCase;

final class CrudUploaderTest extends TestCase
{
    /** @var string */
    private $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/crud-uploads-'.bin2hex(random_bytes(4));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root.'/crud/*/*') ?: [] as $f) {
            unlink($f);
        }
        foreach (glob($this->root.'/crud/*') ?: [] as $d) {
            rmdir($d);
        }
        @rmdir($this->root.'/crud');
        @rmdir($this->root);
    }

    /**
     * @return array<string, mixed>
     */
    private function field(string $type = 'file', array $upload = [])
    {
        return ['label' => 'Attachment', 'type' => $type, 'upload' => $upload + ($type === 'image'
            ? ['max_kb' => 500, 'types' => ['jpg', 'png', 'webp'], 'max_width' => 100, 'quality' => 70]
            : ['max_kb' => 10, 'types' => ['pdf', 'txt']])];
    }

    /**
     * @return array<string, mixed>
     */
    private function file(string $name, string $content)
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $content);

        return ['name' => $name, 'tmp_name' => $tmp, 'size' => strlen($content), 'error' => UPLOAD_ERR_OK];
    }

    private function store(array $field, array $file)
    {
        $result = (new Uploader($this->root, false))->store($file, $field, 'docs');
        @unlink($file['tmp_name']);

        return $result;
    }

    public function testStoresAFileUnderARandomName(): void
    {
        $r = $this->store($this->field(), $this->file('Report 2026.PDF', "%PDF-1.4\nhello"));

        $this->assertNull($r['error']);
        $this->assertMatchesRegularExpression('#^crud/docs/[a-f0-9]{32}\.pdf$#', $r['path']);
        $this->assertFileExists($this->root.'/'.$r['path']);
        $this->assertTrue(Uploader::validPath($r['path']));
    }

    public function testRejectsWrongSizeAndExtensions(): void
    {
        $this->assertStringContainsString('larger than 10 KB', $this->store($this->field(), $this->file('a.pdf', str_repeat('a', 11 * 1024)))['error']);
        $this->assertStringContainsString('must be one of', $this->store($this->field(), $this->file('a.docx', 'x'))['error']);
        $this->assertStringContainsString('must be one of', $this->store($this->field(), $this->file('noext', 'x'))['error']);
        $this->assertStringContainsString('empty', $this->store($this->field(), $this->file('a.pdf', ''))['error']);
        $this->assertStringContainsString('could not be uploaded', $this->store($this->field(), ['name' => 'a.pdf', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE])['error']);
    }

    public function testNeverAcceptsDangerousExtensionsEvenIfTheDefinitionListsThem(): void
    {
        foreach (['shell.php', 'a.phtml', 'x.html', 'x.svg', '.htaccess', 'evil.php.pdf.php'] as $name) {
            $field = $this->field('file', ['types' => ['php', 'phtml', 'html', 'svg', 'htaccess', 'pdf'], 'max_kb' => 10]);
            $this->assertNotNull($this->store($field, $this->file($name, 'x'))['error'], $name);
        }
    }

    public function testRejectsDocumentsContainingServerCode(): void
    {
        $this->assertStringContainsString('contains code', $this->store($this->field(), $this->file('a.pdf', "%PDF\n<?php system('id');"))['error']);
        $this->assertStringContainsString('contains code', $this->store($this->field(), $this->file('a.txt', '<?= $x ?>'))['error']);
        $this->assertNull($this->store($this->field(), $this->file('a.txt', 'plain text <b>fine</b>'))['error']);
    }

    public function testImagesAreVerifiedByContentAndCompressed(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD is not available');
        }

        $img = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($img, null, 100);
        $jpeg = (string) ob_get_clean();

        $r = $this->store($this->field('image'), $this->file('photo.jpg', $jpeg));
        $this->assertNull($r['error']);
        $this->assertSame(100, getimagesize($this->root.'/'.$r['path'])[0], 'resized to the maximum width');

        // A script renamed to .jpg, and a real PNG named .jpg, are both refused.
        $this->assertStringContainsString('not a valid JPG', $this->store($this->field('image'), $this->file('x.jpg', '<?php echo 1;'))['error']);
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        $this->assertStringContainsString('not a valid JPG', $this->store($this->field('image'), $this->file('x.jpg', $png))['error']);
        $this->assertNull($this->store($this->field('image'), $this->file('x.png', $png))['error']);
    }

    public function testDeleteOnlyTouchesFilesItCreated(): void
    {
        $r = $this->store($this->field(), $this->file('a.pdf', '%PDF'));
        $uploader = new Uploader($this->root, false);

        $this->assertFalse($uploader->delete('../../etc/passwd'));
        $this->assertFalse($uploader->delete('crud/docs/../../x.pdf'));
        $this->assertFalse($uploader->delete('crud/docs/name.pdf'));
        $this->assertFileExists($this->root.'/'.$r['path']);

        $this->assertTrue($uploader->delete($r['path']));
        $this->assertFileDoesNotExist($this->root.'/'.$r['path']);
    }
}

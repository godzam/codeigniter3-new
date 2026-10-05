<?php

namespace AppTests\Unit;

use App\Audit\Changes;
use PHPUnit\Framework\TestCase;

final class AuditChangesTest extends TestCase
{
    public function testSnapshotKeepsValuesAndHidesSecrets(): void
    {
        $snap = Changes::snapshot([
            'name' => 'Ada', 'email' => 'ada@example.com', 'password' => '$2y$10$abc', 'api_key' => 'k-123',
            'turnstile_secret_key' => 'shh', 'remember_token' => 'x', 'note' => '', 'deleted_at' => null,
        ]);

        $this->assertSame('Ada', $snap['name']);
        $this->assertSame(Changes::HIDDEN, $snap['password']);
        $this->assertSame(Changes::HIDDEN, $snap['api_key']);
        $this->assertSame(Changes::HIDDEN, $snap['turnstile_secret_key']);
        $this->assertSame(Changes::HIDDEN, $snap['remember_token']);
        $this->assertArrayNotHasKey('note', $snap, 'empty values are left out');
        $this->assertArrayNotHasKey('deleted_at', $snap);
    }

    public function testPermissionsIsNotMistakenForAPassword(): void
    {
        $this->assertFalse(Changes::isSensitive('permissions'));
        $this->assertFalse(Changes::isSensitive('role'));
        $this->assertTrue(Changes::isSensitive('password'));
        $this->assertTrue(Changes::isSensitive('user_password'));
        $this->assertTrue(Changes::isSensitive('fields.pin.secret'));
        $this->assertTrue(Changes::isSensitive('pin', ['pin']), 'extra keys can be named');
    }

    public function testObjectsAndNestedDataAreFlattened(): void
    {
        $flat = Changes::flatten((object) ['a' => 1, 'b' => ['c' => 2, 'd' => ['e' => 3]], 'tags' => ['x', 'y'], 'rows' => [['n' => 1], ['n' => 2]]]);

        $this->assertSame(1, $flat['a']);
        $this->assertSame(2, $flat['b.c']);
        $this->assertSame(3, $flat['b.d.e']);
        $this->assertSame(['x', 'y'], $flat['tags'], 'a list of plain values stays one value');
        $this->assertSame(1, $flat['rows.0.n']);
        $this->assertSame(2, $flat['rows.1.n']);
    }

    public function testDiffListsOnlyWhatChanged(): void
    {
        $diff = Changes::diff(
            ['name' => 'Editor', 'description' => 'old', 'is_default' => 0, 'same' => 'x'],
            ['name' => 'Editor', 'description' => 'new', 'is_default' => '1', 'same' => 'x', 'added' => 'yes']
        );

        $this->assertSame(['description', 'is_default', 'added'], array_keys($diff));
        $this->assertSame(['from' => 'old', 'to' => 'new'], $diff['description']);
        $this->assertSame(['from' => 0, 'to' => '1'], $diff['is_default']);
        $this->assertSame(['from' => null, 'to' => 'yes'], $diff['added']);
    }

    public function testNothingAndEmptyTextAreTheSame(): void
    {
        $this->assertSame([], Changes::diff(['d' => null, 'n' => 5], ['d' => '', 'n' => '5']));
    }

    public function testListsShowWhatWasAddedAndRemoved(): void
    {
        $diff = Changes::diff(['permissions' => ['users.view', 'roles.view']], ['permissions' => ['roles.view', 'users.assign_role']]);

        $this->assertSame(['added' => ['users.assign_role'], 'removed' => ['users.view']], $diff['permissions']);
        $this->assertSame([], Changes::diff(['p' => ['a', 'b']], ['p' => ['b', 'a']]), 'order does not matter');
    }

    public function testSecretsInADiffAreNeverShown(): void
    {
        $diff = Changes::diff(['turnstile_secret_key' => 'old-secret', 'app_name' => 'A'], ['turnstile_secret_key' => 'new-secret', 'app_name' => 'B']);

        $this->assertSame(['from' => Changes::HIDDEN, 'to' => Changes::HIDDEN], $diff['turnstile_secret_key'], 'shown as changed, value hidden');
        $this->assertStringNotContainsString('secret', json_encode($diff['app_name']));
        $this->assertStringNotContainsString('old-secret', json_encode($diff));
        $this->assertStringNotContainsString('new-secret', json_encode($diff));

        $same = Changes::diff(['turnstile_secret_key' => 'same'], ['turnstile_secret_key' => 'same']);
        $this->assertSame([], $same, 'an unchanged secret is not reported at all');
    }

    public function testLongTextIsCut(): void
    {
        $long = str_repeat('é', 800);
        $snap = Changes::snapshot(['body' => $long]);

        $this->assertLessThan(600, mb_strlen($snap['body']));
        $this->assertStringContainsString('(+300 characters)', $snap['body']);
        $this->assertSame('short', Changes::shorten('short'));
    }

    public function testEncodedLogNeverExceedsTheColumn(): void
    {
        $big = [];
        for ($i = 0; $i < 400; ++$i) {
            $big['field_'.$i] = str_repeat('x', 400);
        }

        $json = Changes::encode(['new' => $big], 20000);
        $this->assertLessThanOrEqual(20000, strlen($json));
        $this->assertJson($json);

        $tiny = Changes::encode(['new' => $big], 300);
        $this->assertLessThanOrEqual(300, strlen($tiny));
        $this->assertSame(true, json_decode($tiny, true)['truncated']);

        $small = Changes::encode(['new' => ['a' => 'ünï']]);
        $this->assertSame('{"new":{"a":"ünï"}}', $small, 'normal logs are stored as they are, unicode intact');
    }
}

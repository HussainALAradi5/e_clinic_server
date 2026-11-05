<?php

use App\Models\Accounting\Account;
use Database\Seeders\ChartOfAccountsSeeder;

beforeEach(function () {
    $this->seed(ChartOfAccountsSeeder::class);
});

function accountId(string $code): string
{
    return Account::query()->where('code', $code)->value('id');
}

it('seeds the default chart as a tree with every automatic-posting role mapped', function () {
    actingWith('accounts.read_all');

    $tree = $this->getJson('/accounting/accounts/tree')->assertOk()->json('data');
    expect(array_column($tree, 'code'))->toBe(['1000', '2000', '3000', '4000', '5000'])
        ->and(array_column($tree[0]['children'], 'code'))->toBe(['1100', '1200', '1300', '1400']);

    $this->getJson('/accounting/accounts/tree?type=revenue')->assertJsonCount(1, 'data')->assertJsonCount(7, 'data.0.children');
    $mappings = collect($this->getJson('/accounting/accounts/mappings')->assertOk()->json('data'));
    expect($mappings)->toHaveCount(19)->and($mappings->whereNull('account'))->toBeEmpty();
});

it('creates leaf and group accounts under a group of the same type, never under itself', function () {
    actingWith('accounts.read_all', 'accounts.create', 'accounts.write');

    $group = $this->postJson('/accounting/accounts', ['code' => '4700', 'name' => 'Other revenue', 'type' => 'revenue', 'parent_id' => accountId('4000'), 'is_group' => true])
        ->assertCreated()->assertJsonPath('data.normal_balance', 'credit')->assertJsonPath('data.level', 2)->json('data.id');
    $leaf = $this->postJson('/accounting/accounts', ['code' => '4710', 'name' => 'Rental income', 'type' => 'revenue', 'parent_id' => $group])
        ->assertCreated()->assertJsonPath('data.level', 3)->assertJsonPath('data.is_postable', true)->json('data.id');

    $this->postJson('/accounting/accounts', ['code' => '4710', 'name' => 'Dup', 'type' => 'revenue'])->assertStatus(409)->assertJsonPath('error_code', 'ACCOUNT_CODE_TAKEN');
    $this->postJson('/accounting/accounts', ['code' => '5710', 'name' => 'Wrong type', 'type' => 'expense', 'parent_id' => $group])->assertStatus(422)->assertJsonPath('error_code', 'ACCOUNT_INVALID_PARENT');
    $this->postJson('/accounting/accounts', ['code' => '4720', 'name' => 'Under a leaf', 'type' => 'revenue', 'parent_id' => $leaf])->assertStatus(422);
    $this->putJson("/accounting/accounts/{$group}", ['parent_id' => $group])->assertStatus(422)->assertJsonPath('error_code', 'ACCOUNT_INVALID_PARENT');

    // Moving the group to the top level re-levels its subtree.
    $this->putJson("/accounting/accounts/{$group}", ['parent_id' => null])->assertOk()->assertJsonPath('data.level', 1);
    expect(Account::query()->find($leaf)->level)->toBe(2);
});

it('protects system and mapped accounts, and accounts that still have children', function () {
    actingWith('accounts.read_all', 'accounts.write', 'accounts.delete', 'accounts.create');

    $this->deleteJson('/accounting/accounts/'.accountId('1110'))->assertStatus(409)->assertJsonPath('error_code', 'ACCOUNT_SYSTEM_PROTECTED');
    $this->patchJson('/accounting/accounts/'.accountId('1110').'/archive')->assertStatus(409);

    $id = $this->postJson('/accounting/accounts', ['code' => '5700', 'name' => 'Sponsorships', 'type' => 'expense', 'parent_id' => accountId('5000')])->json('data.id');
    $this->deleteJson("/accounting/accounts/{$id}")->assertNoContent();
    $this->patchJson("/accounting/accounts/{$id}/restore")->assertOk()->assertJsonPath('data.is_deleted', false);
});

it('maps logical roles only to active leaf accounts of the matching type', function () {
    actingWith('accounts.read_all', 'accounts.write');

    $this->putJson('/accounting/accounts/mappings', ['mappings' => [['key' => 'cash_on_hand', 'account_id' => accountId('1100')]]])
        ->assertStatus(422)->assertJsonPath('error_code', 'ACCOUNT_NOT_POSTABLE');
    $this->putJson('/accounting/accounts/mappings', ['mappings' => [['key' => 'cash_on_hand', 'account_id' => accountId('4100')]]])->assertStatus(422);
    $this->putJson('/accounting/accounts/mappings', ['mappings' => [['key' => 'cash_on_hand', 'account_id' => accountId('1120')]]])
        ->assertOk()->assertJsonPath('data.0.account.code', '1120');
});

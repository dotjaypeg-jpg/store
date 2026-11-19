<?php

namespace Chargefield\Store\Tests;

use Chargefield\Store\Masters\MasterFile;
use Chargefield\Store\Masters\MasterReport;
use Chargefield\Store\StoreServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Orchestra\Testbench\TestCase;

/**
 * The masters report's preview_url is per-Work by default, but an owner may vary
 * it by file through an optional masterPreviewUrl($file) hook — so a Work's
 * apparel masters can show a thumbnail of the print while its poster master keeps
 * the flat artwork. Owners that don't define the hook keep the old per-Work
 * preview (masterIndexDetails()['preview_url']).
 */
class MasterReportPreviewTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [StoreServiceProvider::class];
    }

    private function file(Model $owner, string $role): MasterFile
    {
        $file = new MasterFile;
        $file->forceFill([
            'id' => 1,
            'role' => $role,
            'owner_id' => (int) ($owner->getKey() ?? 1),
            'owner_type' => $owner->getMorphClass(),
            'path' => "own-work/x/x-{$role}.png",
            'bytes' => 10,
        ]);
        $file->setRelation('owner', $owner);

        return $file;
    }

    public function test_apparel_file_uses_the_role_aware_preview_hook(): void
    {
        $owner = new ReportPreviewOwner;

        $this->assertSame(
            'https://cdn.test/apparel-thumb.png',
            MasterReport::snapshot($this->file($owner, 'apparel'))['preview_url'],
        );
        $this->assertSame(
            'https://cdn.test/apparel-thumb.png',
            MasterReport::snapshot($this->file($owner, 'apparel_print'))['preview_url'],
        );
    }

    public function test_a_hook_owners_other_roles_keep_the_work_preview(): void
    {
        $owner = new ReportPreviewOwner;

        $this->assertSame(
            'https://cdn.test/work.png',
            MasterReport::snapshot($this->file($owner, 'poster'))['preview_url'],
        );
    }

    public function test_owner_without_the_hook_falls_back_to_master_index_details(): void
    {
        $owner = new ReportPlainOwner;

        $this->assertSame(
            'https://cdn.test/plain.png',
            MasterReport::snapshot($this->file($owner, 'apparel'))['preview_url'],
        );
    }
}

class ReportPreviewOwner extends Model
{
    protected $table = 'report_preview_owners';

    protected $attributes = ['id' => 7];

    /** @return array{studio_client_id: null, preview_url: string} */
    public function masterIndexDetails(): array
    {
        return ['studio_client_id' => null, 'preview_url' => 'https://cdn.test/work.png'];
    }

    public function masterPreviewUrl(MasterFile $file): ?string
    {
        return in_array($file->role, ['apparel', 'apparel_print'], true)
            ? 'https://cdn.test/apparel-thumb.png'
            : 'https://cdn.test/work.png';
    }
}

class ReportPlainOwner extends Model
{
    protected $table = 'report_plain_owners';

    protected $attributes = ['id' => 9];

    /** @return array{preview_url: string} */
    public function masterIndexDetails(): array
    {
        return ['preview_url' => 'https://cdn.test/plain.png'];
    }
}

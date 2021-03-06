<?php

namespace Engelsystem\Test\Unit\Models\User;

use Engelsystem\Models\BaseModel;
use Engelsystem\Models\User\UsesUserModel;
use Engelsystem\Test\Unit\HasDatabase;
use Engelsystem\Test\Unit\TestCase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsesUserModelTest extends TestCase
{
    use HasDatabase;

    /**
     * @covers \Engelsystem\Models\User\UsesUserModel::user
     */
    public function testHasOneRelations()
    {
        /** @var UsesUserModel $contact */
        $model = new class extends BaseModel
        {
            use UsesUserModel;
        };

        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    /**
     * Prepare test
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->initDatabase();
    }
}

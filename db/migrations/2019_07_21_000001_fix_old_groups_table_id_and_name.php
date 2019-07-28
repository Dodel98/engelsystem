<?php

namespace Engelsystem\Migrations;

use Engelsystem\Database\Migration\Migration;

class FixOldGroupsTableIdAndName extends Migration
{
    /** @var array */
    protected $naming = [
        '1-Gast'              => 'Guest',
        '2-Engel'             => 'Angel',
        'Shirt-Manager'       => 'Shirt Manager',
        '3-Shift Coordinator' => 'Shift Coordinator',
        '4-Team Coordinator'  => 'Team Coordinator',
        '5-Bürokrat'          => 'Bureaucrat',
        '6-Developer'         => 'Developer',
    ];

    /** @var array */
    protected $ids = [
        -65 => -80,
        -70 => -90,
    ];

    /**
     * Run the migration
     */
    public function up()
    {
        $this->migrate($this->naming, $this->ids);
    }

    /**
     * Reverse the migration
     */
    public function down()
    {
        $this->migrate(array_flip($this->naming), array_flip($this->ids));
    }

    /**
     * @param array $naming
     * @param array $ids
     */
    protected function migrate($naming, $ids)
    {
        if (!$this->schema->hasTable('Groups')) {
            return;
        }

        $connection = $this->schema->getConnection();
        foreach ($connection->table('Groups')->get() as $data) {
            if (isset($naming[$data->Name])) {
                $data->Name = $naming[$data->Name];
            }

            $data->oldId = $data->UID;
            if (isset($ids[$data->oldId])) {
                $data->UID = $ids[$data->oldId];
            } elseif (isset($ids[$data->oldId * -1])) {
                $data->UID = $ids[$data->oldId * -1] * -1;
            }

            $connection
                ->table('Groups')
                ->where('UID', $data->oldId)
                ->update([
                    'UID'  => $data->UID * -1,
                    'Name' => $data->Name,
                ]);
        }
    }
}
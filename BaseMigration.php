<?php

namespace app\migrations\common;

use app\helpers\SqlSnippet;
use yii\db\Expression;
use yii\db\Migration;

/**
 * Class BaseMigration
 */
abstract class BaseMigration extends Migration
{
    /**
     * @inheritdoc
     */
    public function createTable($table, $columns, $options = null)
    {
        $columns = array_merge([
            "id" => $this->primaryKey()->comment("Идентификатор"),
            "created" => $this->dateTime()->defaultValue(new Expression('NOW()'))->comment("Дата и время создания записи"),
            "updated" => $this->dateTime()->defaultValue(new Expression('NOW()'))->comment("Дата и время изменения записи"),
            "deleted" => $this->dateTime()->null()->comment("Дата и время аннулирования записи")
        ], $columns);
        parent::createTable($table, $columns, $options);

        $this->execute(SqlSnippet::createTriggerToSetUpdated($table));
    }

    public function dropTable($table)
    {
        $this->execute(SqlSnippet::dropTriggerToSetUpdated($table));
        parent::dropTable($table);
    }
}
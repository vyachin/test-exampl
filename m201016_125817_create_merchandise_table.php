<?php

use yii\db\Migration;

/**
 * Товары (подписки в том числе)
 */
class m201016_125817_create_merchandise_table extends Migration
{
    private $table = 'merchandise';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable($this->table, [
            'id' => $this->bigPrimaryKey()->comment("ID"),
            'title' => $this->string(255)->notNull()->comment("Наименование"),
            'code' => $this->string(255)->notNull()->comment("Код"),
            'description' => $this->text()->comment("Описание"),
            'sales_start_date' => $this->date()->notNull()->comment("Начало продаж"),
            'sales_end_date' => $this->date()->notNull()->comment("Окончание продаж"),
            'subscription_period' => $this->string(31)->defaultValue(null)->comment("Период подписки"),
            'price' => $this->decimal(19, 4)->notNull()->defaultValue(0)->comment("Цена")
        ], 'DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addCommentOnTable($this->table, "Товары");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable($this->table);
    }
}

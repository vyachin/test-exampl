<?php

use yii\db\Expression;
use yii\db\Migration;

/**
 * Покупки в Google Play и App Store
 */
class m201016_131540_create_purchase_table extends Migration
{
    private $table = 'purchase';

    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable($this->table, [
            'id' => $this->bigPrimaryKey()->comment("ID"),
            'created' => $this->dateTime()->defaultValue(new Expression("NOW()"))->notNull()->comment("Дата"),
            'user_id' => $this->bigInteger()->notNull()->comment("Учётная запись"),
            'merchandise_id' => $this->bigInteger()->notNull()->comment("Товар"),
            'price' => $this->decimal(19, 4)->notNull()->defaultValue(0)->comment("Цена"),
            'shop' => $this->string(63)->notNull()->comment("Магазин"),
            'shop_sell_id' => $this->string(255)->comment("Идентификатор продажи в магазине"),
            'expiration_date' => $this->dateTime()->notNull()->comment("Окончание подписки"),
        ], 'DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addCommentOnTable($this->table, "Покупки в Google Play и App Store");

        $this->addForeignKey(
            'fk-purchase-user',
            $this->table,
            'user_id',
            'user',
            'id',
            'RESTRICT'
        );
        $this->addForeignKey(
            'fk-purchase-merchandise',
            $this->table,
            'merchandise_id',
            'merchandise',
            'id',
            'RESTRICT'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable($this->table);
    }
}

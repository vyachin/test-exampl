<?php

use app\migrations\common\SimpleForeignKeyTrait;
use yii\db\Migration;

/**
 * часть благодарности с баллами от конкретного центра затрат `{{%gratitude_part}}`.
 */
class m211105_121607_create_gratitude_part_table extends Migration
{
    use SimpleForeignKeyTrait;

    private $foreignKeys = [
        'gratitude_id' => ['gratitude', 'id'],
        'cost_center_id' => ['cost_center', 'id']
    ];
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%gratitude_part}}', [
            'id' => $this->primaryKey(),
            'gratitude_id' => $this->integer()->notNull()->comment("Благодарность"),
            'cost_center_id' => $this->integer()->notNull()->comment("Центр затрат"),
            'amount' => $this->decimal(9,4)->notNull()->comment("Количество баллов"),
        ]);
        $this->addCommentOnTable('{{%gratitude_part}}', "Баллы благодарности");
        $this->simpleForeignKeysUp('{{%gratitude_part}}', $this->foreignKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%gratitude_part}}');
    }
}

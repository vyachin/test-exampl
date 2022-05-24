<?php

use app\migrations\common\SimpleForeignKeyTrait;
use yii\db\Migration;

/**
 * Class m211116_134541_add_in_out_cc_le_to_purchase_customer_contribution_table
 */
class m211116_134541_add_in_out_cc_le_to_purchase_customer_contribution_table extends Migration
{
    use SimpleForeignKeyTrait;

    private $foreignKeys = [
        'issuer_legal_entity_id' => ['legal_entity', 'id'],
        'issuer_cost_center_id' => ['cost_center', 'id'],
        'consumer_legal_entity_id' => ['legal_entity', 'id'],
        'consumer_cost_center_id' => ['cost_center', 'id']
    ];

    private $forward_sql = <<<SQL
UPDATE purchase_customer_contribution target SET
    issuer_legal_entity_id = issuer_le.id,
    issuer_cost_center_id = issuer_cc.id,
    consumer_legal_entity_id = consumer_le.id,
    consumer_cost_center_id = consumer_cc.id
  FROM purchase_customer_contribution pcc
  JOIN cost_center issuer_cc ON pcc.cost_center_id = issuer_cc.id
  JOIN legal_entity issuer_le ON issuer_cc.legal_entity_id = issuer_le.id
  JOIN account consumer ON pcc.customer_id = consumer.id
  JOIN cost_center consumer_cc ON consumer.cost_center_id = consumer_cc.id
  JOIN legal_entity consumer_le ON consumer_cc.legal_entity_id = consumer_le.id
  WHERE target.id = pcc.id
SQL;


    private $oldForeignKeys = [
        'cost_center_id' => ['cost_center', 'id']
    ];
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->addColumn('purchase_customer_contribution', 'issuer_legal_entity_id', $this->integer()->comment("Юр.лицо эмитента"));
        $this->addColumn('purchase_customer_contribution', 'issuer_cost_center_id', $this->integer()->comment("Центр затрат эмитента"));
        $this->addColumn('purchase_customer_contribution', 'consumer_legal_entity_id', $this->integer()->comment("Юр.лицо потребителя"));
        $this->addColumn('purchase_customer_contribution', 'consumer_cost_center_id', $this->integer()->comment("Центр затрат потребителя"));

        $this->db->createCommand($this->forward_sql)->execute();

        $this->alterColumn('purchase_customer_contribution', 'issuer_legal_entity_id', $this->integer()->notNull());
        $this->alterColumn('purchase_customer_contribution', 'issuer_cost_center_id', $this->integer()->notNull());
        $this->alterColumn('purchase_customer_contribution', 'consumer_legal_entity_id', $this->integer()->notNull());
        $this->alterColumn('purchase_customer_contribution', 'consumer_cost_center_id', $this->integer()->notNull());

        $this->dropColumn('purchase_customer_contribution', 'cost_center_id');

        $this->simpleForeignKeysUp('purchase_customer_contribution', $this->foreignKeys);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->addColumn('purchase_customer_contribution', 'cost_center_id', $this->integer()->comment("Центр затрат"));

        $this->db->createCommand("UPDATE purchase_customer_contribution SET cost_center_id = issuer_cost_center_id")->execute();

        $this->alterColumn('purchase_customer_contribution', 'cost_center_id', $this->integer()->notNull());

        $this->dropColumn('purchase_customer_contribution', 'consumer_cost_center_id');
        $this->dropColumn('purchase_customer_contribution', 'consumer_legal_entity_id');
        $this->dropColumn('purchase_customer_contribution', 'issuer_cost_center_id');
        $this->dropColumn('purchase_customer_contribution', 'issuer_legal_entity_id');

        $this->simpleForeignKeysUp('purchase_customer_contribution', $this->oldForeignKeys);
    }
}

<?php

namespace craft\commerce\migrations;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\MigrationHelper;
use craft\helpers\StringHelper;

/**
 * m170904_130000_processing_transactions
 */
class m170904_130000_processing_transactions extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->dropColumn('{{%commerce_transactions}}', 'gatewayProcessing');

        $this->alterColumn('{{%commerce_transactions}}', 'status', $this->enum('status', ['pending', 'redirect', 'success', 'failed', 'processing'])->notNull());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m170904_130000_processing_transactions cannot be reverted.\n";

        return false;
    }
}
